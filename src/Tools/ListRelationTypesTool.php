<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationEntityRelationType;

/**
 * Listet Entity Relation Types (global, nicht team-scoped).
 */
class ListRelationTypesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.relation_types.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/relation-types - Listet Entity Relation Types (global). Nutze dieses Tool bevor du relation_type_id an anderen Tools setzt (IDs nie raten). Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['is_active', 'code', 'sort_order', 'name', 'is_directional', 'is_hierarchical', 'is_reciprocal']),
            [
                'properties' => [
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: aktive/inaktive Relation Types. Default: true.',
                        'default' => true,
                    ],
                    'is_directional' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach direktionalen Relation Types.',
                    ],
                    'is_hierarchical' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach hierarchischen Relation Types.',
                    ],
                    'is_reciprocal' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach reziproken Relation Types.',
                    ],
                    'code' => [
                        'type' => 'string',
                        'description' => 'Optional: Exakter code-Filter.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $activeOnly = (bool)($arguments['is_active'] ?? true);

            $q = OrganizationEntityRelationType::query();

            if ($activeOnly) {
                $q->where('is_active', true);
            } elseif (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', false);
            }

            if (array_key_exists('code', $arguments) && $arguments['code'] !== null && $arguments['code'] !== '') {
                $q->where('code', trim((string)$arguments['code']));
            }

            foreach (['is_directional', 'is_hierarchical', 'is_reciprocal'] as $boolField) {
                if (array_key_exists($boolField, $arguments) && $arguments[$boolField] !== null) {
                    $q->where($boolField, (bool)$arguments[$boolField]);
                }
            }

            $this->applyStandardFilters($q, $arguments, ['is_active', 'code', 'sort_order', 'name', 'is_directional', 'is_hierarchical', 'is_reciprocal', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'code', 'description']);
            $this->applyStandardSort($q, $arguments, ['sort_order', 'name', 'code', 'id', 'created_at'], 'sort_order', 'asc');

            if (empty($arguments['sort'])) {
                $q->orderBy('name', 'asc');
            }

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($rt) => [
                'id' => $rt->id,
                'code' => $rt->code,
                'name' => $rt->name,
                'description' => $rt->description,
                'icon' => $rt->icon,
                'sort_order' => $rt->sort_order,
                'is_active' => (bool)$rt->is_active,
                'is_directional' => (bool)$rt->is_directional,
                'is_hierarchical' => (bool)$rt->is_hierarchical,
                'is_reciprocal' => (bool)$rt->is_reciprocal,
                'metadata' => $rt->metadata,
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Relation Types: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'relation_types', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
