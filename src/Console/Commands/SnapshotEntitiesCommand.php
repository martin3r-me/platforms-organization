<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationDimensionValue;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Services\EntityTimeResolver;
use Platform\Organization\Services\EntityHierarchyService;
use Platform\Organization\Services\PersonActivityRegistry;
use Platform\Organization\Services\PersonAggregationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;

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
        $entities = OrganizationEntity::active()
            ->with([
                'type.group',
            ])
            ->get();

        if ($entities->isEmpty()) {
            $this->info('No active entities found.');
            return self::SUCCESS;
        }

        $entityIds = $entities->pluck('id')->toArray();

        // 2. Load entity links via DimensionLinks for dimension 'entity'
        $reverseMorphMap = array_flip(Relation::morphMap());

        $entityDef = OrganizationDimensionDefinition::where('key', 'entity')->first();

        $linksByEntityAndType = [];
        $linkCountsByEntity = [];

        if ($entityDef) {
            // Build map: dimValueId → source_entity_id
            $dimValues = OrganizationDimensionValue::where('dimension_definition_id', $entityDef->id)
                ->get();

            $dimValueToEntity = [];
            foreach ($dimValues as $dv) {
                $meta = $dv->metadata;
                if (isset($meta['source_entity_id'])) {
                    $dimValueToEntity[$dv->id] = $meta['source_entity_id'];
                }
            }

            $dimValueIds = array_keys($dimValueToEntity);

            if (!empty($dimValueIds)) {
                $dimensionLinks = OrganizationDimensionLink::where('dimension_definition_id', $entityDef->id)
                    ->whereIn('dimension_value_id', $dimValueIds)
                    ->get();

                foreach ($dimensionLinks as $link) {
                    $entityId = $dimValueToEntity[$link->dimension_value_id] ?? null;
                    if (!$entityId) {
                        continue;
                    }
                    $type = $reverseMorphMap[$link->linkable_type] ?? $link->linkable_type;
                    $linksByEntityAndType[$entityId][$type][] = $link->linkable_id;
                    $linkCountsByEntity[$entityId] = ($linkCountsByEntity[$entityId] ?? 0) + 1;
                }
            }
        }

        // 3. Compute metrics via registry
        $registry = resolve(EntityLinkRegistry::class);
        $itemMetrics = $registry->computeMetricsBatch($linksByEntityAndType);

        // 3b. Compute cost-driver adjustments
        $groupLinksByEntity = $linksByEntityAndType;
        // Extract only bank account group links for cost-driver context
        $groupLinksForCostDriver = [];
        foreach ($groupLinksByEntity as $eid => $typeMap) {
            if (isset($typeMap['drip_bank_account_group'])) {
                $groupLinksForCostDriver[$eid] = $typeMap['drip_bank_account_group'];
            }
        }
        if (!empty($groupLinksForCostDriver)) {
            $costDriverAdjustments = $registry->computeCostDriverAdjustments($groupLinksForCostDriver);
            foreach ($costDriverAdjustments as $entityId => $adjustments) {
                foreach ($adjustments as $key => $value) {
                    $itemMetrics[$entityId][$key] = ($itemMetrics[$entityId][$key] ?? 0) + $value;
                }
            }
        }

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

        // 5b. Compute aggregatable person metrics via HasPersonMetrics providers
        $personAggService = new PersonAggregationService($registry);
        $computedPersonMetrics = $personAggService->computePersonMetrics($entities);

        // Merge into personMetrics (coexists with vital signs keys)
        foreach ($computedPersonMetrics as $entityId => $metrics) {
            $personMetrics[$entityId] = array_merge($personMetrics[$entityId] ?? [], $metrics);
        }

        // 5c. Compute terminal communication metrics per entity
        $terminalMetrics = [];
        try {
            $morphMap = Relation::morphMap();
            $entityMorphType = array_search(OrganizationEntity::class, $morphMap, true);
            if ($entityMorphType === false) {
                $entityMorphType = OrganizationEntity::class;
            }

            // Find all terminal channels linked to these entities via context morph
            $channelsByEntity = DB::table('terminal_channels')
                ->where('context_type', $entityMorphType)
                ->whereIn('context_id', $entityIds)
                ->select('id', 'context_id')
                ->get()
                ->groupBy('context_id');

            if ($channelsByEntity->isNotEmpty()) {
                $allChannelIds = $channelsByEntity->flatten()->pluck('id')->all();
                $now = Carbon::now();
                $sevenDaysAgo = $now->copy()->subDays(7);
                $thirtyDaysAgo = $now->copy()->subDays(30);

                // Batch query: messages count 7d per channel
                $messages7d = DB::table('terminal_messages')
                    ->whereIn('channel_id', $allChannelIds)
                    ->where('created_at', '>=', $sevenDaysAgo)
                    ->groupBy('channel_id')
                    ->select('channel_id', DB::raw('COUNT(*) as cnt'))
                    ->pluck('cnt', 'channel_id');

                // Batch query: messages count 30d per channel
                $messages30d = DB::table('terminal_messages')
                    ->whereIn('channel_id', $allChannelIds)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->groupBy('channel_id')
                    ->select('channel_id', DB::raw('COUNT(*) as cnt'))
                    ->pluck('cnt', 'channel_id');

                // Batch query: distinct participants 7d per channel
                $participants7d = DB::table('terminal_messages')
                    ->whereIn('channel_id', $allChannelIds)
                    ->where('created_at', '>=', $sevenDaysAgo)
                    ->whereNotNull('user_id')
                    ->groupBy('channel_id')
                    ->select('channel_id', DB::raw('COUNT(DISTINCT user_id) as cnt'))
                    ->pluck('cnt', 'channel_id');

                // Batch query: last message date per channel
                $lastMessageDates = DB::table('terminal_messages')
                    ->whereIn('channel_id', $allChannelIds)
                    ->groupBy('channel_id')
                    ->select('channel_id', DB::raw('MAX(created_at) as last_at'))
                    ->pluck('last_at', 'channel_id');

                // Aggregate per entity (entity may have multiple channels)
                foreach ($channelsByEntity as $entityId => $channels) {
                    $channelIds = $channels->pluck('id')->all();
                    $m7d = 0;
                    $m30d = 0;
                    $p7d = 0;
                    $lastAt = null;

                    foreach ($channelIds as $cid) {
                        $m7d += (int) ($messages7d[$cid] ?? 0);
                        $m30d += (int) ($messages30d[$cid] ?? 0);
                        $p7d += (int) ($participants7d[$cid] ?? 0);

                        $channelLast = $lastMessageDates[$cid] ?? null;
                        if ($channelLast !== null) {
                            if ($lastAt === null || $channelLast > $lastAt) {
                                $lastAt = $channelLast;
                            }
                        }
                    }

                    $daysSinceLastMessage = null;
                    if ($lastAt !== null) {
                        $daysSinceLastMessage = (int) Carbon::parse($lastAt)->diffInDays($now);
                    }

                    $terminalMetrics[$entityId] = [
                        'terminal_messages_7d' => $m7d,
                        'terminal_messages_30d' => $m30d,
                        'terminal_participants_7d' => $p7d,
                        'terminal_last_message_days' => $daysSinceLastMessage,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Snapshot: Terminal metrics failed', ['error' => $e->getMessage()]);
        }

        // 6. Cascade metrics through entity hierarchy
        $hierarchyService = new EntityHierarchyService();
        $childMap = $hierarchyService->buildChildMap($entities);

        $baseKeys = ['links_count', 'time_total_minutes', 'time_billed_minutes'];

        $ownMetricsMap = [];
        $allProviderKeys = [];

        foreach ($entities as $entity) {
            $items = $itemMetrics[$entity->id] ?? [];
            $time = $timeSummaries[$entity->id] ?? ['total_minutes' => 0, 'billed_minutes' => 0];

            $metrics = [
                'links_count' => $linkCountsByEntity[$entity->id] ?? 0,
                'time_total_minutes' => $time['total_minutes'],
                'time_billed_minutes' => $time['billed_minutes'],
            ];

            foreach ($items as $key => $value) {
                $metrics[$key] = $value;
                $allProviderKeys[$key] = true;
            }

            // Merge terminal metrics
            if (isset($terminalMetrics[$entity->id])) {
                foreach ($terminalMetrics[$entity->id] as $key => $value) {
                    $metrics[$key] = $value;
                }
            }

            $ownMetricsMap[$entity->id] = $metrics;
        }

        // 6b. Aggregate person metrics to org entities via is_active_in + parent_entity_id
        $orgPersonRollups = $personAggService->aggregateToOrgEntities($computedPersonMetrics, $entities);
        $personRollupKeys = ['active_persons_count', 'persons_workload_total', 'persons_completed_total',
            'persons_story_points_total', 'persons_story_points_done', 'persons_time_total_minutes'];

        foreach ($orgPersonRollups as $entityId => $rollups) {
            foreach ($rollups as $key => $value) {
                $ownMetricsMap[$entityId][$key] = $value;
            }
        }

        $cascadeKeys = array_unique(array_merge($baseKeys, array_keys($allProviderKeys), $personRollupKeys));
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

        $this->info("Created/updated " . count($upsertData) . " entity snapshots.");

        // 8. Structure snapshots per team
        $this->createTeamStructureSnapshots($entities, $today, $period, $entityDef);

        return self::SUCCESS;
    }

    protected function createTeamStructureSnapshots($entities, $today, string $period, ?OrganizationDimensionDefinition $entityDef): void
    {
        $reverseMorphMap = array_flip(Relation::morphMap());
        $entitiesByTeam = $entities->groupBy('team_id');

        // Build dimValueId → entityId map for structure snapshots
        $dimValueToEntity = [];
        if ($entityDef) {
            $dimValues = OrganizationDimensionValue::where('dimension_definition_id', $entityDef->id)->get();
            foreach ($dimValues as $dv) {
                $meta = $dv->metadata;
                if (isset($meta['source_entity_id'])) {
                    $dimValueToEntity[$dv->id] = $meta['source_entity_id'];
                }
            }
        }

        foreach ($entitiesByTeam as $teamId => $teamEntities) {
            $entityIds = $teamEntities->pluck('id')->toArray();

            // Entities
            $structureEntities = $teamEntities->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'code' => $e->code,
                'parent_entity_id' => $e->parent_entity_id,
                'entity_type_id' => $e->entity_type_id,
                'type_name' => $e->type?->name,
                'group_name' => $e->type?->group?->name,
                'is_active' => $e->is_active,
                'linked_user_id' => $e->linked_user_id,
            ])->values()->all();

            // Relationships
            $relationships = OrganizationEntityRelationship::query()
                ->where(function ($q) use ($entityIds) {
                    $q->whereIn('from_entity_id', $entityIds)
                      ->whereIn('to_entity_id', $entityIds);
                })
                ->with('relationType')
                ->get()
                ->map(fn($rel) => [
                    'from_entity_id' => $rel->from_entity_id,
                    'to_entity_id' => $rel->to_entity_id,
                    'relation_type_code' => $rel->relationType?->code,
                    'relation_type_name' => $rel->relationType?->name,
                ])
                ->values()
                ->all();

            // Entity links via DimensionLinks
            $entityLinksData = [];

            if ($entityDef) {
                $teamDimValueIds = [];
                foreach ($dimValueToEntity as $dvId => $eId) {
                    if (in_array($eId, $entityIds)) {
                        $teamDimValueIds[] = $dvId;
                    }
                }

                if (!empty($teamDimValueIds)) {
                    $teamDimensionLinks = OrganizationDimensionLink::where('dimension_definition_id', $entityDef->id)
                        ->whereIn('dimension_value_id', $teamDimValueIds)
                        ->get();

                    // Normalize linkable_type → group by canonical morph alias
                    $morphMap = Relation::morphMap() ?: [];
                    $grouped = $teamDimensionLinks->groupBy(function ($link) use ($morphMap, $reverseMorphMap) {
                        $type = $link->linkable_type;
                        if (array_key_exists($type, $morphMap)) return $type;
                        if (isset($reverseMorphMap[$type])) return $reverseMorphMap[$type];
                        if (class_exists($type)) return \Illuminate\Support\Str::snake(class_basename($type));
                        return $type;
                    });

                    foreach ($grouped as $morphAlias => $typeLinks) {
                        $ids = $typeLinks->pluck('linkable_id')->unique()->values()->all();
                        $modelClass = Relation::getMorphedModel($morphAlias);
                        if (!$modelClass || !class_exists($modelClass)) {
                            foreach ($typeLinks as $l) {
                                if (class_exists($l->linkable_type)) {
                                    $modelClass = $l->linkable_type;
                                    break;
                                }
                            }
                        }
                        $labelMap = [];

                        if ($modelClass && class_exists($modelClass)) {
                            $table = (new $modelClass)->getTable();
                            $columns = collect(DB::getSchemaBuilder()->getColumnListing($table));

                            $labelExpr = null;
                            foreach (['name', 'title', 'subject', 'label', 'display_name'] as $col) {
                                if ($columns->contains($col)) {
                                    $labelExpr = DB::raw("COALESCE(NULLIF({$col}, ''), CONCAT('#', id)) as label");
                                    break;
                                }
                            }
                            if (!$labelExpr && $columns->contains('first_name') && $columns->contains('last_name')) {
                                $labelExpr = DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''), CONCAT('#', id)) as label");
                            }
                            if (!$labelExpr && $columns->contains('email')) {
                                $labelExpr = DB::raw("COALESCE(NULLIF(email, ''), CONCAT('#', id)) as label");
                            }
                            if (!$labelExpr) {
                                $labelExpr = DB::raw("CONCAT('#', id) as label");
                            }

                            $query = DB::table($table)->whereIn('id', $ids);
                            if ($columns->contains('deleted_at')) {
                                $query->whereNull('deleted_at');
                            }
                            foreach ($query->select('id', $labelExpr)->get() as $row) {
                                $labelMap[$row->id] = $row->label;
                            }
                        }

                        foreach ($typeLinks as $link) {
                            $entityId = $dimValueToEntity[$link->dimension_value_id] ?? null;
                            if (!$entityId) {
                                continue;
                            }
                            $entityLinksData[] = [
                                'entity_id' => $entityId,
                                'linkable_type' => $morphAlias,
                                'linkable_id' => $link->linkable_id,
                                'linkable_name' => $labelMap[$link->linkable_id] ?? "#{$link->linkable_id}",
                            ];
                        }
                    }
                }
            }

            $structure = [
                'entities' => $structureEntities,
                'relationships' => $relationships,
                'entity_links' => $entityLinksData,
            ];

            DB::table('organization_team_snapshots')->upsert(
                [[
                    'team_id' => $teamId,
                    'snapshot_date' => $today->toDateString(),
                    'snapshot_period' => $period,
                    'structure' => json_encode($structure),
                    'created_at' => now(),
                ]],
                ['team_id', 'snapshot_date', 'snapshot_period'],
                ['structure', 'created_at']
            );
        }

        $this->info("Created/updated " . $entitiesByTeam->count() . " team structure snapshots.");
    }
}
