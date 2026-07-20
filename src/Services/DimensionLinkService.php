<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionLink;

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
     * Alle verfügbaren Dimensionen (rein generisch über dimension_definitions).
     * Kostenstellen sind keine eigene Dimension mehr — sie sind Fremd-IDs an
     * Entities (organization_entity_external_ids) und werden über die
     * entity-Dimension verlinkt.
     */
    public static function getDimensions(): array
    {
        $dimensions = [];

        foreach (OrganizationDimensionDefinition::active()->ordered()->get() as $def) {
            $dimensions[$def->key] = [
                'definition_id' => $def->id,
                'label' => $def->name,
                'mode' => $def->mode,
                'generic' => true,
            ];
        }

        return $dimensions;
    }

    public static function getDimension(string $key): ?array
    {
        return self::getDimensions()[$key] ?? null;
    }

    /**
     * Linked Items für einen Kontext + Dimension holen.
     */
    public function getLinked(string $dimension, string $contextType, int $contextId, ?int $perspectiveId = null): Collection
    {
        $contextType = self::resolveContextType($contextType);

        $def = OrganizationDimensionDefinition::findByKey($dimension);
        if (!$def) {
            return collect();
        }

        $links = OrganizationDimensionLink::where('dimension_definition_id', $def->id)
            ->where('linkable_type', $contextType)
            ->where('linkable_id', $contextId)
            ->with('value')
            ->get();

        $isEntityBased = ($def->value_source === 'entity');

        return $links->map(function ($link) use ($isEntityBased) {
            $entry = [
                // LLM-Hinweis: 'id' = dimension_value_id (interne ID).
                // Fuer entity-basierte Dimensionen ist 'entity_id' die natuerliche
                // Referenz — IMMER fuer DELETE/POST/Bezugnahmen den entity_id
                // benutzen, nicht 'id'.
                'id' => $link->dimension_value_id,
                'dim_value_id' => $link->dimension_value_id,
                'code' => $link->value?->code,
                'name' => $link->value?->name,
                'percentage' => $link->percentage ? (float) $link->percentage : null,
                'is_primary' => (bool) $link->is_primary,
            ];

            if ($isEntityBased) {
                $meta = $link->value?->metadata ?? [];
                if (! is_array($meta)) {
                    $meta = [];
                }
                $entry['entity_id'] = $meta['source_entity_id'] ?? null;
            }

            return $entry;
        });
    }

    /**
     * Reverse: Alle verknüpften Kontexte für ein Dimensions-Element holen.
     */
    public function getLinkedContexts(string $dimension, int $dimensionValueId, ?int $perspectiveId = null): Collection
    {
        $def = OrganizationDimensionDefinition::findByKey($dimension);
        if (!$def) {
            return collect();
        }

        $links = OrganizationDimensionLink::where('dimension_definition_id', $def->id)
            ->where('dimension_value_id', $dimensionValueId)
            ->get();

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
                    ];
                }
            } else {
                foreach ($typeLinks as $link) {
                    $items[] = [
                        'id' => $link->linkable_id,
                        'name' => "#{$link->linkable_id}",
                        'percentage' => $link->percentage ? (float) $link->percentage : null,
                        'is_primary' => (bool) $link->is_primary,
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
    public function link(string $dimension, string $contextType, int $contextId, int $dimensionValueId, array $meta = []): bool
    {
        $contextType = self::resolveContextType($contextType);

        $def = OrganizationDimensionDefinition::findByKey($dimension);
        if (!$def) {
            return false;
        }

        // Single-Mode: vorherigen Link für diese Dimension+Linkable entfernen.
        if ($def->mode === 'single') {
            OrganizationDimensionLink::where('dimension_definition_id', $def->id)
                ->where('linkable_type', $contextType)
                ->where('linkable_id', $contextId)
                ->delete();
        }

        $exists = OrganizationDimensionLink::where('dimension_definition_id', $def->id)
            ->where('dimension_value_id', $dimensionValueId)
            ->where('linkable_type', $contextType)
            ->where('linkable_id', $contextId)
            ->exists();

        if ($exists) {
            return false;
        }

        OrganizationDimensionLink::create([
            'dimension_definition_id' => $def->id,
            'dimension_value_id' => $dimensionValueId,
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
    public function unlink(string $dimension, string $contextType, int $contextId, int $dimensionValueId, ?int $perspectiveId = null): bool
    {
        $contextType = self::resolveContextType($contextType);

        $def = OrganizationDimensionDefinition::findByKey($dimension);
        if (!$def) {
            return false;
        }

        return OrganizationDimensionLink::where('dimension_definition_id', $def->id)
            ->where('dimension_value_id', $dimensionValueId)
            ->where('linkable_type', $contextType)
            ->where('linkable_id', $contextId)
            ->delete() > 0;
    }
}
