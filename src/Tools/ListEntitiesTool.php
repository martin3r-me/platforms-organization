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
        return 'GET /organization/entities - Listet Organisationseinheiten (Entities) im Team. Unterstützt filters/search/sort/limit/offset. Entities sind die zentralen Knoten der Organisation (Abteilungen, Standorte, Business Units etc.).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'is_active', 'entity_type_id', 'vsm_system_id', 'parent_entity_id']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Wird auf Root/Elterteam aufgelöst.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: aktive/inaktive Entities. Default: true.',
                        'default' => true,
                    ],
                    'entity_type_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity Type ID.',
                    ],
                    'vsm_system_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach VSM System ID.',
                    ],
                    'parent_entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Parent Entity ID (null = nur Root-Entities).',
                    ],
                    'include_relations' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Typ, VSM-System, Kostenstelle und Parent mitladen. Default: false.',
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

            if (array_key_exists('entity_type_id', $arguments) && $arguments['entity_type_id'] !== null) {
                $q->where('entity_type_id', (int) $arguments['entity_type_id']);
            }
            if (array_key_exists('vsm_system_id', $arguments) && $arguments['vsm_system_id'] !== null) {
                $q->where('vsm_system_id', (int) $arguments['vsm_system_id']);
            }
            if (array_key_exists('parent_entity_id', $arguments)) {
                $pid = $arguments['parent_entity_id'];
                if ($pid === null || $pid === '' || $pid === 'null' || $pid === 0 || $pid === '0') {
                    $q->whereNull('parent_entity_id');
                } else {
                    $q->where('parent_entity_id', (int) $pid);
                }
            }

            if (!empty($arguments['include_relations'])) {
                $q->with(['type', 'vsmSystem', 'costCenter', 'parent']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'is_active', 'entity_type_id', 'vsm_system_id', 'parent_entity_id', 'created_at']);
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
                    'vsm_system_id' => $e->vsm_system_id,
                    'cost_center_id' => $e->cost_center_id,
                    'parent_entity_id' => $e->parent_entity_id,
                    'is_active' => (bool) $e->is_active,
                ];

                if ($includeRelations) {
                    $item['type_name'] = $e->type?->name;
                    $item['vsm_system_name'] = $e->vsmSystem?->name;
                    $item['cost_center_name'] = $e->costCenter?->name;
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
