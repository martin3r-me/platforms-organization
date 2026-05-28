<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalDefinition;

class SignalEvaluationService
{
    /**
     * Evaluate all active signal definitions for a team.
     *
     * @return Collection<OrganizationSignal> Newly created signals
     */
    public function evaluateForTeam(int $teamId): Collection
    {
        $definitions = OrganizationSignalDefinition::forTeam($teamId)
            ->active()
            ->get();

        $created = collect();

        foreach ($definitions as $definition) {
            $signals = $this->evaluateDefinition($definition);
            $created = $created->merge($signals);
        }

        return $created;
    }

    /**
     * Evaluate a single signal definition against current snapshots.
     *
     * @return Collection<OrganizationSignal> Newly created signals
     */
    public function evaluateDefinition(OrganizationSignalDefinition $def): Collection
    {
        $entityIds = $this->resolveScope($def);

        if (empty($entityIds)) {
            return collect();
        }

        $triggeredEntities = match ($def->pattern_type) {
            'threshold' => $this->evaluateThreshold($def->conditions, $entityIds),
            'trend' => $this->evaluateTrend($def->conditions, $entityIds),
            'cross_dimension' => $this->evaluateCrossDimension($def->conditions, $entityIds),
            'ratio' => $this->evaluateRatio($def->conditions, $entityIds),
            default => [],
        };

        // Auto-resolve stale signals (entities that no longer match)
        $triggeringEntityIds = array_column($triggeredEntities, 'entity_id');
        $this->autoResolveStaleSignals($def, $triggeringEntityIds);

        // Create new signals (skip duplicates)
        $created = collect();

        foreach ($triggeredEntities as $triggered) {
            // Duplicate check: skip if open signal already exists for this definition + entity
            $exists = OrganizationSignal::where('signal_definition_id', $def->id)
                ->where('entity_id', $triggered['entity_id'])
                ->open()
                ->exists();

            if ($exists) {
                continue;
            }

            $signal = OrganizationSignal::create([
                'team_id' => $def->team_id,
                'signal_definition_id' => $def->id,
                'entity_id' => $triggered['entity_id'],
                'status' => 'open',
                'severity' => $def->severity,
                'message' => $triggered['message'],
                'trigger_metrics' => $triggered['metrics'],
            ]);

            $created->push($signal);
        }

        return $created;
    }

    /**
     * Resolve entity IDs based on definition scope.
     */
    protected function resolveScope(OrganizationSignalDefinition $def): array
    {
        $teamId = $def->team_id;

        return match ($def->scope_type) {
            'all' => OrganizationEntity::forTeam($teamId)->active()->pluck('id')->all(),

            'entity_type' => OrganizationEntity::forTeam($teamId)
                ->active()
                ->whereHas('type', function ($q) use ($def) {
                    $codes = $def->scope_value ?? [];
                    $q->whereIn('code', (array) $codes);
                })
                ->pluck('id')
                ->all(),

            'entity_ids' => OrganizationEntity::forTeam($teamId)
                ->active()
                ->whereIn('id', $def->scope_value ?? [])
                ->pluck('id')
                ->all(),

            'subtree' => $this->resolveSubtree($teamId, $def->scope_value),

            default => [],
        };
    }

    /**
     * Resolve subtree: root entity + all descendants.
     */
    protected function resolveSubtree(int $teamId, ?array $scopeValue): array
    {
        $rootId = $scopeValue[0] ?? null;
        if (! $rootId) {
            return [];
        }

        $ids = [$rootId];
        $queue = [$rootId];

        while (! empty($queue)) {
            $childIds = OrganizationEntity::forTeam($teamId)
                ->active()
                ->whereIn('parent_entity_id', $queue)
                ->pluck('id')
                ->all();

            $ids = array_merge($ids, $childIds);
            $queue = $childIds;
        }

        return array_unique($ids);
    }

