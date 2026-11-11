<?php

namespace Platform\Organization\Services\Story;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Services\EntityTimeResolver;
use Platform\Organization\Services\TimeContextResolver;

/**
 * Fakten-Schicht der Knoten-Erzählung („Lage"): was sich an einem Knoten im Zeitraum bewegt hat —
 * deterministisch aus Snapshots + Dimensionen, Namen und Zeit aus der Quelle. Die KI formuliert
 * später nur aus diesen Fakten; erfunden wird nichts.
 *
 * Absicherungen gegen falsche Bewegung (in der Probe an DIGITAL.AGENCY gefunden):
 *  - Energie aus den Zeiteinträgen im Zeitraum (work_date), nicht als Snapshot-Differenz —
 *    „Zeit seit Beginn" sinkt, wenn Einträge verschoben, gelöscht oder nachgetragen werden.
 *  - cumulative_since_start, das sinkt → kein Fluss, sondern Umbau (ausgeschlossen, mit Grund).
 *  - Fehlt der Key im Vergleichs-Snapshot → „neu erfasst", keine Bewegung.
 *  - window_7d/30d nicht differenzieren: passt das Fenster zum Zeitraum, ist der Wert der Fluss.
 *  - modulator_factor → nur Stand. Prozent außerhalb 0–100 → unplausibel.
 *  - Erfassung erweitert (Keys fehlen im Vergleich) + Stichtags-Sprung ab 0 bzw. ×3 → keine Bewegung.
 *  - Gleiche Zahl unter zwei Keys (Items offen ≙ Aufgaben offen) → einmal.
 *  - Geld (Kosten, Umsatz) bewusst noch nicht.
 */
class EntityFactsService
{
    public const PERIODS = ['week', 'month', 'quarter'];

    /** Erst ohne Geld (Entscheidung 11.11.2026). */
    private const EXCLUDED_DIMENSIONS = [EntityLinkRegistry::DIMENSION_COSTS, EntityLinkRegistry::DIMENSION_REVENUE];

    /** Zeiten aus der Quelle ersetzen diese Snapshot-Zähler. */
    private const SOURCE_FLOWS = ['time_total_minutes', 'time_billed_minutes', 'persons_time_total_minutes'];

    private const WINDOW_DAYS = ['window_7d' => 7, 'window_30d' => 30];

    /** Ab diesem Faktor ist ein Stichtags-Sprung bei erweiterter Erfassung keine Bewegung. */
    private const COVERAGE_JUMP = 3;

    /** Relative Änderung darunter gilt als Rauschen. */
    private const NOISE = 0.01;

    public function __construct(
        protected EntityLinkRegistry $registry,
        protected EntityTimeResolver $time,
        protected TimeContextResolver $labels,
    ) {}

    /**
     * @param  string  $period  week | month | quarter
     */
    public function forEntity(OrganizationEntity $entity, string $period = 'week', ?Carbon $asOf = null, bool $withChildren = true): array
    {
        [$from, $to, $days, $label] = $this->range($period, $asOf);

        $current = $this->snapshotAtOrBefore($entity->id, $to);
        $base = $this->snapshotAtOrBefore($entity->id, $from->subDay());

        $metrics = $this->metricFacts($current?->metrics ?? [], $base?->metrics, $days);
        $energy = $this->energy($entity, $from, $to, $days);

        $facts = [
            'entity' => ['id' => $entity->id, 'name' => $entity->name, 'type' => $entity->type?->name],
            'period' => [
                'key' => $period, 'label' => $label, 'days' => $days,
                'from' => $from->toDateString(), 'to' => $to->toDateString(),
            ],
            'snapshots' => [
                'current' => $current?->snapshot_date?->toDateString(),
                'base' => $base?->snapshot_date?->toDateString(),
            ],
            'energy' => $energy,
            'dimensions' => $metrics['dimensions'],
            'movers' => $this->movers($metrics['usable']),
            'excluded' => $metrics['excluded'],
            'coverage' => $metrics['coverage'],
            'signals' => $this->signals($entity),
            'stillness' => $this->stillness($entity, $to),
            'quiet' => false,
        ];

        $facts['quiet'] = $energy['minutes'] === 0 && $facts['movers']['good'] === [] && $facts['movers']['bad'] === [];

        if ($withChildren) {
            $facts['children'] = $entity->children()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (OrganizationEntity $child) => $this->childSummary($child, $period, $asOf))
                ->sortByDesc('weight')->values()->all();
        }

