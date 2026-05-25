<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Illuminate\Support\Facades\DB;

class DimensionRadarService
{
    protected static ?array $teamMaximaCache = null;
    protected static ?int $teamMaximaCacheTeamId = null;

    public function __construct(protected EntityLinkRegistry $registry) {}

    /**
     * Berechnet Radar-Scores für eine Entity im Team-Kontext.
     *
     * @return array<string, array{score: float, label: string, type: string, delta: float, metrics: array}>
     */
    public function computeRadar(int $entityId, int $teamId): array
    {
        $dimensions = EntityLinkRegistry::allDimensions();
        $allDefs = $this->registry->allMetricDefinitions();

        // Load current + 7d-ago snapshots for this entity
        $current = $this->getLatestSnapshot($entityId);
        $previous = $this->getSnapshotNearDate($entityId, now()->subDays(7));

        $currentMetrics = $current?->metrics ?? [];
        $previousMetrics = $previous?->metrics ?? [];

        $maxima = $this->teamMaxima($teamId);

        $radar = [];
        foreach ($dimensions as $dimKey => $dimConfig) {
            $rawValue = $this->dimensionRawValue($dimKey, $currentMetrics, $allDefs);
            $rawPrevious = $this->dimensionRawValue($dimKey, $previousMetrics, $allDefs);
            $max = $maxima[$dimKey] ?? 0;

            $score = ($max > 0) ? round(($rawValue / $max) * 100, 1) : 0;
            $delta = $rawValue - $rawPrevious;

            // Collect contributing metrics for tooltip
            $contributingMetrics = [];
            $dimDefs = array_filter($allDefs, fn ($def) => ($def['dimension'] ?? null) === $dimKey);
            foreach ($dimDefs as $metricKey => $def) {
                $val = $this->resolveMetricValue($metricKey, $def, $currentMetrics);
                if ($val == 0) continue;
                $contributingMetrics[] = [
                    'key' => $metricKey,
                    'label' => $def['label'],
                    'value' => $val,
                    'unit' => $def['unit'],
                    'formatted' => $this->formatMetricValue($val, $def['unit']),
                ];
            }

            // Sort contributing metrics by value descending
            usort($contributingMetrics, fn ($a, $b) => $b['value'] <=> $a['value']);

            $radar[$dimKey] = [
                'score' => $score,
                'raw' => $rawValue,
                'label' => $dimConfig['label'],
                'type' => $dimConfig['type'],
                'delta' => round($delta, 2),
                'has_data' => $rawValue > 0 || $rawPrevious > 0,
                'metrics' => array_slice($contributingMetrics, 0, 3),
            ];
        }

        return $radar;
    }

    /**
     * Team-Maxima für Normalisierung (cached per request).
     *
     * @return array<string, float> [dimension => max_raw_value]
     */
    protected function teamMaxima(int $teamId): array
    {
        if (static::$teamMaximaCacheTeamId === $teamId && static::$teamMaximaCache !== null) {
            return static::$teamMaximaCache;
        }

        $dimensions = EntityLinkRegistry::allDimensions();
        $allDefs = $this->registry->allMetricDefinitions();

        // Load latest snapshots for all active entities in the team
        $entityIds = OrganizationEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        if (empty($entityIds)) {
            static::$teamMaximaCache = array_fill_keys(array_keys($dimensions), 0);
            static::$teamMaximaCacheTeamId = $teamId;
            return static::$teamMaximaCache;
        }

        $snapshots = $this->getLatestSnapshotsForEntities($entityIds);

        $maxima = array_fill_keys(array_keys($dimensions), 0);

        foreach ($snapshots as $snapshot) {
            $metrics = $snapshot->metrics ?? [];
            foreach ($dimensions as $dimKey => $dimConfig) {
                $raw = $this->dimensionRawValue($dimKey, $metrics, $allDefs);
                if ($raw > $maxima[$dimKey]) {
                    $maxima[$dimKey] = $raw;
                }
            }
        }

        static::$teamMaximaCache = $maxima;
        static::$teamMaximaCacheTeamId = $teamId;

        return $maxima;
    }

    /**
     * Raw-Wert einer Dimension aus Snapshot-Metrics.
     * Summiert alle Metriken der Dimension (einheitlich normalisiert).
     */
    protected function dimensionRawValue(string $dimension, array $metrics, array $allDefs): float
    {
        $dimDefs = array_filter($allDefs, fn ($def) => ($def['dimension'] ?? null) === $dimension);

        $total = 0;
        foreach ($dimDefs as $key => $def) {
            $value = $this->resolveMetricValue($key, $def, $metrics);
            $total += $this->normalizeToUnit($value, $def['unit']);
        }

        return $total;
    }

    /**
     * Resolve the correct metric value considering aggregation_mode.
     */
    protected function resolveMetricValue(string $key, array $def, array $metrics): float
    {
        $mode = $def['aggregation_mode'] ?? 'own';

        if ($mode === 'rolled_up') {
            $cascadedKey = $key . '_cascaded';
            return (float) ($metrics[$cascadedKey] ?? $metrics[$key] ?? 0);
        }

        return (float) ($metrics[$key] ?? 0);
    }

    /**
     * Normalize metric values to a common scale per unit type.
     * minutes → hours, currency → k€, count/points/percentage stay as-is.
     */
    protected function normalizeToUnit(float $value, string $unit): float
    {
        return match ($unit) {
            'minutes' => $value / 60,       // → hours
            'currency' => $value / 1000,    // → k€
            default => $value,              // count, points, percentage, score
        };
    }

    protected function formatMetricValue(float $value, string $unit): string
    {
        return match ($unit) {
            'minutes' => round($value / 60, 1) . 'h',
            'percentage' => $value . '%',
            'currency' => number_format($value, 0, ',', '.') . ' €',
            'points' => number_format($value, 0, ',', '.'),
            default => (string) round($value, 1),
        };
    }

    protected function getLatestSnapshot(int $entityId): ?OrganizationEntitySnapshot
    {
        return OrganizationEntitySnapshot::where('entity_id', $entityId)
            ->orderByDesc('snapshot_date')
            ->orderByRaw("FIELD(snapshot_period, 'evening', 'morning') ASC")
            ->first();
    }

    protected function getSnapshotNearDate(int $entityId, $date): ?OrganizationEntitySnapshot
    {
        return OrganizationEntitySnapshot::where('entity_id', $entityId)
            ->where('snapshot_date', '<=', $date->toDateString())
            ->orderByDesc('snapshot_date')
            ->orderByRaw("FIELD(snapshot_period, 'evening', 'morning') ASC")
            ->first();
    }

    /**
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
        })
            ->orderByRaw("FIELD(snapshot_period, 'morning', 'evening') ASC")
            ->get();

        return $snapshots->keyBy('entity_id')->all();
    }
}