    /**
     * Threshold evaluation: compare a single metric against a value.
     * conditions: {"metric": "items_total", "operator": ">", "value": 100}
     */
    protected function evaluateThreshold(array $conditions, array $entityIds): array
    {
        $metric = $conditions['metric'] ?? null;
        $operator = $conditions['operator'] ?? '>';
        $threshold = $conditions['value'] ?? 0;

        if (! $metric) {
            return [];
        }

        $snapshots = $this->getLatestSnapshots($entityIds);
        $entities = $this->getEntityNames($entityIds);
        $triggered = [];

        foreach ($snapshots as $entityId => $snapshot) {
            $value = $snapshot->metrics[$metric] ?? 0;

            $matches = match ($operator) {
                '>' => $value > $threshold,
                '>=' => $value >= $threshold,
                '<' => $value < $threshold,
                '<=' => $value <= $threshold,
                '==' => $value == $threshold,
                '!=' => $value != $threshold,
                default => false,
            };

            if ($matches) {
                $entityName = $entities[$entityId] ?? "Entity #{$entityId}";
                $triggered[] = [
                    'entity_id' => $entityId,
                    'message' => "{$entityName}: {$metric} = {$value} ({$operator} {$threshold})",
                    'metrics' => [$metric => $value],
                ];
            }
        }

        return $triggered;
    }

    /**
     * Trend evaluation: check if metric moves in a direction over N periods.
     * conditions: {"metric": "time_total_minutes", "direction": "decreasing", "periods": 3, "min_change_percent": 10}
     */
    protected function evaluateTrend(array $conditions, array $entityIds): array
    {
        $metric = $conditions['metric'] ?? null;
        $direction = $conditions['direction'] ?? 'increasing'; // increasing or decreasing
        $periods = $conditions['periods'] ?? 3;
        $minChangePercent = $conditions['min_change_percent'] ?? 10;

        if (! $metric) {
            return [];
        }

        $entities = $this->getEntityNames($entityIds);
        $triggered = [];

        // Get snapshots over the last N*2 days to find enough data points
        $snapshots = OrganizationEntitySnapshot::whereIn('entity_id', $entityIds)
            ->where('snapshot_date', '>=', now()->subDays($periods * 2 + 1))
            ->orderBy('snapshot_date')
            ->get()
            ->groupBy('entity_id');

        foreach ($entityIds as $entityId) {
            $entitySnapshots = $snapshots[$entityId] ?? collect();

            if ($entitySnapshots->count() < $periods) {
                continue;
            }

            // Take the last N snapshots (unique by date, prefer latest)
            $recent = $entitySnapshots->unique('snapshot_date')->sortByDesc('snapshot_date')->take($periods)->reverse()->values();

            if ($recent->count() < $periods) {
                continue;
            }

            // Check if all consecutive changes go in the expected direction
            $consistent = true;
            $totalChange = 0;
            $firstValue = $recent->first()->metrics[$metric] ?? 0;

            for ($i = 1; $i < $recent->count(); $i++) {
                $prev = $recent[$i - 1]->metrics[$metric] ?? 0;
                $curr = $recent[$i]->metrics[$metric] ?? 0;
                $diff = $curr - $prev;

                if ($direction === 'increasing' && $diff <= 0) {
                    $consistent = false;
                    break;
                }
                if ($direction === 'decreasing' && $diff >= 0) {
                    $consistent = false;
                    break;
                }
            }

            if (! $consistent) {
                continue;
            }

            $lastValue = $recent->last()->metrics[$metric] ?? 0;
            $changePercent = $firstValue != 0
                ? abs(($lastValue - $firstValue) / $firstValue) * 100
                : ($lastValue != 0 ? 100 : 0);

            if ($changePercent < $minChangePercent) {
                continue;
            }

            $entityName = $entities[$entityId] ?? "Entity #{$entityId}";
            $dirLabel = $direction === 'increasing' ? 'steigend' : 'fallend';
            $triggered[] = [
                'entity_id' => $entityId,
                'message' => "{$entityName}: {$metric} {$dirLabel} über {$periods} Perioden ({$firstValue} → {$lastValue}, " . round($changePercent, 1) . '%)',
                'metrics' => [$metric => $lastValue, 'change_percent' => round($changePercent, 1)],
            ];
        }

        return $triggered;
    }

