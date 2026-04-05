<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Services\PersonActivityRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Dashboard extends Component
{
    public function getTeamId(): ?int
    {
        $user = auth()->user();
        return $user && $user->currentTeam ? $user->currentTeam->id : null;
    }

    public function getTotalEntitiesProperty(): int
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return 0;
        }
        return (int) OrganizationEntity::forTeam($teamId)->count();
    }

    public function getActiveEntitiesProperty(): int
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return 0;
        }
        return (int) OrganizationEntity::forTeam($teamId)->active()->count();
    }

    public function getRootEntitiesProperty(): int
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return 0;
        }
        return (int) OrganizationEntity::forTeam($teamId)->whereNull('parent_entity_id')->count();
    }

    public function getLeafEntitiesProperty(): int
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return 0;
        }
        return (int) OrganizationEntity::forTeam($teamId)
            ->whereDoesntHave('children')
            ->count();
    }

    public function getRecentEntitiesProperty()
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return collect();
        }
        return OrganizationEntity::forTeam($teamId)
            ->with(['type', 'vsmSystem'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    public function getEntitiesByTypeProperty()
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return collect();
        }

        $types = OrganizationEntityType::getActiveOrdered();
        $counts = OrganizationEntity::forTeam($teamId)
            ->selectRaw('entity_type_id, COUNT(*) as aggregate_count')
            ->groupBy('entity_type_id')
            ->pluck('aggregate_count', 'entity_type_id');

        return $types->map(function ($type) use ($counts) {
            return (object) [
                'id' => $type->id,
                'name' => $type->name,
                'icon' => $type->icon,
                'count' => (int) ($counts[$type->id] ?? 0),
            ];
        });
    }

    #[Computed]
    public function teamSnapshotSummary(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return $this->emptySnapshotSummary();
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id');
        if ($entityIds->isEmpty()) {
            return $this->emptySnapshotSummary();
        }

        // Latest snapshot per entity via subquery
        $latestSnapshots = $this->getLatestSnapshotsForEntities($entityIds);
        $agoSnapshots = $this->getSnapshotsNearDate($entityIds, now()->subDays(7));

        $current = $this->aggregateSnapshotMetrics($latestSnapshots);
        $ago = $this->aggregateSnapshotMetrics($agoSnapshots);

        $completionRate = $current['items_total'] > 0
            ? round(($current['items_done'] / $current['items_total']) * 100, 1)
            : 0;
        $billingRate = $current['time_total_minutes'] > 0
            ? round(($current['time_billed_minutes'] / $current['time_total_minutes']) * 100, 1)
            : 0;

        $agoCompletionRate = $ago['items_total'] > 0
            ? round(($ago['items_done'] / $ago['items_total']) * 100, 1)
            : 0;
        $agoBillingRate = $ago['time_total_minutes'] > 0
            ? round(($ago['time_billed_minutes'] / $ago['time_total_minutes']) * 100, 1)
            : 0;

        return [
            'items_total' => $current['items_total'],
            'items_done' => $current['items_done'],
            'completion_rate' => $completionRate,
            'time_total_minutes' => $current['time_total_minutes'],
            'time_billed_minutes' => $current['time_billed_minutes'],
            'billing_rate' => $billingRate,
            'trend_completion' => round($completionRate - $agoCompletionRate, 1),
            'trend_billing' => round($billingRate - $agoBillingRate, 1),
            'has_data' => $current['items_total'] > 0 || $current['time_total_minutes'] > 0,
        ];
    }

    #[Computed]
    public function entityHealthOverview(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return ['counts' => ['progressing' => 0, 'completed' => 0, 'stalled' => 0, 'at_risk' => 0], 'problems' => []];
        }

        $entities = OrganizationEntity::forTeam($teamId)->with('type')->get();
        if ($entities->isEmpty()) {
            return ['counts' => ['progressing' => 0, 'completed' => 0, 'stalled' => 0, 'at_risk' => 0], 'problems' => []];
        }

        $entityIds = $entities->pluck('id');
        $latestSnapshots = $this->getLatestSnapshotsForEntities($entityIds);
        $agoSnapshots = $this->getSnapshotsNearDate($entityIds, now()->subDays(7));

        $counts = ['progressing' => 0, 'completed' => 0, 'stalled' => 0, 'at_risk' => 0];
        $problems = [];

        foreach ($entities as $entity) {
            $current = $latestSnapshots[$entity->id] ?? null;
            $ago = $agoSnapshots[$entity->id] ?? null;

            if (!$current) {
                continue;
            }

            $currentMetrics = $current->metrics;
            $agoMetrics = $ago ? $ago->metrics : null;

            $itemsTotal = $currentMetrics['items_total_cascaded'] ?? $currentMetrics['items_total'] ?? 0;
            $itemsDone = $currentMetrics['items_done_cascaded'] ?? $currentMetrics['items_done'] ?? 0;
            $agoItemsDone = $agoMetrics ? ($agoMetrics['items_done_cascaded'] ?? $agoMetrics['items_done'] ?? 0) : 0;
            $agoItemsTotal = $agoMetrics ? ($agoMetrics['items_total_cascaded'] ?? $agoMetrics['items_total'] ?? 0) : 0;

            $status = $this->classifyEntityHealth($itemsTotal, $itemsDone, $agoItemsTotal, $agoItemsDone);
            $counts[$status]++;

            if (in_array($status, ['stalled', 'at_risk'])) {
                $completionPct = $itemsTotal > 0 ? round(($itemsDone / $itemsTotal) * 100) : 0;
                $problems[] = [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'type_name' => $entity->type->name ?? '',
                    'status' => $status,
                    'items_total' => $itemsTotal,
                    'items_done' => $itemsDone,
                    'completion_pct' => $completionPct,
                    'open_items' => $itemsTotal - $itemsDone,
                ];
            }
        }

        // Sort problems: at_risk first, then stalled, limit to 5
        usort($problems, fn($a, $b) => ($a['status'] === 'at_risk' ? 0 : 1) <=> ($b['status'] === 'at_risk' ? 0 : 1));
        $problems = array_slice($problems, 0, 5);

        return ['counts' => $counts, 'problems' => $problems];
    }

    #[Computed]
    public function teamTimeAnalytics(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return $this->emptyTimeAnalytics();
        }

        $now = Carbon::now();
        $thisMonthStart = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        $thisMonth = OrganizationTimeEntry::where('team_id', $teamId)
            ->where('work_date', '>=', $thisMonthStart)
            ->selectRaw('COALESCE(SUM(minutes), 0) as total_minutes')
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billed = 1 THEN minutes ELSE 0 END), 0) as billed_minutes')
            ->first();

        $lastMonth = OrganizationTimeEntry::where('team_id', $teamId)
            ->whereBetween('work_date', [$lastMonthStart, $lastMonthEnd])
            ->selectRaw('COALESCE(SUM(minutes), 0) as total_minutes')
            ->first();

        $thisTotal = (int) ($thisMonth->total_minutes ?? 0);
        $thisBilled = (int) ($thisMonth->billed_minutes ?? 0);
        $lastTotal = (int) ($lastMonth->total_minutes ?? 0);

        $thisHours = round($thisTotal / 60, 1);
        $lastHours = round($lastTotal / 60, 1);
        $billingRate = $thisTotal > 0 ? round(($thisBilled / $thisTotal) * 100, 1) : 0;

        // Last month billing rate for trend
        $lastMonthBilled = OrganizationTimeEntry::where('team_id', $teamId)
            ->whereBetween('work_date', [$lastMonthStart, $lastMonthEnd])
            ->selectRaw('COALESCE(SUM(CASE WHEN is_billed = 1 THEN minutes ELSE 0 END), 0) as billed_minutes')
            ->value('billed_minutes') ?? 0;
        $lastBillingRate = $lastTotal > 0 ? round(($lastMonthBilled / $lastTotal) * 100, 1) : 0;

        return [
            'hours_this_month' => $thisHours,
            'hours_last_month' => $lastHours,
            'trend_hours' => $lastHours > 0 ? round($thisHours - $lastHours, 1) : 0,
            'billed_minutes' => $thisBilled,
            'open_minutes' => $thisTotal - $thisBilled,
            'billing_rate' => $billingRate,
            'trend_billing' => round($billingRate - $lastBillingRate, 1),
            'has_data' => $thisTotal > 0 || $lastTotal > 0,
        ];
    }

    #[Computed]
    public function completionVelocity(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return ['weekly_rates' => [], 'avg_per_week' => 0, 'trend' => 'stable'];
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id');
        if ($entityIds->isEmpty()) {
            return ['weekly_rates' => [], 'avg_per_week' => 0, 'trend' => 'stable'];
        }

        // Get weekly aggregated snapshots for 4 weeks
        $weeklyRates = [];
        for ($w = 4; $w >= 1; $w--) {
            $weekEnd = now()->subWeeks($w - 1);
            $weekStart = now()->subWeeks($w);

            $endSnapshots = $this->getSnapshotsNearDate($entityIds, $weekEnd);
            $startSnapshots = $this->getSnapshotsNearDate($entityIds, $weekStart);

            $endDone = 0;
            $startDone = 0;
            foreach ($entityIds as $id) {
                $endDone += ($endSnapshots[$id] ?? null) ? ($endSnapshots[$id]->metrics['items_done'] ?? 0) : 0;
                $startDone += ($startSnapshots[$id] ?? null) ? ($startSnapshots[$id]->metrics['items_done'] ?? 0) : 0;
            }

            $weeklyRates[] = max(0, $endDone - $startDone);
        }

        $avg = count($weeklyRates) > 0 ? round(array_sum($weeklyRates) / count($weeklyRates), 1) : 0;

        // Trend: compare last 2 weeks vs. first 2 weeks
        $recent = ($weeklyRates[2] ?? 0) + ($weeklyRates[3] ?? 0);
        $earlier = ($weeklyRates[0] ?? 0) + ($weeklyRates[1] ?? 0);
        $trend = 'stable';
        if ($recent > $earlier * 1.2) {
            $trend = 'accelerating';
        } elseif ($recent < $earlier * 0.8) {
            $trend = 'decelerating';
        }

        return [
            'weekly_rates' => $weeklyRates,
            'avg_per_week' => $avg,
            'trend' => $trend,
        ];
    }

    #[Computed]
    public function teamSnapshotTrend(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return [];
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id');
        if ($entityIds->isEmpty()) {
            return [];
        }

        // Aggregate snapshots per day across all entities
        $rows = OrganizationEntitySnapshot::whereIn('entity_id', $entityIds)
            ->forDateRange(now()->subDays(14), now())
            ->select('snapshot_date')
            ->selectRaw('SUM(JSON_EXTRACT(metrics, "$.items_total")) as items_total')
            ->selectRaw('SUM(JSON_EXTRACT(metrics, "$.items_done")) as items_done')
            ->selectRaw('SUM(JSON_EXTRACT(metrics, "$.time_total_minutes")) as time_total_minutes')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $maxItemsTotal = 0;
        $maxMinutes = 0;
        $data = [];

        foreach ($rows as $row) {
            $itemsTotal = (int) ($row->items_total ?? 0);
            $itemsDone = (int) ($row->items_done ?? 0);
            $totalMin = (int) ($row->time_total_minutes ?? 0);

            if ($itemsTotal > $maxItemsTotal) $maxItemsTotal = $itemsTotal;
            if ($totalMin > $maxMinutes) $maxMinutes = $totalMin;

            $data[] = [
                'date' => Carbon::parse($row->snapshot_date)->format('d.m.'),
                'items_total' => $itemsTotal,
                'items_done' => $itemsDone,
                'time_total_minutes' => $totalMin,
            ];
        }

        return [
            'snapshots' => $data,
            'max_items_total' => $maxItemsTotal,
            'max_minutes' => $maxMinutes,
        ];
    }

    #[Computed]
    public function linkTypeDistribution(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return [];
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id');
        if ($entityIds->isEmpty()) {
            return [];
        }

        $reverseMorphMap = array_flip(Relation::morphMap());

        $rows = OrganizationEntityLink::whereIn('entity_id', $entityIds)
            ->select('linkable_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('linkable_type')
            ->orderByDesc('cnt')
            ->get();

        $labels = resolve(EntityLinkRegistry::class)->activityTypeLabels();
        $config = resolve(EntityLinkRegistry::class)->allLinkTypeConfig();
        $totalLinks = $rows->sum('cnt');

        $result = [];
        foreach ($rows as $row) {
            $type = $reverseMorphMap[$row->linkable_type] ?? $row->linkable_type;
            $typeConfig = $config[$type] ?? null;

            $result[] = [
                'type' => $type,
                'label' => $typeConfig['label'] ?? $labels[$type] ?? $type,
                'icon' => $typeConfig['icon'] ?? 'link',
                'count' => (int) $row->cnt,
                'percentage' => $totalLinks > 0 ? round(($row->cnt / $totalLinks) * 100, 1) : 0,
            ];
        }

        return $result;
    }

    #[Computed]
    public function topEntitiesByActivity(): array
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return [];
        }

        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id');
        if ($entityIds->isEmpty()) {
            return [];
        }

        $latestSnapshots = $this->getLatestSnapshotsForEntities($entityIds);
        $agoSnapshots = $this->getSnapshotsNearDate($entityIds, now()->subDays(7));

        $entities = OrganizationEntity::forTeam($teamId)->with('type')->get()->keyBy('id');
        $activities = [];

        foreach ($entityIds as $id) {
            $current = $latestSnapshots[$id] ?? null;
            $ago = $agoSnapshots[$id] ?? null;

            if (!$current) continue;

            $cm = $current->metrics;
            $am = $ago ? $ago->metrics : [];

            $itemsCompleted7d = max(0,
                ($cm['items_done_cascaded'] ?? $cm['items_done'] ?? 0)
                - ($am['items_done_cascaded'] ?? $am['items_done'] ?? 0)
            );
            $timeLogged7d = max(0,
                ($cm['time_total_minutes_cascaded'] ?? $cm['time_total_minutes'] ?? 0)
                - ($am['time_total_minutes_cascaded'] ?? $am['time_total_minutes'] ?? 0)
            );
            $activityScore = $itemsCompleted7d + ($timeLogged7d / 60); // weight hours equally to items

            if ($activityScore <= 0) continue;

            $entity = $entities[$id] ?? null;
            if (!$entity) continue;

            $activities[] = [
                'id' => $id,
                'name' => $entity->name,
                'type_name' => $entity->type->name ?? '',
                'items_completed_7d' => $itemsCompleted7d,
                'hours_7d' => round($timeLogged7d / 60, 1),
            ];
        }

        // Sort by activity score (items + hours) descending
        usort($activities, fn($a, $b) => ($b['items_completed_7d'] + $b['hours_7d']) <=> ($a['items_completed_7d'] + $a['hours_7d']));

        return array_slice($activities, 0, 5);
    }

    #[Computed]
    public function personOverview(): array
    {
        $empty = ['persons' => [], 'totals' => [], 'metric_configs' => [], 'person_count' => 0];

        $teamId = $this->getTeamId();
        if (!$teamId) {
            return $empty;
        }

        $registry = resolve(PersonActivityRegistry::class);
        if (!$registry->hasProviders()) {
            return $empty;
        }

        $metricConfigs = $registry->allMetricConfigs();
        if (empty($metricConfigs)) {
            return $empty;
        }

        $personEntities = OrganizationEntity::forTeam($teamId)
            ->whereNotNull('linked_user_id')
            ->with('type')
            ->get();

        if ($personEntities->isEmpty()) {
            return $empty;
        }

        $entityIds = $personEntities->pluck('id');
        $latestSnapshots = $this->getLatestSnapshotsForEntities($entityIds);

        // Only consider metrics with type warning or danger for filtering/sorting
        $relevantKeys = array_keys(array_filter($metricConfigs, fn($c) => in_array($c['type'], ['warning', 'danger'])));

        $persons = [];
        $totals = array_fill_keys(array_keys($metricConfigs), 0);

        foreach ($personEntities as $entity) {
            $snap = $latestSnapshots[$entity->id] ?? null;
            if (!$snap) continue;

            $snapshotMetrics = $snap->metrics;

            // Extract all person_* metrics from snapshot
            $personMetrics = [];
            $hasRelevant = false;
            foreach ($metricConfigs as $snapshotKey => $config) {
                $value = $snapshotMetrics[$snapshotKey] ?? 0;
                $personMetrics[$snapshotKey] = $value;
                $totals[$snapshotKey] += $value;
                if ($value > 0 && in_array($snapshotKey, $relevantKeys)) {
                    $hasRelevant = true;
                }
            }

            if (!$hasRelevant) continue;

            // Compute sort score: weighted sum of relevant metrics
            $sortScore = 0;
            foreach ($metricConfigs as $snapshotKey => $config) {
                $sortScore += ($personMetrics[$snapshotKey] ?? 0) * ($config['sort_weight'] ?? 0);
            }

            $persons[] = [
                'id' => $entity->id,
                'name' => $entity->name,
                'type_name' => $entity->type->name ?? '',
                'metrics' => $personMetrics,
                'sort_score' => $sortScore,
            ];
        }

        usort($persons, fn($a, $b) => $b['sort_score'] <=> $a['sort_score']);

        return [
            'persons' => array_slice($persons, 0, 8),
            'totals' => $totals,
            'metric_configs' => $metricConfigs,
            'person_count' => count($persons),
        ];
    }

    #[Computed]
    public function insightStatements(): array
    {
        $statements = [];
        $summary = $this->teamSnapshotSummary;
        $health = $this->entityHealthOverview;
        $time = $this->teamTimeAnalytics;
        $velocity = $this->completionVelocity;

        // Completion insight
        if ($summary['has_data'] && $summary['items_total'] > 0) {
            $text = "{$summary['completion_rate']}% aller Items abgeschlossen";
            if ($summary['trend_completion'] > 0) {
                $text .= " — {$summary['trend_completion']}% mehr als vor einer Woche.";
                $statements[] = ['text' => $text, 'type' => 'success'];
            } elseif ($summary['trend_completion'] < 0) {
                $text .= " — " . abs($summary['trend_completion']) . "% weniger als vor einer Woche.";
                $statements[] = ['text' => $text, 'type' => 'warning'];
            } else {
                $text .= ".";
                $statements[] = ['text' => $text, 'type' => 'info'];
            }
        }

        // Stalled entities
        $stalledCount = $health['counts']['stalled'] + $health['counts']['at_risk'];
        if ($stalledCount > 0) {
            $statements[] = [
                'text' => "{$stalledCount} " . ($stalledCount === 1 ? 'Einheit' : 'Einheiten') . " ohne Fortschritt seit 7 Tagen.",
                'type' => 'warning',
            ];
        }

        // Velocity
        if ($velocity['avg_per_week'] > 0) {
            $trendLabel = match ($velocity['trend']) {
                'accelerating' => ' (beschleunigend)',
                'decelerating' => ' (verlangsamend)',
                default => '',
            };
            $statements[] = [
                'text' => "Wöchentliche Erledigungsrate: Ø {$velocity['avg_per_week']} Items{$trendLabel}.",
                'type' => $velocity['trend'] === 'decelerating' ? 'warning' : 'info',
            ];
        }

        // Billing
        if ($time['has_data'] && $time['billing_rate'] > 0) {
            $text = "Abrechnungsquote bei {$time['billing_rate']}%";
            if ($time['trend_billing'] < 0) {
                $text .= " — " . abs($time['trend_billing']) . "% unter Vormonat.";
                $statements[] = ['text' => $text, 'type' => 'warning'];
            } elseif ($time['trend_billing'] > 0) {
                $text .= " — {$time['trend_billing']}% über Vormonat.";
                $statements[] = ['text' => $text, 'type' => 'success'];
            } else {
                $text .= ".";
                $statements[] = ['text' => $text, 'type' => 'info'];
            }
        }

        // Person danger metrics (generic)
        $personData = $this->personOverview;
        if ($personData['person_count'] > 0) {
            foreach ($personData['metric_configs'] as $snapshotKey => $config) {
                if ($config['type'] !== 'danger') continue;
                $total = $personData['totals'][$snapshotKey] ?? 0;
                if ($total <= 0) continue;
                $personCount = $personData['person_count'];
                $statements[] = [
                    'text' => "{$total}x {$config['label']} bei {$personCount} " . ($personCount === 1 ? 'Person' : 'Personen') . ".",
                    'type' => 'warning',
                ];
            }
        }

        return array_slice($statements, 0, 5);
    }

    // --- Helper Methods ---

    protected function emptySnapshotSummary(): array
    {
        return [
            'items_total' => 0,
            'items_done' => 0,
            'completion_rate' => 0,
            'time_total_minutes' => 0,
            'time_billed_minutes' => 0,
            'billing_rate' => 0,
            'trend_completion' => 0,
            'trend_billing' => 0,
            'has_data' => false,
        ];
    }

    protected function emptyTimeAnalytics(): array
    {
        return [
            'hours_this_month' => 0,
            'hours_last_month' => 0,
            'trend_hours' => 0,
            'billed_minutes' => 0,
            'open_minutes' => 0,
            'billing_rate' => 0,
            'trend_billing' => 0,
            'has_data' => false,
        ];
    }

    /**
     * Get the latest snapshot for each entity.
     * Returns: [entity_id => OrganizationEntitySnapshot]
     */
    protected function getLatestSnapshotsForEntities($entityIds): array
    {
        // Subquery for max snapshot_date per entity
        $latestDates = OrganizationEntitySnapshot::whereIn('entity_id', $entityIds)
            ->select('entity_id', DB::raw('MAX(snapshot_date) as max_date'))
            ->groupBy('entity_id');

        $snapshots = OrganizationEntitySnapshot::joinSub($latestDates, 'latest', function ($join) {
            $join->on('organization_entity_snapshots.entity_id', '=', 'latest.entity_id')
                 ->on('organization_entity_snapshots.snapshot_date', '=', 'latest.max_date');
        })->get();

        return $snapshots->keyBy('entity_id')->all();
    }

    /**
     * Get the snapshot closest to a given date for each entity.
     * Returns: [entity_id => OrganizationEntitySnapshot]
     */
    protected function getSnapshotsNearDate($entityIds, Carbon $date): array
    {
        // Get snapshot on or just before the target date
        $nearDates = OrganizationEntitySnapshot::whereIn('entity_id', $entityIds)
            ->where('snapshot_date', '<=', $date->toDateString())
            ->select('entity_id', DB::raw('MAX(snapshot_date) as max_date'))
            ->groupBy('entity_id');

        $snapshots = OrganizationEntitySnapshot::joinSub($nearDates, 'near', function ($join) {
            $join->on('organization_entity_snapshots.entity_id', '=', 'near.entity_id')
                 ->on('organization_entity_snapshots.snapshot_date', '=', 'near.max_date');
        })->get();

        return $snapshots->keyBy('entity_id')->all();
    }

    protected function aggregateSnapshotMetrics(array $snapshots): array
    {
        $totals = ['items_total' => 0, 'items_done' => 0, 'time_total_minutes' => 0, 'time_billed_minutes' => 0];

        foreach ($snapshots as $snap) {
            $m = $snap->metrics;
            $totals['items_total'] += $m['items_total'] ?? 0;
            $totals['items_done'] += $m['items_done'] ?? 0;
            $totals['time_total_minutes'] += $m['time_total_minutes'] ?? 0;
            $totals['time_billed_minutes'] += $m['time_billed_minutes'] ?? 0;
        }

        return $totals;
    }

    protected function classifyEntityHealth(int $itemsTotal, int $itemsDone, int $agoItemsTotal, int $agoItemsDone): string
    {
        // Completed: all done and has items
        if ($itemsDone >= $itemsTotal && $itemsTotal > 0) {
            return 'completed';
        }

        // At risk: total grew but done did not
        if ($itemsTotal > $agoItemsTotal && $itemsDone <= $agoItemsDone && $itemsTotal > 0) {
            return 'at_risk';
        }

        // Stalled: done unchanged, open items exist
        if ($itemsDone <= $agoItemsDone && ($itemsTotal - $itemsDone) > 0) {
            return 'stalled';
        }

        // Progressing: done increased
        if ($itemsDone > $agoItemsDone) {
            return 'progressing';
        }

        return 'progressing'; // default for entities without prior data
    }

    public function render()
    {
        return view('organization::livewire.dashboard')
            ->layout('platform::layouts.app');
    }
}
