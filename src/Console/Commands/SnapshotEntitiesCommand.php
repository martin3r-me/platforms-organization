<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Services\EntityTimeResolver;
use Platform\Organization\Services\EntityHierarchyService;
use Platform\Organization\Services\PersonActivityRegistry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;

class SnapshotEntitiesCommand extends Command
{
    protected $signature = 'organization:snapshot-entities {--period=auto}';

    protected $description = 'Create snapshots of entity metrics (2x daily: morning/evening)';

    public function handle(): int
    {
        $period = $this->option('period');
        if ($period === 'auto') {
            $period = now()->hour < 13 ? 'morning' : 'evening';
        }

        $today = Carbon::today();

        $this->info("Creating {$period} snapshots for {$today->toDateString()}...");

        // 1. Load all active entities (across all teams)
        $entities = OrganizationEntity::active()->get();

        if ($entities->isEmpty()) {
            $this->info('No active entities found.');
            return self::SUCCESS;
        }

        $entityIds = $entities->pluck('id')->toArray();

        // 2. Load entity links grouped: [entityId => [morphAlias => [linkable_ids]]]
        $reverseMorphMap = array_flip(Relation::morphMap());

        $links = OrganizationEntityLink::whereIn('entity_id', $entityIds)->get();

        $linksByEntityAndType = [];
        $linkCountsByEntity = [];
        foreach ($links as $link) {
            $type = $reverseMorphMap[$link->linkable_type] ?? $link->linkable_type;
            $linksByEntityAndType[$link->entity_id][$type][] = $link->linkable_id;
            $linkCountsByEntity[$link->entity_id] = ($linkCountsByEntity[$link->entity_id] ?? 0) + 1;
        }

        // 3. Compute metrics via registry
        $registry = resolve(EntityLinkRegistry::class);
        $itemMetrics = $registry->computeMetricsBatch($linksByEntityAndType);

        // 4. Compute time summaries via EntityTimeResolver
        $resolver = new EntityTimeResolver();
        $contextPairs = $resolver->resolveContextPairsBatch($entityIds);
        $timeSummaries = $resolver->batchTimeSummaries($contextPairs);

        // 5. Compute person metrics for entities with linked_user_id
        $personMetrics = [];
        try {
            $personRegistry = resolve(PersonActivityRegistry::class);
            if ($personRegistry->hasProviders()) {
                $entitiesWithUser = $entities->filter(fn($e) => $e->linked_user_id !== null);
                foreach ($entitiesWithUser as $entity) {
                    $signs = $personRegistry->allVitalSigns($entity->linked_user_id, $entity->team_id);
                    $flat = [];
                    foreach ($signs as $sectionKey => $sectionSigns) {
                        foreach ($sectionSigns as $sign) {
                            $flat["person_{$sectionKey}_{$sign['key']}"] = $sign['value'];
                        }
                    }
                    if (!empty($flat)) {
                        $personMetrics[$entity->id] = $flat;
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Snapshot: Person metrics failed', ['error' => $e->getMessage()]);
        }

        // 6. Cascade metrics through entity hierarchy
        $hierarchyService = new EntityHierarchyService();
        $childMap = $hierarchyService->buildChildMap($entities);

        $ownMetricsMap = [];
        foreach ($entities as $entity) {
            $items = $itemMetrics[$entity->id] ?? [];
            $time = $timeSummaries[$entity->id] ?? ['total_minutes' => 0, 'billed_minutes' => 0];
            $ownMetricsMap[$entity->id] = [
                'links_count' => $linkCountsByEntity[$entity->id] ?? 0,
                'items_total' => $items['items_total'] ?? 0,
                'items_done' => $items['items_done'] ?? 0,
                'time_total_minutes' => $time['total_minutes'],
                'time_billed_minutes' => $time['billed_minutes'],
            ];
        }

        $cascadeKeys = ['links_count', 'items_total', 'items_done', 'time_total_minutes', 'time_billed_minutes'];
        $cascadedMetrics = $hierarchyService->cascadeMetrics($ownMetricsMap, $childMap, $cascadeKeys);

        // 7. Upsert snapshots
        $upsertData = [];
        foreach ($entities as $entity) {
            $metrics = $ownMetricsMap[$entity->id];

            // Merge cascaded values
            if (isset($cascadedMetrics[$entity->id])) {
                $metrics = array_merge($metrics, $cascadedMetrics[$entity->id]);
            }

            if (isset($personMetrics[$entity->id])) {
                $metrics = array_merge($metrics, $personMetrics[$entity->id]);
            }

            $upsertData[] = [
                'entity_id' => $entity->id,
                'snapshot_date' => $today->toDateString(),
                'snapshot_period' => $period,
                'metrics' => json_encode($metrics),
                'created_at' => now(),
            ];
        }

        // Batch upsert in chunks
        foreach (array_chunk($upsertData, 500) as $chunk) {
            DB::table('organization_entity_snapshots')->upsert(
                $chunk,
                ['entity_id', 'snapshot_date', 'snapshot_period'],
                ['metrics', 'created_at']
            );
        }

        $this->info("Created/updated " . count($upsertData) . " snapshots.");

        return self::SUCCESS;
    }
}
