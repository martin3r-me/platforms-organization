<?php

namespace Platform\Organization\Contracts;

interface HasMetricDefinitions
{
    /**
     * Deklariert Metrik-Definitionen fuer Snapshot-Keys dieses Providers.
     *
     * Pflichtfelder: label, group, direction, unit
     * Optionale Felder (abwaertskompatibel):
     *   - dimension: Welche der 7½ Dimensionen bedient diese Metrik?
     *   - type: Wie wird die Metrik erfasst? (stock/flow/modulator)
     *   - pair: Bezugs-Metrik fuer Ratio-Berechnung
     *
     * @return array<string, array{
     *   label: string,
     *   group: string,
     *   direction: 'up'|'down'|'neutral',
     *   unit: 'count'|'minutes'|'percentage'|'points'|'score'|'currency',
     *   dimension?: 'complexity'|'energy'|'throughput'|'org_capital'|'costs'|'revenue'|'potential'|'quality',
     *   type?: 'stock'|'flow'|'modulator',
     *   pair?: string,
     * }>
     */
    public function metricDefinitions(): array;
}
