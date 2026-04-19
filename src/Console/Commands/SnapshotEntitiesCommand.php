<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Models\OrganizationEntityRelationship;
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
        $entities = OrganizationEntity::active()
            ->with([
                'type.group',
                'vsmSystem:id,code,name,sort_order',
                'costCenter:id,code,name',
            ])
            ->get();

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

            $ownMetricsMap[$entity->id] = $metrics;
        }

        $cascadeKeys = array_unique(array_merge($baseKeys, array_keys($allProviderKeys)));
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
        $this->createTeamStructureSnapshots($entities, $links, $today, $period);

        return self::SUCCESS;
    }

    protected function createTeamStructureSnapshots($entities, $links, $today, string $period): void
    {
        $reverseMorphMap = array_flip(Relation::morphMap());
        $entitiesByTeam = $entities->groupBy('team_id');

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
                'vsm_code' => $e->vsmSystem?->code,
                'vsm_name' => $e->vsmSystem?->name,
                'vsm_sort' => $e->vsmSystem?->sort_order,
                'cost_center_id' => $e->cost_center_id,
                'cost_center_name' => $e->costCenter?->name,
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

            // Entity links with resolved names
            $teamLinks = $links->whereIn('entity_id', $entityIds);
            $entityLinksData = [];

            // Normalize linkable_type → group by canonical morph alias
            $morphMap = Relation::morphMap() ?: [];
            $grouped = $teamLinks->groupBy(function ($link) use ($morphMap, $reverseMorphMap) {
                $type = $link->linkable_type;
                if (array_key_exists($type, $morphMap)) return $type;
                if (isset($reverseMorphMap[$type])) return $reverseMorphMap[$type];
                if (class_exists($type)) return \Illuminate\Support\Str::snake(class_basename($type));
                return $type;
            });
            foreach ($grouped as $morphAlias => $typeLinks) {
                $ids = $typeLinks->pluck('linkable_id')->unique()->values()->all();
                // Resolve model class: alias → FQCN, or use raw type if it is already a class
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

                    // Build label expression — supports name/title/.../first_name+last_name/email
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
                    $entityLinksData[] = [
                        'entity_id' => $link->entity_id,
                        'linkable_type' => $morphAlias,
                        'linkable_id' => $link->linkable_id,
                        'linkable_name' => $labelMap[$link->linkable_id] ?? "#{$link->linkable_id}",
                    ];
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
