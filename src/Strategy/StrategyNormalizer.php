<?php

namespace Platform\Organization\Strategy;

/**
 * Bringt die bestehende Strategie-Struktur (Ausgabe des EntityStrategyPresenter,
 * Model-Form: mehrere Forecasts → FocusAreas) in die flache Blueprint-Form
 * (eine Regnose, Fokusräume als eigenes Kapitel).
 *
 * Übergangs-Adapter: überbrückt den Modell-Mismatch, solange der Modell-Shift
 * (Fokusräume lösen sich von der Regnose) noch nicht migriert ist. Damit läuft
 * StrategyCompleteness schon heute gegen echte Carrier-Daten.
 *   - Regnose      = Inhalt des primären (ersten) Forecasts
 *   - Fokusräume   = flach über ALLE Forecasts
 *   - Meilenstein.quarter = target_year + target_quarter (sonst null)
 */
class StrategyNormalizer
{
    /**
     * @param  array<string,mixed>|null  $presenter  Ausgabe von EntityStrategyPresenter::forEntity()
     * @return array<string,mixed>  Blueprint-Form (siehe StrategyCompleteness)
     */
    public static function fromPresenter(?array $presenter): array
    {
        if (! $presenter) {
            return ['mission' => null, 'vision' => null, 'regnose' => null, 'focus_areas' => []];
        }

        $forecasts = $presenter['forecasts'] ?? [];

        $focusAreas = [];
        foreach ($forecasts as $forecast) {
            foreach ($forecast['focus_areas'] ?? [] as $fa) {
                $focusAreas[] = [
                    'title'        => $fa['title'] ?? null,
                    'zielbilder'   => array_map(fn ($vi) => $vi['title'] ?? '', $fa['vision_images'] ?? []),
                    'hindernisse'  => array_map(fn ($ob) => $ob['title'] ?? '', $fa['obstacles'] ?? []),
                    'meilensteine' => array_map(fn ($m) => [
                        'title'   => $m['title'] ?? null,
                        'quarter' => (! empty($m['target_year']) && ! empty($m['target_quarter']))
                            ? ['year' => (int) $m['target_year'], 'q' => (int) $m['target_quarter']]
                            : null,
                    ], $fa['milestones'] ?? []),
                ];
            }
        }

        return [
            'mission'     => $presenter['mission']['content'] ?? null,
            'vision'      => $presenter['vision']['content'] ?? null,
            'regnose'     => $forecasts[0]['content'] ?? null,
            'focus_areas' => $focusAreas,
        ];
    }
}
