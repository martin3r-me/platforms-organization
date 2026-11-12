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
Du schreibst die „Lage" einer Einheit der Organisation BHG.DIGITAL: höchstens drei kurze deutsche Sätze, höchstens 55 Wörter, reiner Text ohne Markdown, ohne Aufzählung, ohne Überschrift.

Worauf es ankommt:
- Der erste Satz ist die Nachricht: das, was sich am stärksten verändert hat. „gut", „schlecht" und „neutral" sind nach Gewicht sortiert, der jeweils erste Eintrag zählt am meisten. Die gebuchte Zeit ist Kontext und nur dann die Nachricht, wenn sie sich gegenüber der Vorperiode stark verändert hat.
- Wähle die zwei, drei Fakten, die die Lage erklären. Nicht jede Zahl nennen.
- Namen statt Prozente: welches Projekt, welche Person, welche Einheit darunter.
- Hat eine Einheit in „darunter" einen „anteil_stunden" von 80 oder mehr, sag das in einem Halbsatz („fast alles in Kunden-Engagements") und wiederhole ihre Details nicht. Erzähl dann, was nur hier sichtbar ist.

Strikt:
- Jede Zahl und jeder Name steht in den Fakten. Nichts erfinden, keine Ursachen vermuten, keine Ratschläge, keine Bewertung von Stillstand.
- Vergleiche nur mit der genannten Vorperiode und nenne sie so, wie sie in „vorperiode" heißt.
- Bei Einträgen mit „im_zeitraum" zählt nur dieser Wert (z. B. „19 Aufgaben erledigt"), keine Gesamtzähler.
- Ist „erfassung_erweitert" wahr, genügt ein kurzer Hinweis wie „die Erfassung ist im Zeitraum gewachsen".
- Keine wertenden Füllwörter (immerhin, leider, erfreulich, nur, deutlich).
- Kein Geld. Keine Fachwörter wie Knoten, Kindknoten, Items, Snapshot, Delta, Dimension, Metrik.
- Schreibweise: Namen in GROSSBUCHSTABEN normal schreiben (PLANUNG 2027 → Planung 2027). Projekt und Kunde stehen getrennt; den Kunden nur nennen, wenn es hilft. Stunden als „h" mit Komma (1,5 h), große Zahlen mit Punkt (1.688).
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
            $ctx = array_values(array_filter(array_map(fn ($c) => $this->name($this->context($c['label'])['was'] ?: null), array_slice($e['contexts'], 0, 2))));
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
        $metric = fn ($m) => ($m['flow'] ?? null) !== null
            ? ['was' => $this->plain($m['label']), 'im_zeitraum' => $m['flow']]
            : ['was' => $this->plain($m['label']), 'jetzt' => $m['current'], 'veraenderung' => $m['delta']];

        $flows = [];
        foreach ($f['dimensions'] as $dim) {
            foreach ($dim['metrics'] as $m) {
                if ($m['flow'] !== null && $m['flow'] != 0) {
                    $flows[] = ['was' => $this->plain($m['label']), 'im_zeitraum' => $m['flow']];
                }
            }
        }

        return [
            'einheit' => $f['entity']['name'] . ($f['entity']['type'] ? ' (' . $f['entity']['type'] . ')' : ''),
            'zeitraum' => $f['period']['label'],
            'vorperiode' => match ($f['period']['key']) {
                'month' => 'die 30 Tage davor',
                'quarter' => 'der gleich lange Zeitraum davor',
                default => 'die Vorwoche',
            },
            'gebucht' => [
                'stunden' => round($f['energy']['minutes'] / 60, 1),
                'davon_agenten' => round($f['energy']['agent_minutes'] / 60, 1),
                'vorperiode_stunden' => round($f['energy']['previous_minutes'] / 60, 1),
                'wer' => array_map(fn ($p) => ['name' => $this->name($p['name']), 'stunden' => round($p['minutes'] / 60, 1), 'agent' => $p['agent']], $f['energy']['people']),
                'worauf' => array_map(fn ($c) => ['was' => $this->name($this->context($c['label'])['was'])] + $this->context($c['label']) + ['stunden' => round($c['minutes'] / 60, 1)], $f['energy']['contexts']),
            ],
            'gut' => array_map($metric, $f['movers']['good']),
            'schlecht' => array_map($metric, $f['movers']['bad']),
            'neutral' => array_map($metric, $f['movers']['neutral']),
            'im_zeitraum' => $flows,
            'erfassung_erweitert' => $f['coverage']['expanded'] ?? false,
            'signale_offen' => $f['signals']['open'],
            'signale' => array_map(fn ($s) => $s['message'], $f['signals']['top']),
            'letzte_buchung_vor_tagen' => $f['stillness']['days_since_last_booking'],
            'darunter' => array_map(fn ($c) => array_filter([
                'name' => $c['name'],
                'stunden' => round($c['minutes'] / 60, 1),
                'anteil_stunden' => $f['energy']['minutes'] > 0 ? (int) round(100 * $c['minutes'] / $f['energy']['minutes']) : null,
                'zustand' => $c['mute'] ? 'nichts angebunden' : ($c['quiet'] ? 'ruhig' : 'bewegt'),
                'staerkste_bewegung' => $c['top'] ? $this->plain($c['top']['label']) . ' ' . ($c['top']['delta'] > 0 ? '+' : '') . $c['top']['delta'] : null,
            ], fn ($v) => $v !== null), $f['children'] ?? []),
        ];
    }

    /** Metrik-Label in Alltagssprache: „Items (offen)" → „offene Aufgaben", ohne „(7 Tage)". */
    public function plain(string $label): string
    {
        $label = preg_replace('/\s*\(\d+ Tage\)/u', '', $label);

        return strtr($label, [
            'Items (offen)' => 'offene Aufgaben',
            'Items (erledigt)' => 'erledigte Aufgaben',
            'Items (gesamt)' => 'Aufgaben insgesamt',
            'Verknuepfungen' => 'Verknüpfungen',
            'Referring Domains' => 'verlinkende Domains',
            'AI Search Volume' => 'Suchvolumen in KI-Suchen',
            'Backlink-Spam-Score' => 'Spam-Wert der Backlinks',
        ]);
    }

    /** „PROJECT · PLANUNG 2027 - BHGBROICHCATERING" → Projekt „PLANUNG 2027", Kunde „BHGBROICHCATERING". */
    public function context(?string $label): array
    {
        $label = $this->cleanLabel($label) ?? '';
        if (preg_match('/^(.+?)\s+-\s+([^-]+)$/u', $label, $m)) {
            return ['was' => trim($m[1]), 'kunde' => trim($m[2])];
        }

        return ['was' => $label];
    }

    protected function move(array $m): string
    {
        if (($m['flow'] ?? null) !== null) {
            // Fenster-Metrik: der Wert im Zeitraum zählt, nicht die Differenz zur Vorperiode
            return $this->plain($m['label']) . ' ' . $this->num($m['flow']);
        }
        $d = $m['delta'];
        $sign = $d > 0 ? '+' : '−';

        return $this->plain($m['label']) . ' ' . $sign . $this->num(abs($d));
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
    public function name(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return mb_strtoupper($name) === $name ? mb_convert_case(mb_strtolower($name), MB_CASE_TITLE) : $name;
    }
}
