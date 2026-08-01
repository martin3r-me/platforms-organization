<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationFocusArea;
use Platform\Organization\Models\OrganizationForecast;
use Platform\Organization\Models\OrganizationStrategicDocument;

/**
 * Baut die Strategie-Ansicht einer Carrier-Entity zusammen (Mission/Vision,
 * Fokusraeume, Transformations-Map, Regnosen). Einzige Quelle für den
 * eingeloggten Strategie-Tab (Entity\Show) UND die öffentliche Teil-Ansicht,
 * damit beide dieselbe Struktur rendern.
 *
 * Modell-Shift: Fokusräume hängen entity-nativ (über entity_id), nicht mehr an
 * einer Regnose. forecast_id ist nur noch ein optionaler Soft-Link und wird für
 * die Darstellung nicht als Gruppierung genutzt. Regnosen (Forecasts) sind
 * eigenständige Erzähl-Blöcke; es kann keine, eine oder mehrere geben.
 *
 * Rückgabe-Shape:
 * [
 *   'mission'            => ['title','content','version','valid_from'] | null,
 *   'vision'             => ['title','content','version','valid_from'] | null,
 *   'focus_areas'        => [ ...pro FA (siehe unten), entity-nativ, flach ],
 *   'transformation_map' => ['years' => [int,...], 'grid' => [fa_id => [year => [m,...]]], 'no_year' => [fa_id => [m,...]]],
 *   'forecasts'          => [ ...pro Regnose ],
 *   'milestone_total'    => int,
 *   'has_any'            => bool,
 * ]
 *
 * Eine focus_area hat:
 *   id, title, description, order, vision_images => [{id,title}], obstacles => [{id,title}],
 *   milestones => [{id,title,target_year,target_quarter,order}]
 *
 * Ein forecast (Regnose) hat:
 *   id, title, target_date, content, current_version
 */
class EntityStrategyPresenter
{
    public static function forEntity(OrganizationEntity $entity): ?array
    {
        $activeDoc = fn (string $type) => OrganizationStrategicDocument::query()
            ->where('entity_id', $entity->id)
            ->where('type', $type)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('version')
            ->first();

        $formatDoc = function ($doc) {
            if (! $doc) {
                return null;
            }

            return [
                'title'      => $doc->title,
                'content'    => $doc->content,
                'version'    => $doc->version,
                'valid_from' => $doc->valid_from?->toDateString(),
            ];
        };

        $mission = $formatDoc($activeDoc('mission'));
        $vision  = $formatDoc($activeDoc('vision'));

        // ── Fokusräume entity-nativ (Modell-Shift) ─────────────────
        $focusAreaModels = OrganizationFocusArea::query()
            ->where('entity_id', $entity->id)
            ->whereNull('deleted_at')
            ->with([
                'visionImages' => fn ($q) => $q->whereNull('deleted_at')->orderBy('order'),
                'obstacles'    => fn ($q) => $q->whereNull('deleted_at')->orderBy('order'),
                'milestones'   => fn ($q) => $q->whereNull('deleted_at')
                    ->orderBy('target_year')->orderBy('target_quarter')->orderBy('order'),
            ])
            ->orderBy('order')
            ->get();

        $milestoneTotal = 0;
        $years = [];
        $grid = [];
        $noYear = [];

        $focusAreas = $focusAreaModels->map(function ($fa) use (&$milestoneTotal, &$years, &$grid, &$noYear) {
            $milestones = $fa->milestones->map(function ($m) {
                return [
                    'id'             => $m->id,
                    'title'          => $m->title,
                    'target_year'    => $m->target_year !== null ? (int) $m->target_year : null,
                    'target_quarter' => $m->target_quarter !== null ? (int) $m->target_quarter : null,
                    'order'          => (int) $m->order,
                ];
            })->values()->toArray();

            foreach ($milestones as $m) {
                if ($m['target_year']) {
                    $years[$m['target_year']] = true;
                    $grid[$fa->id][$m['target_year']][] = $m;
                } else {
                    $noYear[$fa->id][] = $m;
                }
            }
            $milestoneTotal += count($milestones);

            return [
                'id'            => $fa->id,
                'title'         => $fa->title,
                'description'   => $fa->description,
                'order'         => (int) $fa->order,
                'vision_images' => $fa->visionImages->map(fn ($vi) => ['id' => $vi->id, 'title' => $vi->title])->values()->toArray(),
                'obstacles'     => $fa->obstacles->map(fn ($ob) => ['id' => $ob->id, 'title' => $ob->title])->values()->toArray(),
                'milestones'    => $milestones,
            ];
        })->values()->toArray();

        ksort($years);

        $transformationMap = [
            'years'   => array_keys($years),
            'grid'    => $grid,
            'no_year' => $noYear,
        ];

        // ── Regnosen (Forecasts) als eigenständige Erzähl-Blöcke ────
        $forecastData = OrganizationForecast::query()
            ->where('entity_id', $entity->id)
            ->whereNull('deleted_at')
            ->with('currentVersion')
            ->orderBy('target_date')
            ->get()
            ->map(fn ($f) => [
                'id'              => $f->id,
                'title'           => $f->title,
                'target_date'     => $f->target_date?->toDateString(),
                'content'         => $f->currentVersion?->content ?? $f->content,
                'current_version' => $f->currentVersion?->version,
            ])
            ->values()
            ->toArray();

        $hasAny = $mission || $vision || ! empty($focusAreas) || ! empty($forecastData);
        if (! $hasAny) {
            return null;
        }

        return [
            'mission'            => $mission,
            'vision'             => $vision,
            'focus_areas'        => $focusAreas,
            'transformation_map' => $transformationMap,
            'forecasts'          => $forecastData,
            'milestone_total'    => $milestoneTotal,
            'has_any'            => true,
        ];
    }
}
