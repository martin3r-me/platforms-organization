<?php

namespace Platform\Organization\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationEntitySnapshot;

class SnapshotMovementService
{
    public function __construct(protected EntityLinkRegistry $registry) {}

    /**
     * Movement for a single entity over N days.
     */
    public function forEntity(int $entityId, int $days = 7, ?string $group = null): MovementResult
    {
        $current = $this->getLatestSnapshotsForEntities([$entityId]);
        $previous = $this->getSnapshotsNearDate([$entityId], now()->subDays($days));

        $currentMetrics = isset($current[$entityId]) ? $current[$entityId]->metrics : [];
        $previousMetrics = isset($previous[$entityId]) ? $previous[$entityId]->metrics : [];

        return $this->computeMovement($currentMetrics, $previousMetrics, $days, $group);
    }

    /**
     * Aggregated movement across multiple entities.
     */
    public function forEntities(array $entityIds, int $days = 7, ?string $group = null): MovementResult
    {
        if (empty($entityIds)) {
            return MovementResult::empty();
        }

        $currentSnapshots = $this->getLatestSnapshotsForEntities($entityIds);
        $previousSnapshots = $this->getSnapshotsNearDate($entityIds, now()->subDays($days));

        $currentAgg = $this->aggregateMetrics($currentSnapshots);
        $previousAgg = $this->aggregateMetrics($previousSnapshots);

        return $this->computeMovement($currentAgg, $previousAgg, $days, $group);
    }

    /**
     * Time series data for charts.
     */
    public function timeSeries(int $entityId, int $days = 30, ?string $group = null): array
    {
        $snapshots = OrganizationEntitySnapshot::where('entity_id', $entityId)
            ->forDateRange(now()->subDays($days), now())
            ->orderBy('snapshot_date')
            ->get();

        $definitions = $group
            ? $this->registry->metricDefinitionsForGroup($group)
            : $this->registry->allMetricDefinitions();

        $keys = array_keys($definitions);
        $series = [];

        foreach ($snapshots as $snap) {
            $point = ['date' => $snap->snapshot_date->format('Y-m-d')];
            foreach ($keys as $key) {
                $point[$key] = $snap->metrics[$key] ?? 0;
            }
            $series[] = $point;
        }

        return $series;
    }

    protected function computeMovement(array $current, array $previous, int $days, ?string $group): MovementResult
    {
        $allDefinitions = $this->registry->allMetricDefinitions();

        // Auto-discover keys from snapshots that have no definition
        $allKeys = array_unique(array_merge(array_keys($current), array_keys($previous)));
        foreach ($allKeys as $key) {
            if (!isset($allDefinitions[$key]) && !str_ends_with($key, '_cascaded') && !str_starts_with($key, 'person_')) {
                $allDefinitions[$key] = [
                    'label' => ucfirst(str_replace('_', ' ', $key)),
                    'group' => 'other',
                    'direction' => 'neutral',
                    'unit' => 'count',
                ];
            }
        }

        // Filter by group if specified
        $definitions = $group
            ? array_filter($allDefinitions, fn (array $def) => $def['group'] === $group)
            : $allDefinitions;

        $deltas = [];
        foreach ($definitions as $key => $def) {
            $cur = $current[$key] ?? 0;
            $prev = $previous[$key] ?? 0;

            // Skip keys with no data in either snapshot
            if ($cur == 0 && $prev == 0) {
                continue;
            }

            $delta = $cur - $prev;
            $sentiment = $this->deriveSentiment($delta, $def['direction']);

            // Calculate ratio if pair defined
            $ratio = null;
            if (isset($def['pair'])) {
                $pairValue = $current[$def['pair']] ?? 0;
                $ratio = $pairValue > 0 ? round(($cur / $pairValue) * 100, 1) : null;
            }

            $deltas[$key] = new MetricDelta(
                key: $key,
                label: $def['label'],
                group: $def['group'],
                current: $cur,
                previous: $prev,
                delta: $delta,
                sentiment: $sentiment,
                unit: $def['unit'],
                ratio: $ratio,
                pairKey: $def['pair'] ?? null,
            );
        }

        // Collect available groups (only those with actual data)
        $availableGroups = [];
        foreach ($allDefinitions as $def) {
            $g = $def['group'];
            if (!isset($availableGroups[$g])) {
                $groupLabels = $this->registry->allMetricGroups();
                $availableGroups[$g] = $groupLabels[$g] ?? ucfirst($g);
            }
        }

        // Filter to groups that have at least one delta
        $activeGroups = [];
        foreach ($deltas as $d) {
            $activeGroups[$d->group] = $availableGroups[$d->group] ?? ucfirst($d->group);
        }

        return new MovementResult($deltas, $days, $group, $activeGroups);
    }

    protected function deriveSentiment(int|float $delta, string $direction): string
    {
        if ($delta == 0) {
            return 'neutral';
        }

        return match ($direction) {
            'up' => $delta > 0 ? 'positive' : 'negative',
            'down' => $delta < 0 ? 'positive' : 'negative',
            default => 'neutral',
        };
    }

    protected function aggregateMetrics(array $snapshots): array
    {
        $totals = [];
        foreach ($snapshots as $snap) {
            foreach ($snap->metrics as $key => $value) {
                $totals[$key] = ($totals[$key] ?? 0) + $value;
            }
        }

        return $totals;
    }

    /**
     * Get the latest snapshot for each entity.
     *
     * @return array<int, OrganizationEntitySnapshot>
     */
    protected function getLatestSnapshotsForEntities(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

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
     *
     * @return array<int, OrganizationEntitySnapshot>
     */
    protected function getSnapshotsNearDate(array $entityIds, Carbon $date): array
    {
        if (empty($entityIds)) {
            return [];
        }

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
}
