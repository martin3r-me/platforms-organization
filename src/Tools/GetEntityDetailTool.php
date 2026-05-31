<?php

namespace Platform\Organization\Tools;

use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Services\PersonActivityRegistry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class GetEntityDetailTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entity.detail.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/entity/detail - Vollständiges Bild einer Entity in einem Call: Stammdaten, Kind-Entities, verknüpfte Objekte (Projekte, Korrespondenz, Tasks etc.) mit Metadaten, und berechnete KPIs. Ersetzt 4-5 separate Tool-Calls.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Entity.',
                ],
                'include_children' => [
                    'type' => 'boolean',
                    'description' => 'Optional: direkte Kind-Entities mitladen (Default: true).',
                    'default' => true,
                ],
                'include_links' => [
                    'type' => 'boolean',
                    'description' => 'Optional: alle verknüpften Objekte gruppiert nach Typ mit Metadaten laden (Default: true).',
                    'default' => true,
                ],
                'include_metrics' => [
                    'type' => 'boolean',
                    'description' => 'Optional: berechnete KPIs aller Provider laden (Default: true).',
                    'default' => true,
                ],
            ],
            'required' => ['entity_id'],
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

            $entityId = (int) $arguments['entity_id'];
            $entity = OrganizationEntity::where('team_id', $rootTeamId)
                ->with(['type.group', 'parent'])
                ->find($entityId);

            if (!$entity) {
                return ToolResult::error('NOT_FOUND', "Entity {$entityId} nicht gefunden oder nicht im Team.");
            }

            $result = [
                'id' => $entity->id,
                'name' => $entity->name,
                'code' => $entity->code,
                'type_name' => $entity->type?->name,
                'type_group' => $entity->type?->group?->name,
                'parent_id' => $entity->parent_entity_id,
                'parent_name' => $entity->parent?->name,
                'is_active' => (bool) $entity->is_active,
                'description' => $entity->description,
            ];

            // Children (default: true)
            $includeChildren = $arguments['include_children'] ?? true;
            $childIds = [];
            if ($includeChildren) {
                $children = OrganizationEntity::where('parent_entity_id', $entityId)
                    ->where('team_id', $rootTeamId)
                    ->where('is_active', true)
                    ->with('type')
                    ->orderBy('name')
                    ->get();

                $childIds = $children->pluck('id')->toArray();

                $result['children'] = $children->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'type_name' => $c->type?->name,
                    'is_active' => (bool) $c->is_active,
                ])->toArray();
            }

            // Links + Metrics: load dimension links once, reuse for both
            // Include child entity links when include_children is active
            $includeLinks = $arguments['include_links'] ?? true;
            $includeMetrics = $arguments['include_metrics'] ?? true;

            if ($includeLinks || $includeMetrics) {
                $linkEntityIds = array_merge([$entityId], $childIds);
                $links = EntityDimensionBridge::linksForEntities($linkEntityIds);
                $registry = resolve(EntityLinkRegistry::class);
                $reverseMorphMap = array_flip(Relation::morphMap());

                // Group by morph alias, track entity_id per linkable
                $grouped = [];
                $linkableEntityMap = []; // linkable_id → entity_id
                foreach ($links as $link) {
                    $alias = $reverseMorphMap[$link->linkable_type] ?? $link->linkable_type;
                    $grouped[$alias][] = $link->linkable_id;
                    $linkableEntityMap[$alias][$link->linkable_id] = $link->entity_id;
                }

                if ($includeLinks) {
                    $typeConfig = $registry->allLinkTypeConfig();
                    $linkResults = [];

                    foreach ($grouped as $alias => $ids) {
                        $ids = array_unique($ids);
                        $config = $typeConfig[$alias] ?? [];
                        $label = $config['label'] ?? $alias;

                        // Resolve FQCN and batch-load models
                        $fqcn = Relation::getMorphedModel($alias);
                        $items = [];

                        if ($fqcn && class_exists($fqcn)) {
                            $provider = $registry->getProvider($alias);
                            $query = $fqcn::whereIn('id', $ids);

                            if ($provider) {
                                $provider->applyEagerLoading($query, $alias, $fqcn);
                            }

                            $models = $query->get();

                            foreach ($models as $model) {
                                $meta = $provider ? $provider->extractMetadata($alias, $model) : [];
                                $item = array_merge(['id' => $model->id], $meta);

                                // Attribute to source entity when children are included
                                $sourceEntityId = $linkableEntityMap[$alias][$model->id] ?? null;
                                if ($sourceEntityId && $sourceEntityId !== $entityId) {
                                    $item['via_entity_id'] = $sourceEntityId;
                                }

                                $items[] = $item;
                            }
                        }

                        $linkResults[] = [
                            'type' => $alias,
                            'label' => $label,
                            'count' => count($ids),
                            'items' => $items,
                        ];
                    }

                    $result['links'] = $linkResults;
                }

                if ($includeMetrics) {
                    // Build per-entity link map for metric computation
                    $linksByEntityAndType = [];
                    foreach ($links as $link) {
                        $eid = $link->entity_id ?? $entityId;
                        $alias = $reverseMorphMap[$link->linkable_type] ?? $link->linkable_type;
                        $linksByEntityAndType[$eid][$alias][] = $link->linkable_id;
                    }

                    $allMetrics = $registry->computeMetricsBatch($linksByEntityAndType);

                    // Merge metrics across entity + children
                    $mergedMetrics = [];
                    foreach ($allMetrics as $eid => $entityMetrics) {
                        foreach ($entityMetrics as $key => $value) {
                            $mergedMetrics[$key] = ($mergedMetrics[$key] ?? 0) + $value;
                        }
                    }
                    $result['metrics'] = $mergedMetrics;
                }
            }

            // Person-Entity: include user-bridged data (assigned tasks, tickets etc.)
            if ($entity->linked_user_id) {
                $result['linked_user_id'] = $entity->linked_user_id;
                $personRegistry = resolve(PersonActivityRegistry::class);

                $result['vital_signs'] = $personRegistry->allVitalSigns(
                    $entity->linked_user_id,
                    $rootTeamId
                );
                $result['responsibilities'] = $personRegistry->allResponsibilities(
                    $entity->linked_user_id,
                    $rootTeamId,
                    10
                );
            }

            return ToolResult::success(['entity' => $result]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Entity-Details: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'entity', 'detail', 'traversal'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
