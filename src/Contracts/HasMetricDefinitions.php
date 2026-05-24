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
     *   - aggregation_mode?: 'own'|'rolled_up'|'both' (default: 'own')
     *     'own' = nur eigene Werte, 'rolled_up' = cascaded-Wert verwenden, 'both' = beide anzeigen
     *   - roll_up_function?: 'sum'|'avg'|'max'|'min'|'latest' (default: 'sum')
     *     Wie wird cascaded (fuer zukuenftige Erweiterungen, aktuell immer sum)
     *
     * @return array<string, array{
     *   label: string,
     *   group: string,
     *   direction: 'up'|'down'|'neutral',
     *   unit: 'count'|'minutes'|'percentage'|'points'|'score'|'currency',
     *   dimension?: 'complexity'|'energy'|'throughput'|'org_capital'|'costs'|'revenue'|'potential'|'quality',
     *   type?: 'stock'|'flow'|'modulator',
     *   pair?: string,
     *   aggregation_mode?: 'own'|'rolled_up'|'both',
     *   roll_up_function?: 'sum'|'avg'|'max'|'min'|'latest',
     * }>
     */
    public function metricDefinitions(): array;
}
