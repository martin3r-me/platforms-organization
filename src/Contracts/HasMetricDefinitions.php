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
     *   - basis?: Wie ist der gespeicherte Wert zu lesen?
     *     'cumulative_since_start' = monoton wachsender Zaehler (z.B. time_total_minutes)
     *       → delta(N Tage) = N-Tage-Flow
     *     'window_7d'/'window_30d' = vorab-gefenstertes Aggregat
     *       → aktueller Wert ist bereits der gewuenschte Zeitraum, NICHT differenzieren
     *     'stichtag' = Bestand zu diesem Zeitpunkt (z.B. links_count)
     *       → delta(N Tage) = Strukturveraenderung
     *     'modulator_factor' = Zustand/Faktor, keine Mengenaussage
     *       → kein Delta, nur aktueller Wert
     *     Default: aus 'type' abgeleitet (flow→cumulative_since_start, stock→stichtag, modulator→modulator_factor)
     *   - is_dimension_primary?: bool (default false)
     *     Markiert diese Metrik als kanonisches Mass ihrer Dimension. Wird fuer Score-Berechnung
     *     in DimensionRadarService verwendet, wenn dimension_score_method=primary aktiv ist.
     *     Max. eine Metrik pro Dimension darf primary sein (Validation in Registry).
     *   - subset_of?: string
     *     Verweist auf eine andere Metrik, deren Teilmenge dieser Wert ist (z.B.
     *     time_billed_minutes subset_of time_total_minutes). Aggregator schliesst Subsets
     *     bei Dimensionssummen aus, um Doppelzaehlung zu vermeiden.
     *
     * @return array<string, array{
     *   label: string,
     *   group: string,
     *   direction: 'up'|'down'|'neutral',
     *   unit: 'count'|'minutes'|'percentage'|'points'|'score'|'currency'|'days',
     *   dimension?: 'complexity'|'energy'|'throughput'|'org_capital'|'costs'|'revenue'|'potential'|'quality',
     *   type?: 'stock'|'flow'|'modulator',
     *   pair?: string,
     *   aggregation_mode?: 'own'|'rolled_up'|'both',
     *   roll_up_function?: 'sum'|'avg'|'max'|'min'|'latest',
     *   basis?: 'cumulative_since_start'|'window_7d'|'window_30d'|'stichtag'|'modulator_factor',
     *   is_dimension_primary?: bool,
     *   subset_of?: string,
     * }>
     */
    public function metricDefinitions(): array;
}
