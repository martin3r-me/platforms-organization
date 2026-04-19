<?php

namespace Platform\Organization\Services;

class MovementResult
{
    /**
     * @param array<string, MetricDelta> $deltas
     */
    public function __construct(
        public readonly array $deltas,
        public readonly int $days,
        public readonly ?string $group,
        public readonly array $availableGroups,
    ) {}

    public static function empty(): self
    {
        return new self([], 7, null, []);
    }

    public function toArray(): array
    {
        $metrics = [];
        foreach ($this->deltas as $key => $delta) {
            $metrics[$key] = $delta->toArray();
        }

        return [
            'metrics' => $metrics,
            'metrics_by_group' => $this->metricsByGroupArray(),
            'days' => $this->days,
            'group' => $this->group,
            'available_groups' => $this->availableGroups,
            'summary' => $this->buildSummary(),
        ];
    }

    /**
     * @return array<string, MetricDelta[]>
     */
    public function byGroup(): array
    {
        $groups = [];
        foreach ($this->deltas as $delta) {
            $groups[$delta->group][] = $delta;
        }

        return $groups;
    }

    /**
     * @return array<string, MetricDelta[]>
     */
    public function nonZeroDeltas(): array
    {
        return array_filter($this->deltas, fn (MetricDelta $d) => $d->delta != 0);
    }

    protected function metricsByGroupArray(): array
    {
        $result = [];
        foreach ($this->byGroup() as $groupKey => $deltas) {
            $result[$groupKey] = array_map(fn (MetricDelta $d) => $d->toArray(), $deltas);
        }

        return $result;
    }

    protected function buildSummary(): array
    {
        $insights = [];
        $byGroup = $this->byGroup();

        foreach ($byGroup as $groupKey => $deltas) {
            $positives = array_filter($deltas, fn (MetricDelta $d) => $d->sentiment === 'positive' && $d->delta != 0);
            $negatives = array_filter($deltas, fn (MetricDelta $d) => $d->sentiment === 'negative' && $d->delta != 0);

            if (!empty($positives)) {
                $labels = array_map(fn (MetricDelta $d) => $d->label . ' ' . $d->formatDelta(), $positives);
                $insights[] = [
                    'text' => ucfirst($groupKey) . ': ' . implode(', ', $labels),
                    'type' => 'success',
                ];
            }

            if (!empty($negatives)) {
                $labels = array_map(fn (MetricDelta $d) => $d->label . ' ' . $d->formatDelta(), $negatives);
                $insights[] = [
                    'text' => ucfirst($groupKey) . ': ' . implode(', ', $labels),
                    'type' => 'warning',
                ];
            }
        }

        return array_slice($insights, 0, 5);
    }
}