        return $facts;
    }

    // ── Zeitraum ───────────────────────────────────────────────

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int, 3: string} */
    protected function range(string $period, ?Carbon $asOf): array
    {
        $to = CarbonImmutable::parse(($asOf ?? now())->toDateString());

        return match ($period) {
            'month' => [$to->subDays(29), $to, 30, 'letzte 30 Tage'],
            'quarter' => (function () use ($to) {
                $from = $to->firstOfQuarter();

                return [$from, $to, $from->diffInDays($to) + 1, 'Q' . $to->quarter . '/' . $to->year . ' bisher'];
            })(),
            default => [$to->subDays(6), $to, 7, 'diese Woche (7 Tage)'],
        };
    }

    // ── Snapshots → Dimensionen ────────────────────────────────

    protected function snapshotAtOrBefore(int $entityId, CarbonImmutable $date): ?OrganizationEntitySnapshot
    {
        return OrganizationEntitySnapshot::where('entity_id', $entityId)
            ->where('snapshot_date', '<=', $date->toDateString())
            ->orderByDesc('snapshot_date')
            ->orderByRaw("CASE snapshot_period WHEN 'evening' THEN 0 ELSE 1 END")
            ->first();
    }

    /**
     * @param  array|null  $base  null = kein Vergleichs-Snapshot (Knoten jünger als der Zeitraum)
     * @return array{dimensions: array, usable: array, excluded: array}
     */
    protected function metricFacts(array $current, ?array $base, int $days): array
    {
        $defs = $this->registry->allMetricDefinitions();
        $dimensionLabels = EntityLinkRegistry::allDimensions();

        $usable = [];
        $excluded = [];
        $seen = [];

        // Erfassung erweitert: Keys, die heute gemessen werden, im Vergleichs-Snapshot aber fehlen
        // (z. B. Planner-Metriken erst seit Mitte des Zeitraums). Dann sind Stichtags-Sprünge
        // eher neue Erfassung als Arbeit.
        $newKeys = $base === null ? [] : array_values(array_filter(
            array_keys($defs),
            fn ($k) => ($defs[$k]['dimension'] ?? null) && $this->value($current, $k, $defs[$k]) !== null && $this->value($base, $k, $defs[$k]) === null,
        ));
        $coverageExpanded = $newKeys !== [];

        foreach ($defs as $key => $def) {
            $dimension = $def['dimension'] ?? null;
            if (! $dimension || in_array($dimension, self::EXCLUDED_DIMENSIONS, true) || in_array($key, self::SOURCE_FLOWS, true)) {
                continue;
            }

            $cur = $this->value($current, $key, $def);
            if ($cur === null) {
                continue;
            }
            $prev = $base === null ? null : $this->value($base, $key, $def);
            if ($cur == 0 && ($prev ?? 0) == 0) {
                continue;
            }

            $fact = [
                'key' => $key,
                'label' => $def['label'],
                'dimension' => $dimension,
                'unit' => $def['unit'],
                'direction' => $def['direction'],
                'basis' => $def['basis'],
                'primary' => (bool) $def['is_dimension_primary'],
                'current' => $this->round($cur),
                'previous' => $prev === null ? null : $this->round($prev),
                'delta' => null,
                'flow' => null,
                'sentiment' => 'neutral',
            ];

            if ($def['unit'] === 'percentage' && ($cur < 0 || $cur > 100)) {
                $excluded[] = $fact + ['reason' => 'implausible'];
                continue;
            }

            switch ($def['basis']) {
                case 'modulator_factor':
                    break; // nur Stand

                case 'window_7d':
                case 'window_30d':
                    if (self::WINDOW_DAYS[$def['basis']] !== $days) {
                        continue 2; // Fenster passt nicht zum Zeitraum → weglassen
                    }
                    $fact['flow'] = $fact['current'];
                    if ($prev !== null) {
                        // Vorperiode nur, wenn der Vergleichs-Snapshot wirklich ein Fenster früher liegt
                        $fact['delta'] = $this->round($cur - $prev);
                    }
                    break;

                case 'cumulative_since_start':
                    if ($prev === null) {
                        $excluded[] = $fact + ['reason' => 'no_baseline'];
                        continue 2;
                    }
                    if ($cur < $prev) {
                        $excluded[] = $fact + ['reason' => 'counter_decreased'];
                        continue 2;
                    }
                    $fact['flow'] = $this->round($cur - $prev);
                    $fact['delta'] = $fact['flow'];
                    break;

                default: // stichtag
                    if ($prev === null) {
                        $excluded[] = $fact + ['reason' => 'no_baseline'];
                        continue 2;
                    }
                    if ($coverageExpanded && $cur > $prev && ($prev == 0 || $cur / $prev >= self::COVERAGE_JUMP)) {
                        $excluded[] = $fact + ['reason' => 'coverage_change'];
                        continue 2;
                    }
                    $fact['delta'] = $this->round($cur - $prev);
            }

            if ($fact['delta'] !== null && $fact['delta'] != 0) {
                $fact['sentiment'] = match ($def['direction']) {
                    'up' => $fact['delta'] > 0 ? 'positive' : 'negative',
                    'down' => $fact['delta'] < 0 ? 'positive' : 'negative',
                    default => 'neutral',
                };
            }

            // Gleiche Zahl unter zweitem Key (z. B. items_open ≙ planner_items_open) nur einmal.
            $sig = $dimension . '|' . $fact['current'] . '|' . $fact['delta'];
            if (isset($seen[$sig])) {
                if ($fact['primary']) {
                    $usable[$seen[$sig]] = $fact;
                }
                continue;
            }
            $seen[$sig] = $key;
            $usable[$key] = $fact;
        }

        $dimensions = [];
        foreach ($dimensionLabels as $dim => $meta) {
            if (in_array($dim, self::EXCLUDED_DIMENSIONS, true)) {
                continue;
            }
            $list = array_values(array_filter($usable, fn ($f) => $f['dimension'] === $dim));
            usort($list, fn ($a, $b) => [$b['primary'], abs($b['delta'] ?? 0)] <=> [$a['primary'], abs($a['delta'] ?? 0)]);
            $dimensions[$dim] = ['label' => $meta['label'], 'metrics' => $list];
        }

        return ['dimensions' => $dimensions, 'usable' => $usable, 'excluded' => $excluded,
            'coverage' => ['expanded' => $coverageExpanded, 'new_keys' => $newKeys]];
    }

    /** Teilbaum-Wert, wo die Metrik aufrollt; null = Key im Snapshot nicht vorhanden. */
    protected function value(array $metrics, string $key, array $def): int|float|null
    {
        $v = ($def['aggregation_mode'] ?? 'own') === 'rolled_up' && array_key_exists($key . '_cascaded', $metrics)
            ? $metrics[$key . '_cascaded']
            : ($metrics[$key] ?? null);

        return is_numeric($v) ? $v + 0 : null;
    }

    /**
     * Die größten Bewegungen, getrennt nach gut/schlecht (direction), relativ gewichtet.
     *
     * @return array{good: array, bad: array, neutral: array}
     */
    protected function movers(array $usable): array
    {
        $out = ['good' => [], 'bad' => [], 'neutral' => []];
        foreach ($usable as $f) {
            if (($f['delta'] ?? 0) == 0) {
                continue;
            }
            $baseValue = abs(($f['previous'] ?? 0)) ?: 1;
            $relative = abs($f['delta']) / $baseValue;
            if ($relative < self::NOISE) {
                continue; // z. B. Sichtbarkeit −0,04 % — Rauschen, keine Bewegung
            }
            $score = min($relative, 3) * ($f['primary'] ? 1.5 : 1);
            $bucket = match ($f['sentiment']) { 'positive' => 'good', 'negative' => 'bad', default => 'neutral' };
            $out[$bucket][] = ['key' => $f['key'], 'label' => $f['label'], 'dimension' => $f['dimension'], 'unit' => $f['unit'],
                'current' => $f['current'], 'delta' => $f['delta'], 'flow' => $f['flow'], 'score' => round($score, 3)];
        }
        foreach ($out as &$list) {
            usort($list, fn ($a, $b) => $b['score'] <=> $a['score']);
            $list = array_slice($list, 0, 3);
        }

        return $out;
    }

    // ── Energie aus der Quelle ─────────────────────────────────

    protected function energy(OrganizationEntity $entity, CarbonImmutable $from, CarbonImmutable $to, int $days): array
    {
        $query = fn () => $this->time->buildTimeEntryQuery($entity, true);
        $inRange = fn (CarbonImmutable $a, CarbonImmutable $b) => $query()->whereBetween('work_date', [$a->toDateString(), $b->toDateString()]);

        $minutes = (int) $inRange($from, $to)->sum('minutes');
        $agent = (int) $inRange($from, $to)->where('metadata->source', 'agent')->sum('minutes');
        $previous = (int) $inRange($from->subDays($days), $from->subDay())->sum('minutes');

        $people = $inRange($from, $to)
            ->select('user_id', DB::raw('SUM(minutes) as m'), DB::raw("MAX(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.source')) = 'agent' THEN 1 ELSE 0 END) as agent"))
            ->groupBy('user_id')->orderByDesc('m')->limit(5)->with('user:id,name')->get()
            ->map(fn ($r) => ['name' => $r->user?->name, 'minutes' => (int) $r->m, 'agent' => (bool) $r->agent])->all();

        $contexts = $inRange($from, $to)
            ->whereNotNull('context_type')
            ->select('context_type', 'context_id', DB::raw('SUM(minutes) as m'))
            ->groupBy('context_type', 'context_id')->orderByDesc('m')->limit(5)->get()
            ->map(fn ($r) => ['label' => $this->labels->resolveLabel($r->context_type, (int) $r->context_id), 'minutes' => (int) $r->m])->all();

        return [
            'minutes' => $minutes,
            'agent_minutes' => $agent,
            'previous_minutes' => $previous,
            'delta_pct' => $previous > 0 ? round(($minutes - $previous) / $previous * 100) : null,
            'people' => $people,
            'contexts' => $contexts,
        ];
    }

    // ── Signale, Stillstand, Kinder ────────────────────────────

    protected function signals(OrganizationEntity $entity): array
    {
        $open = OrganizationSignal::where('entity_id', $entity->id)->actionable();

        return [
            'open' => (clone $open)->count(),
            'critical' => (clone $open)->whereIn('severity', ['critical', 'algedonic'])->count(),
            'top' => (clone $open)->orderByRaw("CASE severity WHEN 'algedonic' THEN 0 WHEN 'critical' THEN 1 ELSE 2 END")
                ->latest()->limit(3)->get(['message', 'severity', 'created_at'])
                ->map(fn ($s) => ['message' => mb_strimwidth((string) $s->message, 0, 160, '…'), 'severity' => $s->severity, 'since' => $s->created_at?->toDateString()])->all(),
        ];
    }

    /** Stillstand vorerst nur als Tatsache: letzte Buchung im Teilbaum, ohne Wertung. */
    protected function stillness(OrganizationEntity $entity, CarbonImmutable $to): array
    {
        $last = $this->time->buildTimeEntryQuery($entity, true)->where('work_date', '<=', $to->toDateString())->max('work_date');

        return [
            'last_booking' => $last ? Carbon::parse($last)->toDateString() : null,
            'days_since_last_booking' => $last ? (int) Carbon::parse($last)->diffInDays($to) : null,
        ];
    }

    protected function childSummary(OrganizationEntity $child, string $period, ?Carbon $asOf): array
    {
        return $this->summarize($this->forEntity($child, $period, $asOf, false));
    }

    /**
     * Kurzfassung eines Knotens, wie sie beim Eltern-Knoten unter „Darunter" steht. Öffentlich, damit
     * der tägliche Lauf die schon gerechneten Fakten der Kinder wiederverwendet statt neu zu rechnen.
     */
    public function summarize(array $f): array
    {
        $top = $f['movers']['good'][0] ?? $f['movers']['bad'][0] ?? $f['movers']['neutral'][0] ?? null;
        $hasMetrics = collect($f['dimensions'])->contains(fn ($d) => $d['metrics'] !== []) || $f['excluded'] !== [];

        return [
            'id' => $f['entity']['id'],
            'name' => $f['entity']['name'],
            'minutes' => $f['energy']['minutes'],
            'good' => count($f['movers']['good']),
            'bad' => count($f['movers']['bad']),
            'top' => $top,
            'quiet' => $f['quiet'],
            'mute' => ! $hasMetrics && $f['energy']['minutes'] === 0,
            'signals_open' => $f['signals']['open'],
            'days_since_last_booking' => $f['stillness']['days_since_last_booking'],
            'weight' => $f['energy']['minutes'] + 60 * (count($f['movers']['good']) + count($f['movers']['bad'])),
        ];
    }

    protected function round(int|float $v): int|float
    {
        return is_float($v) ? round($v, 2) : $v;
    }
}
