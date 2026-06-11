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
 *
 * Inklusive Beer-Channel-Properties — Filter nach affects_aggregation und
 * channel_class moeglich, um z.B. nur operative Channels fuer Snapshot-Service
 * abzurufen.
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
        return 'GET /organization/relation-types - Listet Entity Relation Types (global). WICHTIG: IDs nie raten — immer erst dieses Tool aufrufen. Filtere nach code, is_hierarchical, affects_aggregation, channel_class.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: true = nur aktive, false = nur inaktive. Default: true.',
                        'default' => true,
                    ],
                    'code' => [
                        'type' => 'string',
                        'description' => 'Direkter Filter: exakter Code-Match. Beispiel: code="reports_to"',
                    ],
                    'is_directional' => [
                        'type' => 'boolean',
                        'description' => 'Direkter Filter: true = nur gerichtete Beziehungen (A->B != B->A).',
                    ],
                    'is_hierarchical' => [
                        'type' => 'boolean',
                        'description' => 'Direkter Filter: true = nur hierarchische Beziehungen (Ueber-/Unterordnung).',
                    ],
                    'is_reciprocal' => [
                        'type' => 'boolean',
                        'description' => 'Direkter Filter: true = nur wechselseitige Beziehungen (A<->B).',
                    ],
                    'affects_aggregation' => [
                        'type' => 'boolean',
                        'description' => 'Direkter Filter: true = nur Channels, die Snapshot/Movement-Aggregation triggern.',
                    ],
                    'channel_class' => [
                        'type' => 'string',
                        'enum' => ['operational', 'informational', 'structural', 'algedonic', 'environmental'],
                        'description' => 'Direkter Filter: nach Beer-Channel-Klasse.',
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

            if (!empty($arguments['code'])) {
                $q->where('code', trim((string)$arguments['code']));
            }

            foreach (['is_directional', 'is_hierarchical', 'is_reciprocal', 'affects_aggregation'] as $boolField) {
                if (array_key_exists($boolField, $arguments) && $arguments[$boolField] !== null) {
                    $q->where($boolField, (bool)$arguments[$boolField]);
                }
            }

            if (!empty($arguments['channel_class'])) {
                $q->where('channel_class', (string)$arguments['channel_class']);
            }

            $this->applyStandardFilters($q, $arguments, ['created_at']);
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
                'affects_aggregation' => (bool)$rt->affects_aggregation,
                'is_recursive' => (bool)$rt->is_recursive,
                'cascade_to_children' => (bool)$rt->cascade_to_children,
                'aggregation_weight' => (float)$rt->aggregation_weight,
                'traversal_direction' => $rt->traversal_direction,
                'inverse_code' => $rt->inverse_code,
                'allowed_from_types' => $rt->allowed_from_types,
                'allowed_to_types' => $rt->allowed_to_types,
                'cardinality' => $rt->cardinality,
                'channel_class' => $rt->channel_class,
                'variety_flow' => $rt->variety_flow,
                'capabilities' => $rt->capabilities,
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
