<?php

namespace Platform\Organization\Services;

class MetricDelta
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $group,
        public readonly int|float $current,
        public readonly int|float $previous,
        public readonly int|float $delta,
        public readonly string $sentiment,   // 'positive' | 'negative' | 'neutral'
        public readonly string $unit,
        public readonly ?float $ratio,
        public readonly ?string $pairKey,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'group' => $this->group,
            'current' => $this->current,
            'previous' => $this->previous,
            'delta' => $this->delta,
            'delta_formatted' => $this->formatDelta(),
            'sentiment' => $this->sentiment,
            'unit' => $this->unit,
            'ratio' => $this->ratio,
            'pair_key' => $this->pairKey,
        ];
    }

    public function formatDelta(): string
    {
        if ($this->delta == 0) {
            return '0';
        }

        $sign = $this->delta > 0 ? '+' : '';

        return match ($this->unit) {
            'minutes' => $sign . round($this->delta / 60, 1) . 'h',
            'percentage' => $sign . $this->delta . '%',
            default => $sign . $this->delta,
        };
    }
}
