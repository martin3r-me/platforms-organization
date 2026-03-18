<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationCostCenterLink;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;

class DimensionLinkService
{
    /**
     * Registry aller verfügbaren Dimensionen.
     */
    public static function getDimensions(): array
    {
        return [
            'cost-centers' => [
                'model' => OrganizationCostCenter::class,
                'link_model' => OrganizationCostCenterLink::class,
                'fk' => 'cost_center_id',
                'label' => 'Kostenstellen',
                'mode' => 'multi_percent',
            ],
            'entities' => [
                'model' => OrganizationEntity::class,
                'link_model' => OrganizationEntityLink::class,
                'fk' => 'entity_id',
                'label' => 'Organisationseinheiten',
                'mode' => 'multi',
            ],
        ];
    }

    public static function getDimension(string $key): ?array
    {
        return self::getDimensions()[$key] ?? null;
    }

    /**
     * Linked Items für einen Kontext + Dimension holen.
     */
    public function getLinked(string $dimension, string $contextType, int $contextId): Collection
    {
        $cfg = self::getDimension($dimension);
        if (!$cfg) {
            return collect();
        }

        $linkModel = $cfg['link_model'];
        $fk = $cfg['fk'];
        $dimensionModel = $cfg['model'];

        $links = $linkModel::where('linkable_type', $contextType)
            ->where('linkable_id', $contextId)
            ->get();

        $linksByFk = $links->keyBy($fk);
        $ids = $links->pluck($fk)->unique()->toArray();

        return $dimensionModel::whereIn('id', $ids)
            ->orderBy('name')
            ->get()
            ->map(function ($item) use ($linksByFk) {
                $link = $linksByFk->get($item->id);
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->name,
                    'percentage' => $link?->percentage ? (float) $link->percentage : null,
                    'is_primary' => (bool) ($link?->is_primary ?? false),
                ];
            });
    }

    /**
     * Reverse: Alle verknüpften Kontexte für ein Dimensions-Element holen.
     * "Zeig mir alles was an Kunde 5 hängt."
     */
    public function getLinkedContexts(string $dimension, int $dimensionItemId): Collection
    {
        $cfg = self::getDimension($dimension);
        if (!$cfg) {
            return collect();
        }

        $linkModel = $cfg['link_model'];
        $fk = $cfg['fk'];

        $links = $linkModel::where($fk, $dimensionItemId)->get();

        // Gruppiere nach linkable_type und lade die tatsächlichen Models
        $grouped = $links->groupBy('linkable_type');

        $results = [];
        foreach ($grouped as $morphType => $typeLinks) {
            $modelClass = Relation::getMorphedModel($morphType) ?? $morphType;
            $ids = $typeLinks->pluck('linkable_id')->unique()->toArray();

            $label = class_basename($modelClass);
            $items = [];

            // Versuche die Models zu laden für lesbare Namen
            if (class_exists($modelClass)) {
                $models = $modelClass::whereIn('id', $ids)->get()->keyBy('id');
                foreach ($typeLinks as $link) {
                    $model = $models->get($link->linkable_id);
                    $items[] = [
                        'id' => $link->linkable_id,
                        'name' => $model?->name ?? $model?->title ?? "#{$link->linkable_id}",
                        'percentage' => $link->percentage ? (float) $link->percentage : null,
                        'is_primary' => (bool) ($link->is_primary ?? false),
                    ];
                }
            } else {
                // Model-Klasse nicht verfügbar – nur IDs zurückgeben
                foreach ($typeLinks as $link) {
                    $items[] = [
                        'id' => $link->linkable_id,
                        'name' => "#{$link->linkable_id}",
                        'percentage' => $link->percentage ? (float) $link->percentage : null,
                        'is_primary' => (bool) ($link->is_primary ?? false),
                    ];
                }
            }

            $results[] = [
                'linkable_type' => $morphType,
                'model_class' => $modelClass,
                'label' => $label,
                'items' => $items,
                'count' => count($items),
            ];
        }

        return collect($results);
    }

    /**
     * Link erstellen. Respektiert den Mode (single = ersetzt vorherigen).
     */
    public function link(string $dimension, string $contextType, int $contextId, int $dimensionItemId, array $meta = []): bool
    {
        $cfg = self::getDimension($dimension);
        if (!$cfg) {
            return false;
        }

        $linkModel = $cfg['link_model'];
        $fk = $cfg['fk'];

        // Single-Mode: vorherigen Link entfernen
        if ($cfg['mode'] === 'single') {
            $linkModel::where('linkable_type', $contextType)
                ->where('linkable_id', $contextId)
                ->delete();
        }

        // Duplikat-Check
        $exists = $linkModel::where($fk, $dimensionItemId)
            ->where('linkable_type', $contextType)
            ->where('linkable_id', $contextId)
            ->exists();

        if ($exists) {
            return false;
        }

        $linkModel::create([
            $fk => $dimensionItemId,
            'linkable_type' => $contextType,
            'linkable_id' => $contextId,
            'percentage' => $meta['percentage'] ?? null,
            'is_primary' => $meta['is_primary'] ?? false,
            'start_date' => $meta['start_date'] ?? null,
            'end_date' => $meta['end_date'] ?? null,
            'team_id' => $meta['team_id'] ?? auth()->user()?->currentTeam?->id,
            'created_by_user_id' => $meta['created_by_user_id'] ?? auth()->id(),
        ]);

        return true;
    }

    /**
     * Link entfernen.
     */
    public function unlink(string $dimension, string $contextType, int $contextId, int $dimensionItemId): bool
    {
        $cfg = self::getDimension($dimension);
        if (!$cfg) {
            return false;
        }

        $linkModel = $cfg['link_model'];
        $fk = $cfg['fk'];

        $deleted = $linkModel::where($fk, $dimensionItemId)
            ->where('linkable_type', $contextType)
            ->where('linkable_id', $contextId)
            ->delete();

        return $deleted > 0;
    }
}
