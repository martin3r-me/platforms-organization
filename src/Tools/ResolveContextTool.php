<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\ObjectContextResolver;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ResolveContextTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.context.resolve.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/context/resolve - Löst von jedem Objekt im System (Projekt, Task, Ticket, Canvas etc.) die zugehörige Entity-Struktur auf. Gibt verknüpfte Entities mit vollständigem Org-Pfad zurück (Entity → Parent → Root). Akzeptiert einfache Namen wie "project", "task", "ticket" — kein Vorwissen über interne Typen nötig.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'object_type' => [
                    'type' => 'string',
                    'description' => 'Typ des Objekts. Einfache Namen: project, task, ticket, canvas, thread, contact, okr, issue, package, note, employee, applicant, process, brand, spreadsheet, presentation, recording. Oder exakter Morph-Alias.',
                ],
                'object_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Objekts.',
                ],
            ],
            'required' => ['object_type', 'object_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $objectType = $arguments['object_type'];
            $objectId = (int) $arguments['object_id'];

            $morphAlias = ObjectContextResolver::resolveAlias($objectType);
            if (!$morphAlias) {
                return ToolResult::error('UNKNOWN_TYPE', "Unbekannter object_type '{$objectType}'. Bekannte Typen: " . implode(', ', ObjectContextResolver::knownTypes()));
            }

            ['links' => $links, 'resolved_via' => $resolvedVia] = ObjectContextResolver::entityLinks($morphAlias, $objectId);

            if ($links->isEmpty()) {
                return ToolResult::success([
                    'object_type' => $objectType,
                    'resolved_morph_alias' => $morphAlias,
                    'object_id' => $objectId,
                    'entities' => [],
                    'message' => 'Keine Entity-Verknüpfung gefunden — weder direkt noch über Parent-Objekt.',
                ]);
            }

            // Build entity results with org path
            $entities = [];
            foreach ($links as $link) {
                $entity = $link->relationLoaded('entity') ? $link->getRelation('entity') : null;
                if (!$entity) {
                    continue;
                }

                // Build org path: walk up parent chain
                $path = [];
                $current = $entity;
                $visited = [];
                while ($current && !in_array($current->id, $visited)) {
                    $visited[] = $current->id;
                    $path[] = [
                        'id' => $current->id,
                        'name' => $current->name,
                        'type_name' => $current->type?->name,
                    ];
                    if ($current->parent_entity_id) {
                        $current = OrganizationEntity::where('team_id', $rootTeamId)
                            ->with('type')
                            ->find($current->parent_entity_id);
                    } else {
                        $current = null;
                    }
                }

                $entities[] = [
                    'entity_id' => $entity->id,
                    'entity_name' => $entity->name,
                    'entity_type' => $entity->type?->name,
                    'org_path' => $path,
                    'org_path_display' => implode(' → ', array_column($path, 'name')),
                ];
            }

            $response = [
                'object_type' => $objectType,
                'resolved_morph_alias' => $morphAlias,
                'object_id' => $objectId,
                'entities' => $entities,
            ];

            if ($resolvedVia) {
                $response['resolved_via'] = $resolvedVia;
            }

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Auflösen des Kontexts: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'context', 'resolve', 'traversal'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
