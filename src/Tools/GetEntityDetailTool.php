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
                    'description' => 'Optional: direkte Kind-Entities mitladen (Default: false).',
                ],
                'include_links' => [
                    'type' => 'boolean',
                    'description' => 'Optional: alle verknüpften Objekte gruppiert nach Typ mit Metadaten laden (Default: false).',
                ],
                'include_metrics' => [
                    'type' => 'boolean',
                    'description' => 'Optional: berechnete KPIs aller Provider laden (Default: false).',
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

            // Children
            if (!empty($arguments['include_children'])) {
                $children = OrganizationEntity::where('parent_entity_id', $entityId)
                    ->where('team_id', $rootTeamId)
                    ->where('is_active', true)
                    ->with('type')
                    ->orderBy('name')
                    ->get();

                $result['children'] = $children->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'type_name' => $c->type?->name,
                    'is_active' => (bool) $c->is_active,
                ])->toArray();
            }

            // Links
            if (!empty($arguments['include_links'])) {
                $links = EntityDimensionBridge::linksForEntity($entityId);
                $registry = resolve(EntityLinkRegistry::class);
                $typeConfig = $registry->allLinkTypeConfig();
                $reverseMorphMap = array_flip(Relation::morphMap());

                // Group by morph alias
                $grouped = [];
                foreach ($links as $link) {
                    $alias = $reverseMorphMap[$link->linkable_type] ?? $link->linkable_type;
                    $grouped[$alias][] = $link->linkable_id;
                }

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
                            $items[] = array_merge(
                                ['id' => $model->id],
                                $meta
                            );
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

            // Metrics
            if (!empty($arguments['include_metrics'])) {
                $links = EntityDimensionBridge::linksForEntity($entityId);
                $reverseMorphMap = array_flip(Relation::morphMap());

                $linksByEntityAndType = [];
                foreach ($links as $link) {
                    $alias = $reverseMorphMap[$link->linkable_type] ?? $link->linkable_type;
                    $linksByEntityAndType[$entityId][$alias][] = $link->linkable_id;
                }

                $registry = resolve(EntityLinkRegistry::class);
                $metrics = $registry->computeMetricsBatch($linksByEntityAndType);
                $result['metrics'] = $metrics[$entityId] ?? [];
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
