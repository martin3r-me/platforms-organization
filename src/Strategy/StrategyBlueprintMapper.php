<?php

namespace Platform\Organization\Strategy;

/**
 * Projiziert die reiche Strategie-Ansicht (EntityStrategyPresenter::forEntity)
 * auf die schlanke Blueprint-Eval-Form, die StrategyCompleteness erwartet.
 *
 * Bewusst nur an das Array-Shape gekoppelt, nicht an die Presenter-Klasse:
 * der reiche Read bleibt die einzige DB-Quelle, Completeness bewertet eine
 * abgeleitete Projektion davon (Modell-Shift-Konsolidierung — löste den
 * separaten StrategyReader ab).
 *
 * @see \Platform\Organization\Services\EntityStrategyPresenter Rich-Shape (Quelle)
 * @see StrategyCompleteness Ziel-Shape (Verbraucher)
 */
class StrategyBlueprintMapper
{
    /**
     * @param  array<string,mixed>|null  $strategy  Rich-Shape oder null (nichts gepflegt).
     * @return array<string,mixed>  Blueprint-Eval-Form.
     */
    public static function fromStrategyArray(?array $strategy): array
    {
        if ($strategy === null) {
            return ['mission' => null, 'vision' => null, 'regnose' => null, 'focus_areas' => []];
        }

        // Regnose = Inhalt der (frühesten) Regnose; Presenter liefert forecasts nach target_date sortiert.
        $regnose = $strategy['forecasts'][0]['content'] ?? null;

        $focusAreas = array_map(static function (array $fa): array {
            return [
                'title'        => $fa['title'] ?? null,
                'zielbilder'   => array_column($fa['vision_images'] ?? [], 'title'),
                'hindernisse'  => array_column($fa['obstacles'] ?? [], 'title'),
                'meilensteine' => array_map(static function (array $m): array {
                    $hasQuarter = ! empty($m['target_year']) && ! empty($m['target_quarter']);

                    return [
                        'title'   => $m['title'] ?? null,
                        'quarter' => $hasQuarter
                            ? ['year' => (int) $m['target_year'], 'q' => (int) $m['target_quarter']]
                            : null,
                    ];
                }, $fa['milestones'] ?? []),
            ];
        }, $strategy['focus_areas'] ?? []);

        return [
            'mission'     => $strategy['mission']['content'] ?? null,
            'vision'      => $strategy['vision']['content'] ?? null,
            'regnose'     => $regnose,
            'focus_areas' => $focusAreas,
        ];
    }
}