    /**
     * Cross-dimension evaluation: check if two metrics diverge or converge.
     * conditions: {"metric_a": "revenue_total", "metric_b": "costs_total", "relationship": "diverging"}
     */
    protected function evaluateCrossDimension(array $conditions, array $entityIds): array
    {
        $metricA = $conditions['metric_a'] ?? null;
        $metricB = $conditions['metric_b'] ?? null;
        $relationship = $conditions['relationship'] ?? 'diverging'; // diverging or converging

        if (! $metricA || ! $metricB) {
            return [];
        }

        $entities = $this->getEntityNames($entityIds);
        $triggered = [];

        // Need at least 2 data points to detect divergence
        $snapshots = OrganizationEntitySnapshot::whereIn('entity_id', $entityIds)
            ->where('snapshot_date', '>=', now()->subDays(14))
            ->orderBy('snapshot_date')
            ->get()
            ->groupBy('entity_id');

        foreach ($entityIds as $entityId) {
            $entitySnapshots = $snapshots[$entityId] ?? collect();
            $unique = $entitySnapshots->unique('snapshot_date')->sortBy('snapshot_date')->values();

            if ($unique->count() < 2) {
                continue;
            }

            $first = $unique->first();
            $last = $unique->last();

            $gapFirst = ($first->metrics[$metricA] ?? 0) - ($first->metrics[$metricB] ?? 0);
            $gapLast = ($last->metrics[$metricA] ?? 0) - ($last->metrics[$metricB] ?? 0);

            $isDiverging = abs($gapLast) > abs($gapFirst) * 1.2; // 20% threshold
            $isConverging = abs($gapLast) < abs($gapFirst) * 0.8;

            $matches = ($relationship === 'diverging' && $isDiverging)
                    || ($relationship === 'converging' && $isConverging);

            if ($matches) {
                $entityName = $entities[$entityId] ?? "Entity #{$entityId}";
                $relLabel = $relationship === 'diverging' ? 'divergieren' : 'konvergieren';
                $aVal = $last->metrics[$metricA] ?? 0;
                $bVal = $last->metrics[$metricB] ?? 0;
                $triggered[] = [
                    'entity_id' => $entityId,
                    'message' => "{$entityName}: {$metricA} und {$metricB} {$relLabel} ({$aVal} vs. {$bVal})",
                    'metrics' => [$metricA => $aVal, $metricB => $bVal],
                ];
            }
        }

        return $triggered;
    }

    /**
     * Ratio evaluation: compare ratio of two metrics against a threshold.
     * conditions: {"numerator": "items_done", "denominator": "items_total", "operator": "<", "value": 0.5}
     */
    protected function evaluateRatio(array $conditions, array $entityIds): array
    {
        $numerator = $conditions['numerator'] ?? null;
        $denominator = $conditions['denominator'] ?? null;
        $operator = $conditions['operator'] ?? '<';
        $threshold = $conditions['value'] ?? 0.5;

        if (! $numerator || ! $denominator) {
            return [];
        }

        $snapshots = $this->getLatestSnapshots($entityIds);
        $entities = $this->getEntityNames($entityIds);
        $triggered = [];

        foreach ($snapshots as $entityId => $snapshot) {
            $numVal = $snapshot->metrics[$numerator] ?? 0;
            $denVal = $snapshot->metrics[$denominator] ?? 0;

            if ($denVal == 0) {
                continue;
            }

            $ratio = $numVal / $denVal;

            $matches = match ($operator) {
                '>' => $ratio > $threshold,
                '>=' => $ratio >= $threshold,
                '<' => $ratio < $threshold,
                '<=' => $ratio <= $threshold,
                '==' => abs($ratio - $threshold) < 0.001,
                default => false,
            };

            if ($matches) {
                $entityName = $entities[$entityId] ?? "Entity #{$entityId}";
                $ratioFormatted = round($ratio * 100, 1);
                $thresholdFormatted = round($threshold * 100, 1);
                $triggered[] = [
                    'entity_id' => $entityId,
                    'message' => "{$entityName}: {$numerator}/{$denominator} = {$ratioFormatted}% ({$operator} {$thresholdFormatted}%)",
                    'metrics' => [$numerator => $numVal, $denominator => $denVal, 'ratio' => round($ratio, 4)],
                ];
            }
        }

        return $triggered;
    }

    /**
     * Auto-resolve open signals for entities that no longer match the definition.
     */
    protected function autoResolveStaleSignals(OrganizationSignalDefinition $def, array $triggeringEntityIds): int
    {
        $query = OrganizationSignal::where('signal_definition_id', $def->id)
            ->open();

        if (! empty($triggeringEntityIds)) {
            $query->whereNotIn('entity_id', $triggeringEntityIds);
        }

        $staleSignals = $query->get();
        $count = 0;

        foreach ($staleSignals as $signal) {
            $signal->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Get the latest snapshot for each entity (no N+1).
     *
     * @return array<int, OrganizationEntitySnapshot>
     */
    protected function getLatestSnapshots(array $entityIds): array
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
     * Get entity names by IDs (batch load).
     */
    protected function getEntityNames(array $entityIds): array
    {
        return OrganizationEntity::whereIn('id', $entityIds)
            ->pluck('name', 'id')
            ->all();
    }
}
