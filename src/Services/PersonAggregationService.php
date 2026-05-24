<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Contracts\HasPersonMetrics;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationType;

class PersonAggregationService
{
    public function __construct(protected EntityLinkRegistry $registry) {}

    /**
     * Compute per-person metrics from all HasPersonMetrics providers.
     *
     * @param Collection $entities All active entities
     * @return array<int, array<string, int|float>> [entityId => ['person_active_items' => X, ...]]
     */
    public function computePersonMetrics(Collection $entities): array
    {
        $personEntities = $entities->filter(fn ($e) => $e->linked_user_id !== null);

        if ($personEntities->isEmpty()) {
            return [];
        }

        // Group person entities by team_id
        $byTeam = $personEntities->groupBy('team_id');

        $providers = $this->registry->personMetricsProviders();
        if (empty($providers)) {
            return [];
        }

        // Collect per-person raw metrics (ungeprefixed) across all providers
        // [userId => [key => value]]
        $perUser = [];

        foreach ($byTeam as $teamId => $teamEntities) {
            $userIds = $teamEntities->pluck('linked_user_id')->unique()->values()->all();

            foreach ($providers as $provider) {
                $providerMetrics = $provider->personMetrics($userIds, $teamId);

                foreach ($providerMetrics as $userId => $metrics) {
                    foreach ($metrics as $key => $value) {
                        $perUser[$userId][$key] = ($perUser[$userId][$key] ?? 0) + $value;
                    }
                }
            }
        }

        // Add time entries per user
        $allUserIds = $personEntities->pluck('linked_user_id')->unique()->values()->all();
        if (!empty($allUserIds)) {
            $timeByUser = DB::table('organization_time_entries')
                ->whereIn('user_id', $allUserIds)
                ->whereNull('deleted_at')
                ->select('user_id', DB::raw('SUM(minutes) as total_minutes'))
                ->groupBy('user_id')
                ->pluck('total_minutes', 'user_id');

            foreach ($timeByUser as $userId => $minutes) {
                $perUser[$userId]['time_total_minutes'] = (int) $minutes;
            }
        }

        // Map back to entity IDs and prefix keys with 'person_'
        $result = [];
        foreach ($personEntities as $entity) {
            $userData = $perUser[$entity->linked_user_id] ?? [];
            if (empty($userData)) {
                continue;
            }

            $prefixed = [];
            foreach ($userData as $key => $value) {
                $prefixed["person_{$key}"] = $value;
            }
            $result[$entity->id] = $prefixed;
        }

        return $result;
    }

    /**
     * Aggregate person metrics to org entities via is_active_in relationships + parent_entity_id.
     *
     * @param array<int, array<string, int|float>> $personMetrics [entityId => ['person_active_items' => X, ...]]
     * @param Collection $entities All active entities
     * @return array<int, array<string, int|float>> [orgEntityId => ['active_persons_count' => N, ...]]
     */
    public function aggregateToOrgEntities(array $personMetrics, Collection $entities): array
    {
        if (empty($personMetrics)) {
            return [];
        }

        $personEntityIds = array_keys($personMetrics);

        // Build map: orgEntityId => [(personEntityId, percentage), ...]
        $orgPersonMap = [];

        // 1. Load is_active_in relationships
        $relationType = OrganizationEntityRelationType::where('code', 'is_active_in')->first();

        if ($relationType) {
            $relationships = OrganizationEntityRelationship::where('relation_type_id', $relationType->id)
                ->whereIn('from_entity_id', $personEntityIds)
                ->get();

            foreach ($relationships as $rel) {
                $percentage = $rel->metadata['percentage'] ?? 100;
                $orgPersonMap[$rel->to_entity_id][] = [
                    'person_entity_id' => $rel->from_entity_id,
                    'percentage' => (float) $percentage,
                ];
            }
        }

        // 2. parent_entity_id fallback: person entities whose parent is an org-unit
        // Only add if not already mapped via is_active_in to that same org entity
        $personEntities = $entities->whereIn('id', $personEntityIds);
        foreach ($personEntities as $entity) {
            if (!$entity->parent_entity_id) {
                continue;
            }

            $parentId = $entity->parent_entity_id;

            // Check if already mapped to this parent via is_active_in
            $alreadyMapped = false;
            if (isset($orgPersonMap[$parentId])) {
                foreach ($orgPersonMap[$parentId] as $entry) {
                    if ($entry['person_entity_id'] === $entity->id) {
                        $alreadyMapped = true;
                        break;
                    }
                }
            }

            if (!$alreadyMapped) {
                $orgPersonMap[$parentId][] = [
                    'person_entity_id' => $entity->id,
                    'percentage' => 100.0,
                ];
            }
        }

        if (empty($orgPersonMap)) {
            return [];
        }

        // 3. Compute rollup metrics for each org entity
        $result = [];
        foreach ($orgPersonMap as $orgEntityId => $personEntries) {
            $distinctPersons = [];
            $workloadTotal = 0;
            $completedTotal = 0;
            $spTotal = 0;
            $spDone = 0;
            $timeTotal = 0;

            foreach ($personEntries as $entry) {
                $personEntityId = $entry['person_entity_id'];
                $pct = $entry['percentage'] / 100;

                $distinctPersons[$personEntityId] = true;

                $metrics = $personMetrics[$personEntityId] ?? [];
                $workloadTotal += ($metrics['person_active_items'] ?? 0) * $pct;
                $completedTotal += ($metrics['person_completed_items'] ?? 0) * $pct;
                $spTotal += ($metrics['person_story_points_total'] ?? 0) * $pct;
                $spDone += ($metrics['person_story_points_done'] ?? 0) * $pct;
                $timeTotal += ($metrics['person_time_total_minutes'] ?? 0) * $pct;
            }

            $result[$orgEntityId] = [
                'active_persons_count' => count($distinctPersons),
                'persons_workload_total' => round($workloadTotal, 2),
                'persons_completed_total' => round($completedTotal, 2),
                'persons_story_points_total' => round($spTotal, 2),
                'persons_story_points_done' => round($spDone, 2),
                'persons_time_total_minutes' => round($timeTotal, 2),
            ];
        }

        return $result;
    }
}
