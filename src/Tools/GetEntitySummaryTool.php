<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class GetEntitySummaryTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entity.summary.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/entity/summary - Aggregierter Status einer Entity in einem Call: Link-Counts nach Typ, Signal-Counts, Provider-Metriken. Ideal als Einstieg um zu sehen was bei einer Entity los ist, bevor man tiefer geht.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Entity für die der Summary geladen werden soll.',
                ],
                'include_children' => [
                    'type' => 'boolean',
                    'description' => 'Optional: true = Kind-Entities mit aggregieren (Default: false).',
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
            $entity = OrganizationEntity::where('team_id', $rootTeamId)->find($entityId);

            if (!$entity) {
                return ToolResult::error('NOT_FOUND', "Entity {$entityId} nicht gefunden oder nicht im Team.");
            }

            $includeChildren = !empty($arguments['include_children']);
            $entityIds = [$entityId];

            if ($includeChildren) {
                $childIds = OrganizationEntity::where('parent_entity_id', $entityId)
                    ->where('team_id', $rootTeamId)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();
                $entityIds = array_merge($entityIds, $childIds);
            }

            // Link counts by type
            $linkCounts = EntityDimensionBridge::linkCountsByEntityAndType($entityIds);
            $mergedCounts = [];
            foreach ($linkCounts as $eid => $typeCounts) {
                foreach ($typeCounts as $type => $count) {
                    $mergedCounts[$type] = ($mergedCounts[$type] ?? 0) + $count;
                }
            }
            $linksTotal = array_sum($mergedCounts);

            // Signal counts
            $signals = OrganizationSignal::whereIn('entity_id', $entityIds)
                ->whereNull('resolved_at')
                ->whereNull('deleted_at')
                ->selectRaw('severity, count(*) as cnt')
                ->groupBy('severity')
                ->pluck('cnt', 'severity')
                ->toArray();

            $signalSummary = [
                'open_total' => array_sum($signals),
                'critical' => $signals['critical'] ?? 0,
                'warning' => $signals['warning'] ?? 0,
                'info' => $signals['info'] ?? 0,
            ];

            // Provider metrics
            $links = EntityDimensionBridge::linksForEntities($entityIds);
            $linksByEntityAndType = [];
            foreach ($links as $link) {
                $eid = $link->entity_id;
                if (!$eid) {
                    continue;
                }
                $morphMap = array_flip(\Illuminate\Database\Eloquent\Relations\Relation::morphMap());
                $alias = $morphMap[$link->linkable_type] ?? $link->linkable_type;
                $linksByEntityAndType[$eid][$alias][] = $link->linkable_id;
            }

            $registry = resolve(EntityLinkRegistry::class);
            $metrics = $registry->computeMetricsBatch($linksByEntityAndType);

            // Merge metrics across all entities
            $mergedMetrics = [];
            foreach ($metrics as $eid => $entityMetrics) {
                foreach ($entityMetrics as $key => $value) {
                    $mergedMetrics[$key] = ($mergedMetrics[$key] ?? 0) + $value;
                }
            }

            return ToolResult::success([
                'entity_id' => $entityId,
                'entity_name' => $entity->name,
                'includes_children' => $includeChildren,
                'entity_count' => count($entityIds),
                'signals' => $signalSummary,
                'links_by_type' => $mergedCounts,
                'links_total' => $linksTotal,
                'metrics' => $mergedMetrics,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Entity-Summary: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'entity', 'summary', 'aggregation'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
