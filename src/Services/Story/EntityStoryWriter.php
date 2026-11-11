<?php

namespace Platform\Organization\Services\Story;

use Illuminate\Support\Facades\Log;
use Platform\Core\Services\AnthropicProvider;

/**
 * Macht aus den Fakten eines Knotens (EntityFactsService) zwei bis vier Sätze.
 *
 * Die KI formuliert nur; jede Zahl und jeder Name kommt aus den Fakten. Ruhige oder stumme Knoten,
 * fehlender API-Key oder ein Fehler → fester Satz aus denselben Fakten (template), nie eine Lücke.
 */
class EntityStoryWriter
{
    private const SYSTEM = <<<'TXT'
Du schreibst die „Lage" eines Knotens in der Organisation BHG.DIGITAL: zwei bis vier kurze deutsche Sätze, höchstens 70 Wörter, reiner Text ohne Markdown, ohne Aufzählung, ohne Überschrift.

Regeln:
- Der erste Satz ist die Antwort auf „Was ist hier los?" — die wichtigste Bewegung, mit Namen und Zahl.
- Namen statt Prozente: welches Projekt, welche Person, welcher Kindknoten.
- Jede Zahl und jeder Name muss in den Fakten stehen. Nichts erfinden, keine Ursachen vermuten, keine Ratschläge.
- Keine Zahl ohne Richtung, wo es einen Vergleich gibt (z. B. „4 h, halb so viel wie in der Vorwoche").
- Stillstand nur als Tatsache („seit 12 Tagen keine Buchung"), nie als Wertung.
- Ist coverage.expanded wahr, erwähne knapp, dass die Erfassung im Zeitraum erweitert wurde — Sprünge deshalb nicht als Arbeit erzählen.
- Kein Geld. Keine Metrik-Schlüssel, keine Fachbegriffe wie „Snapshot", „Delta", „Dimension".
- Stunden mit Komma (1,5 h), große Zahlen mit Punkt (1.688). Personennamen in normaler Schreibweise.
TXT;

    public function __construct(protected AnthropicProvider $anthropic) {}

    /** @return array{story: string, source: string, model: ?string} */
    public function write(array $facts, bool $allowLlm = true): array
    {
        if (! $allowLlm || $facts['quiet'] || $this->isMute($facts) || ! $this->anthropic->isAvailable()) {
            return ['story' => $this->template($facts), 'source' => 'template', 'model' => null];
        }

        try {
            $result = $this->anthropic->chat(
                [['role' => 'user', 'content' => "Fakten (JSON):\n" . json_encode($this->compact($facts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]],
                [
                    'system' => self::SYSTEM,
                    'model' => config('ai.anthropic.story_model', config('ai.anthropic.inference_model', 'claude-sonnet-5')),
                    'max_tokens' => 400,
                    'temperature' => 0.3,
                ],
            );
            $text = trim((string) ($result['content'] ?? ''));
            if ($text !== '') {
                return ['story' => $text, 'source' => 'llm', 'model' => $result['model'] ?? null];
            }
        } catch (\Throwable $e) {
            Log::warning('EntityStoryWriter: KI-Satz fehlgeschlagen, fester Satz', ['entity' => $facts['entity']['id'] ?? null, 'error' => $e->getMessage()]);
        }

        return ['story' => $this->template($facts), 'source' => 'template', 'model' => null];
    }

    /** Fester Satz aus den Fakten — für ruhige/stumme Knoten und als Rückfall. */
    public function template(array $facts): string
    {
        $when = $this->when($facts['period']['key']);

        if ($this->isMute($facts)) {
            return 'Hier ist noch nichts angebunden, das sich messen lässt.';
        }

        $active = collect($facts['children'] ?? [])->reject(fn ($c) => $c['quiet'] || $c['mute'])->values();

        if ($facts['quiet']) {
            $s = "{$when} ruhig: nichts gebucht, keine Bewegung.";
            $days = $facts['stillness']['days_since_last_booking'];
            if ($days !== null && $days > 0) {
                $s .= " Letzte Buchung vor {$days} " . ($days === 1 ? 'Tag' : 'Tagen') . '.';
            }

            return $s;
        }

        $parts = [];
        $e = $facts['energy'];
        if ($e['minutes'] > 0) {
            $s = "{$when} {$this->hours($e['minutes'])} gebucht";
            if ($e['previous_minutes'] > 0) {
                $s .= " (davor {$this->hours($e['previous_minutes'])})";
            }
            if ($e['agent_minutes'] > 0) {
                $s .= ", davon {$this->hours($e['agent_minutes'])} von Agenten";
            }
            $ctx = array_values(array_filter(array_map(fn ($c) => $this->cleanLabel($c['label']), array_slice($e['contexts'], 0, 2))));
            if ($ctx) {
                $s .= ', vor allem auf ' . implode(' und ', $ctx);
            }
            $parts[] = $s . '.';
        }

        $moves = array_merge(
            array_map(fn ($m) => $this->move($m), array_slice($facts['movers']['good'], 0, 2)),
            array_map(fn ($m) => $this->move($m), array_slice($facts['movers']['bad'], 0, 2)),
        );
        if ($moves) {
            $parts[] = 'Bewegt: ' . implode(', ', $moves) . '.';
        }
        if ($facts['coverage']['expanded'] ?? false) {
            $parts[] = 'Die Erfassung wurde im Zeitraum erweitert.';
        }
        if ($active->isNotEmpty()) {
            $parts[] = 'Am meisten los: ' . $active->take(2)->pluck('name')->implode(' und ') . '.';
        }

        return implode(' ', $parts) ?: "{$when} kaum Bewegung.";
    }

    // ── intern ─────────────────────────────────────────────────

    protected function isMute(array $facts): bool
    {
        $hasMetrics = collect($facts['dimensions'])->contains(fn ($d) => $d['metrics'] !== []) || $facts['excluded'] !== [];
        $childrenAlive = collect($facts['children'] ?? [])->contains(fn ($c) => ! $c['mute']);

        return ! $hasMetrics && $facts['energy']['minutes'] === 0 && ! $childrenAlive;
    }

    /** Was die KI sieht: nur das Erzählbare, keine internen Schlüssel. */
    protected function compact(array $f): array
    {
        $metric = fn ($m) => array_filter([
            'was' => $m['label'], 'jetzt' => $m['current'], 'veraenderung' => $m['delta'],
            'im_zeitraum' => $m['flow'] ?? null,
        ], fn ($v) => $v !== null);

        $flows = [];
        foreach ($f['dimensions'] as $dim) {
            foreach ($dim['metrics'] as $m) {
                if ($m['flow'] !== null) {
                    $flows[] = ['was' => $m['label'], 'im_zeitraum' => $m['flow']];
                }
            }
        }

        return [
            'knoten' => $f['entity']['name'] . ($f['entity']['type'] ? ' (' . $f['entity']['type'] . ')' : ''),
            'zeitraum' => $f['period']['label'],
            'gebucht' => [
                'stunden' => round($f['energy']['minutes'] / 60, 1),
                'davon_agenten' => round($f['energy']['agent_minutes'] / 60, 1),
                'vorperiode_stunden' => round($f['energy']['previous_minutes'] / 60, 1),
                'wer' => array_map(fn ($p) => ['name' => $this->name($p['name']), 'stunden' => round($p['minutes'] / 60, 1), 'agent' => $p['agent']], $f['energy']['people']),
                'worauf' => array_map(fn ($c) => ['was' => $this->cleanLabel($c['label']), 'stunden' => round($c['minutes'] / 60, 1)], $f['energy']['contexts']),
            ],
            'gut' => array_map($metric, $f['movers']['good']),
            'schlecht' => array_map($metric, $f['movers']['bad']),
            'neutral' => array_map($metric, $f['movers']['neutral']),
            'im_zeitraum' => $flows,
            'coverage' => ['expanded' => $f['coverage']['expanded'] ?? false],
            'signale_offen' => $f['signals']['open'],
            'signale' => array_map(fn ($s) => $s['message'], $f['signals']['top']),
            'letzte_buchung_vor_tagen' => $f['stillness']['days_since_last_booking'],
            'darunter' => array_map(fn ($c) => array_filter([
                'name' => $c['name'],
                'stunden' => round($c['minutes'] / 60, 1),
                'zustand' => $c['mute'] ? 'nichts angebunden' : ($c['quiet'] ? 'ruhig' : 'bewegt'),
                'top' => $c['top'] ? $c['top']['label'] . ' ' . ($c['top']['delta'] > 0 ? '+' : '') . $c['top']['delta'] : null,
            ], fn ($v) => $v !== null), $f['children'] ?? []),
        ];
    }

    protected function move(array $m): string
    {
        if (($m['flow'] ?? null) !== null) {
            // Fenster-Metrik: der Wert im Zeitraum zählt, nicht die Differenz zur Vorperiode
            return preg_replace('/\s*\(\d+ Tage\)/u', '', $m['label']) . ' ' . $this->num($m['flow']);
        }
        $d = $m['delta'];
        $sign = $d > 0 ? '+' : '−';

        return $m['label'] . ' ' . $sign . $this->num(abs($d));
    }

    protected function when(string $period): string
    {
        return match ($period) {
            'month' => 'In den letzten 30 Tagen',
            'quarter' => 'Im Quartal bisher',
            default => 'Diese Woche',
        };
    }

    protected function hours(int $minutes): string
    {
        $h = round($minutes / 60, 1);

        return str_replace('.', ',', (string) ($h == (int) $h ? (int) $h : $h)) . ' h';
    }

    protected function num(int|float $v): string
    {
        return is_float($v) && $v != (int) $v ? number_format($v, 1, ',', '.') : number_format((int) $v, 0, ',', '.');
    }

    /** „PROJECT · PLANUNG 2027 - BHGBROICHCATERING" → „PLANUNG 2027 - BHGBROICHCATERING" */
    protected function cleanLabel(?string $label): ?string
    {
        return $label === null ? null : trim(preg_replace('/^[A-Z]+ · /u', '', $label));
    }

    /** „MARTIN ERREN" → „Martin Erren" */
    protected function name(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return mb_strtoupper($name) === $name ? mb_convert_case(mb_strtolower($name), MB_CASE_TITLE) : $name;
    }
}
