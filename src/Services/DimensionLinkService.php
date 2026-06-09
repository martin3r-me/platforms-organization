<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationCostCenterLink;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationDimensionValue;

class DimensionLinkService
{
    /**
     * Resolve a context type to the canonical morph alias.
     *
     * Accepts morph aliases ("organization_process"), full class names
     * ("Platform\Process\Models\Process"), or short names ("process").
     * Returns the registered morph alias so that stored linkable_type
     * values are always consistent with what Sidebar/EntityDimensionBridge
     * queries expect.
     */
    public static function resolveContextType(string $contextType): string
    {
        // 1. Already a known morph alias → use as-is
        if (Relation::getMorphedModel($contextType)) {
            return $contextType;
        }

        $morphMap = Relation::morphMap();

        // 2. Full class name → resolve to its alias
        $alias = array_search($contextType, $morphMap, true);
        if ($alias !== false) {
            return $alias;
        }

        // 3. Composite name where the suffix is itself a registered alias
        //    e.g. "planner_project" → "project" (when the morph map registers "project")
        if (str_contains($contextType, '_')) {
            $parts = explode('_', $contextType);
            $suffix = end($parts);
            if (Relation::getMorphedModel($suffix)) {
                return $suffix;
            }
        }

        // 4. Short name fallback: find a unique morph alias ending with _<contextType>
        //    e.g. "process" matches "organization_process"
        $candidates = [];
        foreach ($morphMap as $a => $class) {
            if ($a === $contextType || str_ends_with($a, '_' . $contextType)) {
                $candidates[] = $a;
            }
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        // No match or ambiguous — return as-is (caller's responsibility)
        return $contextType;
    }

    /**
     * Legacy registry — kept for backward compatibility with existing
     * cost-center and entity link tables. New dimensions use the
     * generic dimension_definitions/dimension_links tables exclusively.
     */
    private static function getLegacyDimensions(): array
    {
        return [
            'cost-centers' => [
                'model' => OrganizationCostCenter::class,
                'link_model' => OrganizationCostCenterLink::class,
                'fk' => 'cost_center_id',
                'label' => 'Kostenstellen',
                'mode' => 'multi_percent',
            ],
        ];
    }

    /**
     * Get all available dimensions — legacy + generic.
     */
    public static function getDimensions(): array
    {
        $dimensions = self::getLegacyDimensions();

        // Add all generic dimension definitions (excluding keys that overlap with legacy)
        $definitions = OrganizationDimensionDefinition::active()->ordered()->get();
        foreach ($definitions as $def) {
            if (!isset($dimensions[$def->key])) {
                $dimensions[$def->key] = [
                    'definition_id' => $def->id,
                    'label' => $def->name,
                    'mode' => $def->mode,
                    'generic' => true,
                ];
            }
        }

        return $dimensions;
    }

    public static function getDimension(string $key): ?array
    {
        return self::getDimensions()[$key] ?? null;
    }

    /**
     * Check if a dimension uses the generic (new) system.
     */
    private static function isGeneric(string $key): bool
    {
        $legacy = self::getLegacyDimensions();
        if (isset($legacy[$key])) {
            return false;
        }

        return OrganizationDimensionDefinition::where('key', $key)->exists();
    }

    /**
     * Linked Items für einen Kontext + Dimension holen.
     * Supports optional perspective_id for perspective-aware lookups.
     */
    public function getLinked(string $dimension, string $contextType, int $contextId, ?int $perspectiveId = null): Collection
    {
        $contextType = self::resolveContextType($contextType);

        if (self::isGeneric($dimension)) {
            return $this->getLinkedGeneric($dimension, $contextType, $contextId, $perspectiveId);
        }

        // Legacy path
        $cfg = self::getLegacyDimensions()[$dimension] ?? null;
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
     * Generic dimension lookup via dimension_links table.
     */
    private function getLinkedGeneric(string $dimensionKey, string $contextType, int $contextId, ?int $perspectiveId = null): Collection
    {
        $def = OrganizationDimensionDefinition::findByKey($dimensionKey);
        if (!$def) {
            return collect();
        }

        $query = OrganizationDimensionLink::where('dimension_definition_id', $def->id)
            ->where('linkable_type', $contextType)
            ->where('linkable_id', $contextId);


        $links = $query->with('value')->get();

        return $links->map(function ($link) {
            return [
                'id' => $link->dimension_value_id,
                'code' => $link->value?->code,
                'name' => $link->value?->name,
                'percentage' => $link->percentage ? (float) $link->percentage : null,
                'is_primary' => (bool) $link->is_primary,
                'perspective_id' => $link->perspective_id,
            ];
        });
    }

    /**
     * Reverse: Alle verknüpften Kontexte für ein Dimensions-Element holen.
     */
    public function getLinkedContexts(string $dimension, int $dimensionItemId, ?int $perspectiveId = null): Collection
    {
        // Note: no resolveContextType here — this is a reverse lookup that returns
        // all linkable_types, not a query filtered by one.
        if (self::isGeneric($dimension)) {
            return $this->getLinkedContextsGeneric($dimension, $dimensionItemId, $perspectiveId);
        }

        // Legacy path
        $cfg = self::getLegacyDimensions()[$dimension] ?? null;
        if (!$cfg) {
            return collect();
        }

        $linkModel = $cfg['link_model'];
        $fk = $cfg['fk'];

        $links = $linkModel::where($fk, $dimensionItemId)->get();

        $grouped = $links->groupBy('linkable_type');

        $results = [];
        foreach ($grouped as $morphType => $typeLinks) {
            $modelClass = Relation::getMorphedModel($morphType) ?? $morphType;
            $ids = $typeLinks->pluck('linkable_id')->unique()->toArray();

            $label = class_basename($modelClass);
            $items = [];

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
     * Generic reverse lookup.
     */
    private function getLinkedContextsGeneric(string $dimensionKey, int $dimensionValueId, ?int $perspectiveId = null): Collection
    {
        $def = OrganizationDimensionDefinition::findByKey($dimensionKey);
        if (!$def) {
            return collect();
        }

        $query = OrganizationDimensionLink::where('dimension_definition_id', $def->id)
            ->where('dimension_value_id', $dimensionValueId);


        $links = $query->get();
        $grouped = $links->groupBy('linkable_type');

        $results = [];
        foreach ($grouped as $morphType => $typeLinks) {
            $modelClass = Relation::getMorphedModel($morphType) ?? $morphType;
            $ids = $typeLinks->pluck('linkable_id')->unique()->toArray();
            $label = class_basename($modelClass);
            $items = [];

            if (class_exists($modelClass)) {
                $models = $modelClass::whereIn('id', $ids)->get()->keyBy('id');
                foreach ($typeLinks as $link) {
                    $model = $models->get($link->linkable_id);
                    $items[] = [
                        'id' => $link->linkable_id,
                        'name' => $model?->name ?? $model?->title ?? "#{$link->linkable_id}",
                        'percentage' => $link->percentage ? (float) $link->percentage : null,
                        'is_primary' => (bool) $link->is_primary,
                        'perspective_id' => $link->perspective_id,
                    ];
                }
            } else {
                foreach ($typeLinks as $link) {
                    $items[] = [
                        'id' => $link->linkable_id,
                        'name' => "#{$link->linkable_id}",
                        'percentage' => $link->percentage ? (float) $link->percentage : null,
                        'is_primary' => (bool) $link->is_primary,
                        'perspective_id' => $link->perspective_id,
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
        $contextType = self::resolveContextType($contextType);

        if (self::isGeneric($dimension)) {
            return $this->linkGeneric($dimension, $contextType, $contextId, $dimensionItemId, $meta);
        }

        // Legacy path
        $cfg = self::getLegacyDimensions()[$dimension] ?? null;
        if (!$cfg) {
            return false;
        }

        $linkModel = $cfg['link_model'];
        $fk = $cfg['fk'];

        if ($cfg['mode'] === 'single') {
            $linkModel::where('linkable_type', $contextType)
                ->where('linkable_id', $contextId)
                ->delete();
        }

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
     * Generic link creation via dimension_links table.
     */
    private function linkGeneric(string $dimensionKey, string $contextType, int $contextId, int $dimensionValueId, array $meta = []): bool
    {
        $def = OrganizationDimensionDefinition::findByKey($dimensionKey);
        if (!$def) {
            return false;
        }

        $perspectiveId = $meta['perspective_id'] ?? null;

        // Single-Mode: remove previous link for this dimension+linkable+perspective
        if ($def->mode === 'single') {
            OrganizationDimensionLink::where('dimension_definition_id', $def->id)
                ->where('linkable_type', $contextType)
                ->where('linkable_id', $contextId)
                ->where('perspective_id', $perspectiveId)
                ->delete();
        }

        // Duplicate check
        $exists = OrganizationDimensionLink::where('dimension_definition_id', $def->id)
            ->where('dimension_value_id', $dimensionValueId)
            ->where('linkable_type', $contextType)
            ->where('linkable_id', $contextId)
            ->where('perspective_id', $perspectiveId)
            ->exists();

        if ($exists) {
            return false;
        }

        OrganizationDimensionLink::create([
            'dimension_definition_id' => $def->id,
            'dimension_value_id' => $dimensionValueId,
            'linkable_type' => $contextType,
            'linkable_id' => $contextId,
            'perspective_id' => $perspectiveId,
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
    public function unlink(string $dimension, string $contextType, int $contextId, int $dimensionItemId, ?int $perspectiveId = null): bool
    {
        $contextType = self::resolveContextType($contextType);

        if (self::isGeneric($dimension)) {
            return $this->unlinkGeneric($dimension, $contextType, $contextId, $dimensionItemId, $perspectiveId);
        }

        // Legacy path
        $cfg = self::getLegacyDimensions()[$dimension] ?? null;
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

    /**
     * Generic unlink.
     */
    private function unlinkGeneric(string $dimensionKey, string $contextType, int $contextId, int $dimensionValueId, ?int $perspectiveId = null): bool
    {
        $def = OrganizationDimensionDefinition::findByKey($dimensionKey);
        if (!$def) {
            return false;
        }

        $query = OrganizationDimensionLink::where('dimension_definition_id', $def->id)
            ->where('dimension_value_id', $dimensionValueId)
            ->where('linkable_type', $contextType)
            ->where('linkable_id', $contextId);

        if ($perspectiveId) {
            $query->where('perspective_id', $perspectiveId);
        }

        return $query->delete() > 0;
    }
}
