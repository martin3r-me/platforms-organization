<?php

namespace Platform\Organization\Strategy;

use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationFocusArea;
use Platform\Organization\Models\OrganizationForecast;
use Platform\Organization\Models\OrganizationStrategicDocument;

/**
 * Kanonischer, entity-nativer Strategie-Reader (Modell-Shift).
 *
 * Liest direkt in Blueprint-Form — Fokusräume über entity_id (nicht mehr via
 * Forecast), eine Regnose (der Forecast des Carriers). Löst mittelfristig den
 * Übergangs-Adapter StrategyNormalizer(→Presenter) ab.
 *
 *   Mission/Vision = aktives StrategicDocument
 *   Regnose        = Inhalt des (primären) Forecasts
 *   Fokusräume     = OrganizationFocusArea WHERE entity_id = Carrier (flach)
 *   Meilenstein.quarter = target_year + target_quarter (sonst null)
 */
class StrategyReader
{
    /** @return array<string,mixed>  Blueprint-Form (siehe StrategyCompleteness). */
    public static function forEntity(OrganizationEntity $entity): array
    {
        return [
            'mission'     => self::activeDoc($entity->id, 'mission'),
            'vision'      => self::activeDoc($entity->id, 'vision'),
            'regnose'     => self::regnose($entity->id),
            'focus_areas' => self::focusAreas($entity->id),
        ];
    }

    private static function activeDoc(int $entityId, string $type): ?string
    {
        return OrganizationStrategicDocument::query()
            ->where('entity_id', $entityId)
            ->where('type', $type)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderByDesc('version')
            ->value('content');
    }

    private static function regnose(int $entityId): ?string
    {
        $forecast = OrganizationForecast::query()
            ->where('entity_id', $entityId)
            ->whereNull('deleted_at')
            ->orderBy('target_date')
            ->with('currentVersion')
            ->first();

        return $forecast ? ($forecast->currentVersion?->content ?? $forecast->content) : null;
    }

    /** @return array<int, array<string,mixed>> */
    private static function focusAreas(int $entityId): array
    {
        return OrganizationFocusArea::query()
            ->where('entity_id', $entityId)
            ->whereNull('deleted_at')
            ->orderBy('order')
            ->with([
                'visionImages' => fn ($q) => $q->whereNull('deleted_at')->orderBy('order'),
                'obstacles'    => fn ($q) => $q->whereNull('deleted_at')->orderBy('order'),
                'milestones'   => fn ($q) => $q->whereNull('deleted_at')
                    ->orderBy('target_year')->orderBy('target_quarter')->orderBy('order'),
            ])
            ->get()
            ->map(fn ($fa) => [
                'title'        => $fa->title,
                'zielbilder'   => $fa->visionImages->pluck('title')->all(),
                'hindernisse'  => $fa->obstacles->pluck('title')->all(),
                'meilensteine' => $fa->milestones->map(fn ($m) => [
                    'title'   => $m->title,
                    'quarter' => ($m->target_year && $m->target_quarter)
                        ? ['year' => (int) $m->target_year, 'q' => (int) $m->target_quarter]
                        : null,
                ])->all(),
            ])->all();
    }
}
