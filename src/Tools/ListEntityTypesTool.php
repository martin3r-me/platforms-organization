<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationEntityType;

/**
 * Listet Entity Types (global, nicht team-scoped).
 */
class ListEntityTypesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.entity_types.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/entity-types - Listet Entity Types (global). WICHTIG: IDs nie raten — immer erst dieses Tool aufrufen. Filtere nach code, entity_type_group_id oder vsm_class (carrier/actor/observed).';
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
                    'entity_type_group_id' => [
                        'type' => 'integer',
                        'description' => 'Direkter Filter: nur Types dieser Gruppe. Nutze entity_type_groups.GET für IDs.',
                    ],
                    'code' => [
                        'type' => 'string',
                        'description' => 'Direkter Filter: exakter Code-Match. Beispiel: code="department"',
                    ],
                    'vsm_class' => [
                        'type' => 'string',
                        'enum' => OrganizationEntityType::VSM_CLASSES,
                        'description' => 'Optional: Filter nach VSM-Klasse (carrier/actor/observed).',
                    ],
                    'can_be_perspective' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur Types deren Entities Perspektive sein duerfen (= carrier-Types).',
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

            $q = OrganizationEntityType::query();

            if ($activeOnly) {
                $q->where('is_active', true);
            } elseif (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', false);
            }

            if (!empty($arguments['entity_type_group_id'])) {
                $q->where('entity_type_group_id', (int)$arguments['entity_type_group_id']);
            }

            if (!empty($arguments['code'])) {
                $q->where('code', trim((string)$arguments['code']));
            }

            if (!empty($arguments['vsm_class'])) {
                $vc = (string) $arguments['vsm_class'];
                if (in_array($vc, OrganizationEntityType::VSM_CLASSES, true)) {
                    $q->where('vsm_class', $vc);
                }
            }

            if (array_key_exists('can_be_perspective', $arguments)) {
                $q->where('can_be_perspective', (bool) $arguments['can_be_perspective']);
            }

            $this->applyStandardFilters($q, $arguments, ['created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'code', 'description']);
            $this->applyStandardSort($q, $arguments, ['sort_order', 'name', 'code', 'id', 'created_at'], 'sort_order', 'asc');

            // Add secondary sort by name if primary is sort_order
            if (empty($arguments['sort'])) {
                $q->orderBy('name', 'asc');
            }

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($et) => [
                'id' => $et->id,
                'code' => $et->code,
                'name' => $et->name,
                'description' => $et->description,
                'icon' => $et->icon,
                'sort_order' => $et->sort_order,
                'is_active' => (bool)$et->is_active,
                'entity_type_group_id' => $et->entity_type_group_id,
                'group_name' => $et->group?->name,
                'vsm_class' => $et->vsm_class,
                'can_be_perspective' => (bool) $et->can_be_perspective,
                'metadata' => $et->metadata,
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Entity Types: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'entity_types', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
