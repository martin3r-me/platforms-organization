<?php

namespace Platform\Organization\Contracts;

interface HasMetricDefinitions
{
    /**
     * Deklariert Metrik-Definitionen fuer Snapshot-Keys dieses Providers.
     *
     * @return array<string, array{
     *   label: string,
     *   group: string,
     *   direction: 'up'|'down'|'neutral',
     *   unit: 'count'|'minutes'|'percentage'|'points'|'score',
     *   pair?: string,
     * }>
     */
    public function metricDefinitions(): array;
}
