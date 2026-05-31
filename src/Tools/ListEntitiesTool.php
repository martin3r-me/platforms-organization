<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListEntitiesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entities.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/entities - Listet Organisationseinheiten (Entities) im Team. Entities sind die zentralen Knoten der Organisation (Abteilungen, Standorte, Business Units etc.). Unterstützt direkte Filter-Parameter: entity_type_id, parent_entity_id, roots_only. Für Baum-Traversierung: erst roots_only=true, dann parent_entity_id=<id> für Kinder.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Wird auf Root/Elterteam aufgelöst.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: true = nur aktive, false = nur inaktive. Default: true (nur aktive).',
                        'default' => true,
                    ],
                    'entity_type_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter: nur Entities dieses Typs zurückgeben. Beispiel: entity_type_id=11',
                    ],
                    'parent_entity_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter: nur direkte Kinder dieser Entity zurückgeben. Beispiel: parent_entity_id=5 liefert alle Kinder von Entity 5.',
                    ],
                    'roots_only' => [
                        'type' => 'boolean',
                        'description' => 'Direkter Filter: true = nur Root-Entities (ohne Eltern). Nützlich als Einstieg für Baum-Traversierung.',
                    ],
                    'include_relations' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Typ-Name und Parent-Name mitladen. Default: false.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $activeOnly = (bool) ($arguments['is_active'] ?? true);
            $q = OrganizationEntity::query()->where('team_id', $rootTeamId);

            if ($activeOnly) {
                $q->where('is_active', true);
            } elseif (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', false);
            }

            if (!empty($arguments['entity_type_id'])) {
                $q->where('entity_type_id', (int) $arguments['entity_type_id']);
            }

            if (!empty($arguments['roots_only'])) {
                $q->whereNull('parent_entity_id');
            } elseif (array_key_exists('parent_entity_id', $arguments) && $arguments['parent_entity_id'] !== null && $arguments['parent_entity_id'] !== '') {
                $q->where('parent_entity_id', (int) $arguments['parent_entity_id']);
            }

            if (!empty($arguments['include_relations'])) {
                $q->with(['type', 'parent']);
            }

            $this->applyStandardFilters($q, $arguments, ['created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'code', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'code', 'id', 'created_at'], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $includeRelations = !empty($arguments['include_relations']);

            $items = $result['data']->map(function ($e) use ($includeRelations) {
                $item = [
                    'id' => $e->id,
                    'code' => $e->code,
                    'name' => $e->name,
                    'entity_type_id' => $e->entity_type_id,
                    'parent_entity_id' => $e->parent_entity_id,
                    'is_active' => (bool) $e->is_active,
                ];

                if ($includeRelations) {
                    $item['type_name'] = $e->type?->name;
                    $item['parent_name'] = $e->parent?->name;
                }

                return $item;
            })->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Entities: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'entities', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
