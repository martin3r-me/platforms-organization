<?php

namespace Platform\Organization\Strategy;

/**
 * Bewertet eine (normalisierte) Carrier-Strategie gegen das StrategyBlueprint.
 * Reine Logik — Array rein, Report raus. Eine Stelle, gegen die UI/MCP/API
 * einheitlich prüfen (Lücken anzeigen, Autoren führen, Vollständigkeit als
 * Puls-Signal speisen).
 *
 * Erwartete Eingabe (Ziel-Form des Blueprints):
 * [
 *   'mission'  => ?string (md),
 *   'vision'   => ?string (md),
 *   'regnose'  => ?string (md),
 *   'focus_areas' => [
 *       ['title'=>?string, 'zielbilder'=>[], 'hindernisse'=>[],
 *        'meilensteine'=>[ ['title'=>?string, 'quarter'=>['year'=>int,'q'=>int]|null], ... ]],
 *       ...
 *   ],
 * ]
 */
class StrategyCompleteness
{
    /** @return array<string, mixed> */
    public function evaluate(array $strategy): array
    {
        $chapters = [];
        $issues = [];
        $requiredOk = 0;
        $requiredTotal = 0;

        foreach (StrategyBlueprint::chapters() as $c) {
            [$ok, $reason, $chapterIssues] = $this->evaluateChapter($c, $strategy);
            $issues = array_merge($issues, $chapterIssues);

            if ($c->required()) {
                $requiredTotal++;
                if ($ok) {
                    $requiredOk++;
                }
            }

            $chapters[] = [
                'key'      => $c->value,
                'label'    => $c->label(),
                'order'    => $c->order(),
                'required' => $c->required(),
                'ok'       => $ok,
                'reason'   => $reason,
            ];
        }

        $percent = $requiredTotal > 0 ? (int) round($requiredOk / $requiredTotal * 100) : 100;
        $hasError = (bool) array_filter($issues, fn ($i) => $i['severity'] === 'error');

        return [
            'complete'       => $requiredOk === $requiredTotal && ! $hasError,
            'percent'        => $percent,
            'chapters'       => $chapters,
            'issues'         => array_values($issues),
            'map_renderable' => $this->milestonesWithQuarter($strategy) > 0,
        ];
    }

    /** @return array{0: bool, 1: ?string, 2: array<int, array<string,string>>} */
    private function evaluateChapter(StrategyChapter $c, array $strategy): array
    {
        return match ($c) {
            StrategyChapter::Mission => $this->prose($strategy['mission'] ?? null, 'Mission fehlt.'),
            StrategyChapter::Vision  => $this->prose($strategy['vision'] ?? null, 'Vision fehlt.'),
            StrategyChapter::Regnose => $this->prose($strategy['regnose'] ?? null, 'Regnose fehlt.'),
            StrategyChapter::FocusAreas        => $this->focusAreas($strategy['focus_areas'] ?? []),
            StrategyChapter::TransformationMap => $this->transformationMap($strategy),
        };
    }

    private function prose(?string $value, string $missing): array
    {
        return $this->filled($value)
            ? [true, null, []]
            : [false, $missing, []];
    }

    private function focusAreas(array $areas): array
    {
        if (count($areas) === 0) {
            return [false, 'Mindestens ein Fokusraum nötig.', []];
        }

        $issues = [];
        $allOk = true;

        foreach ($areas as $i => $area) {
            $name = $this->filled($area['title'] ?? null) ? $area['title'] : ('Fokusraum ' . ($i + 1));
            $milestones = $area['meilensteine'] ?? [];

            if (count($milestones) === 0) {
                $allOk = false;
                $issues[] = ['severity' => 'error', 'chapter' => 'focus_areas', 'message' => "„{$name}" . '" hat keinen Meilenstein.'];
                continue;
            }

            foreach ($milestones as $j => $m) {
                if (! $this->hasQuarter($m['quarter'] ?? null)) {
                    $allOk = false;
                    $mName = $this->filled($m['title'] ?? null) ? $m['title'] : ('Meilenstein ' . ($j + 1));
                    $issues[] = ['severity' => 'error', 'chapter' => 'focus_areas', 'message' => "„{$mName}" . "\" ({$name}) ohne Quartal — fehlt in der Transformation-Map."];
                }
            }
        }

        return [$allOk, $allOk ? null : 'Fokusräume unvollständig (siehe Issues).', $issues];
    }

    private function transformationMap(array $strategy): array
    {
        // Abgeleitet — nie „required". Info, ob überhaupt etwas rendert.
        $n = $this->milestonesWithQuarter($strategy);

        return [true, $n > 0 ? null : 'Noch keine terminierten Meilensteine — Map bleibt leer.', []];
    }

    private function milestonesWithQuarter(array $strategy): int
    {
        $count = 0;
        foreach ($strategy['focus_areas'] ?? [] as $area) {
            foreach ($area['meilensteine'] ?? [] as $m) {
                if ($this->hasQuarter($m['quarter'] ?? null)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function hasQuarter(mixed $q): bool
    {
        return is_array($q) && ! empty($q['year']) && ! empty($q['q']);
    }

    private function filled(?string $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
