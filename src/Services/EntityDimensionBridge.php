<?php

namespace Platform\Organization\Services;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationDimensionValue;

class EntityDimensionBridge
{
    protected static ?int $entityDefId = null;
    protected static ?array $entityToDimValue = null;
    protected static ?array $dimValueToEntity = null;

    /**
     * Get the dimension_definition_id for the 'entity' dimension.
     */
    public static function definitionId(): ?int
    {
        if (static::$entityDefId === null) {
            static::$entityDefId = OrganizationDimensionDefinition::where('key', 'entity')->value('id') ?? 0;
        }

        return static::$entityDefId ?: null;
    }

    /**
     * Build lookup maps lazily (once per request).
     */
    protected static function ensureMaps(): void
    {
        if (static::$entityToDimValue !== null) {
            return;
        }

        static::$entityToDimValue = [];
        static::$dimValueToEntity = [];

        $defId = static::definitionId();
        if (!$defId) {
            return;
        }

        $values = OrganizationDimensionValue::where('dimension_definition_id', $defId)->get();

        foreach ($values as $v) {
            $sourceEntityId = $v->metadata['source_entity_id'] ?? null;
            if ($sourceEntityId) {
                static::$entityToDimValue[$sourceEntityId] = $v->id;
                static::$dimValueToEntity[$v->id] = $sourceEntityId;
            }
        }
    }

    /**
     * Translate entity_id → dimension_value_id.
     */
    public static function dimValueId(int $entityId): ?int
    {
        static::ensureMaps();

        return static::$entityToDimValue[$entityId] ?? null;
    }

    /**
     * Translate dimension_value_id → entity_id.
     */
    public static function entityId(int $dimValueId): ?int
    {
        static::ensureMaps();

        return static::$dimValueToEntity[$dimValueId] ?? null;
    }

    /**
     * Reset cached maps (for testing or long-running processes).
     */
    public static function flush(): void
    {
        static::$entityDefId = null;
        static::$entityToDimValue = null;
        static::$dimValueToEntity = null;
    }

    // ─── FORWARD: Entity → Linked Items ──────────────────────────

    /**
     * Get all dimension links for a single entity.
     * Returns Collection of OrganizationDimensionLink with virtual entity_id attribute.
     */
    public static function linksForEntity(int $entityId): Collection
    {
        return static::linksForEntities([$entityId]);
    }

    /**
     * Get all dimension links for multiple entities.
     * Returns Collection of OrganizationDimensionLink with virtual entity_id attribute.
     */
    public static function linksForEntities(array $entityIds): Collection
    {
        $defId = static::definitionId();
        if (!$defId) {
            return collect();
        }

        static::ensureMaps();

        $dimValueIds = [];
        foreach ($entityIds as $eid) {
            $dvId = static::$entityToDimValue[$eid] ?? null;
            if ($dvId) {
                $dimValueIds[] = $dvId;
            }
        }

        if (empty($dimValueIds)) {
            return collect();
        }

        $links = OrganizationDimensionLink::where('dimension_definition_id', $defId)
            ->whereIn('dimension_value_id', $dimValueIds)
            ->get();

        // Attach virtual entity_id
        foreach ($links as $link) {
            $link->entity_id = static::$dimValueToEntity[$link->dimension_value_id] ?? null;
        }

        return $links;
    }

    // ─── REVERSE: Linkable → Entities ────────────────────────────

    /**
     * Get entity links for given linkable types and IDs.
     * Sidebar pattern: find which entities are linked to a set of projects/documents/etc.
     *
     * Returns Collection of OrganizationDimensionLink with virtual entity_id,
     * optionally with entity.type eager-loaded.
     */
    public static function linksForLinkables(array $linkableTypes, array $linkableIds, bool $withEntity = true): Collection
    {
        $defId = static::definitionId();
        if (!$defId || empty($linkableTypes) || empty($linkableIds)) {
            return collect();
        }

        static::ensureMaps();

        $query = OrganizationDimensionLink::where('dimension_definition_id', $defId)
            ->whereIn('linkable_type', $linkableTypes)
            ->whereIn('linkable_id', $linkableIds);

        if ($withEntity) {
            $query->with(['value']);
        }

        $links = $query->get();

        // Attach virtual entity_id + eager-load entity if requested
        $entityIds = [];
        foreach ($links as $link) {
            $eid = static::$dimValueToEntity[$link->dimension_value_id] ?? null;
            $link->entity_id = $eid;
            if ($eid) {
                $entityIds[] = $eid;
            }
        }

        if ($withEntity && !empty($entityIds)) {
            $entities = \Platform\Organization\Models\OrganizationEntity::whereIn('id', array_unique($entityIds))
                ->with('type')
                ->get()
                ->keyBy('id');

            foreach ($links as $link) {
                $link->setRelation('entity', $entities->get($link->entity_id));
            }
        }

        return $links;
    }

    /**
     * Get entity links filtered by linkable_type only (no ID filter).
     * Used when you need all links for certain morph types (e.g. TimeEntries context map).
     *
     * Returns Collection of OrganizationDimensionLink with virtual entity_id
     * and entity.type eager-loaded.
     */
    public static function linksForLinkableTypes(array $linkableTypes): Collection
    {
        $defId = static::definitionId();
        if (!$defId || empty($linkableTypes)) {
            return collect();
        }

        static::ensureMaps();

        $links = OrganizationDimensionLink::where('dimension_definition_id', $defId)
            ->whereIn('linkable_type', $linkableTypes)
            ->get();

        $entityIds = [];
        foreach ($links as $link) {
            $eid = static::$dimValueToEntity[$link->dimension_value_id] ?? null;
            $link->entity_id = $eid;
            if ($eid) {
                $entityIds[] = $eid;
            }
        }

        if (!empty($entityIds)) {
            $entities = \Platform\Organization\Models\OrganizationEntity::whereIn('id', array_unique($entityIds))
                ->with('type')
                ->get()
                ->keyBy('id');

            foreach ($links as $link) {
                $link->setRelation('entity', $entities->get($link->entity_id));
            }
        }

        return $links;
    }

    // ─── WRITE ───────────────────────────────────────────────────

    /**
     * Create a dimension link for an entity → linkable.
     */
    public static function createLink(int $entityId, string $linkableType, int $linkableId, array $meta = []): ?OrganizationDimensionLink
    {
        $defId = static::definitionId();
        if (!$defId) {
            return null;
        }

        $dvId = static::dimValueId($entityId);
        if (!$dvId) {
            // Auto-create dimension value for entity
            $dvId = static::ensureDimValueForEntity($entityId);
            if (!$dvId) {
                return null;
            }
        }

        return OrganizationDimensionLink::create(array_merge([
            'dimension_definition_id' => $defId,
            'dimension_value_id' => $dvId,
            'linkable_type' => $linkableType,
            'linkable_id' => $linkableId,
            'team_id' => auth()->check() ? auth()->user()->currentTeam?->id : null,
            'created_by_user_id' => auth()->id(),
        ], $meta));
    }

    /**
     * Delete a specific entity → linkable link.
     */
    public static function deleteLink(int $entityId, string $linkableType, int $linkableId): bool
    {
        $defId = static::definitionId();
        if (!$defId) {
            return false;
        }

        $dvId = static::dimValueId($entityId);
        if (!$dvId) {
            return false;
        }

        return OrganizationDimensionLink::where('dimension_definition_id', $defId)
            ->where('dimension_value_id', $dvId)
            ->where('linkable_type', $linkableType)
            ->where('linkable_id', $linkableId)
            ->delete() > 0;
    }

    /**
     * Replace all entity links for a linkable with a single new entity link.
     * Used by UpdateDocumentTool pattern: delete old links → create new one.
     */
    public static function replaceLinks(string $linkableType, int $linkableId, int $newEntityId, array $meta = []): void
    {
        $defId = static::definitionId();
        if (!$defId) {
            return;
        }

        OrganizationDimensionLink::where('dimension_definition_id', $defId)
            ->where('linkable_type', $linkableType)
            ->where('linkable_id', $linkableId)
            ->delete();

        static::createLink($newEntityId, $linkableType, $linkableId, $meta);
    }

    // ─── AGGREGATE ───────────────────────────────────────────────

    /**
     * Count links grouped by entity_id and linkable_type (morph alias).
     * Returns: [entity_id => [morphAlias => count]]
     */
    public static function linkCountsByEntityAndType(array $entityIds): array
    {
        $links = static::linksForEntities($entityIds);
        $reverseMorphMap = array_flip(Relation::morphMap());

        $result = [];
        foreach ($links as $link) {
            $eid = $link->entity_id;
            if (!$eid) {
                continue;
            }
            $type = $reverseMorphMap[$link->linkable_type] ?? $link->linkable_type;
            $result[$eid][$type] = ($result[$eid][$type] ?? 0) + 1;
        }

        return $result;
    }

    /**
     * Total link count for a set of entity IDs.
     */
    public static function totalLinkCount(array $entityIds): int
    {
        $defId = static::definitionId();
        if (!$defId) {
            return 0;
        }

        static::ensureMaps();

        $dimValueIds = [];
        foreach ($entityIds as $eid) {
            $dvId = static::$entityToDimValue[$eid] ?? null;
            if ($dvId) {
                $dimValueIds[] = $dvId;
            }
        }

        if (empty($dimValueIds)) {
            return 0;
        }

        return OrganizationDimensionLink::where('dimension_definition_id', $defId)
            ->whereIn('dimension_value_id', $dimValueIds)
            ->count();
    }

    // ─── HELPERS ─────────────────────────────────────────────────

    /**
     * Ensure a DimensionValue exists for the given entity_id.
     * Auto-creates one if missing (e.g. entity was created after migration).
     */
    protected static function ensureDimValueForEntity(int $entityId): ?int
    {
        $defId = static::definitionId();
        if (!$defId) {
            return null;
        }

        $entity = \Platform\Organization\Models\OrganizationEntity::find($entityId);
        if (!$entity) {
            return null;
        }

        $dv = OrganizationDimensionValue::create([
            'dimension_definition_id' => $defId,
            'code' => $entity->code ?? "entity-{$entityId}",
            'name' => $entity->name,
            'team_id' => $entity->team_id,
            'is_active' => true,
            'metadata' => ['source_entity_id' => $entityId],
        ]);

        // Update cache
        static::$entityToDimValue[$entityId] = $dv->id;
        static::$dimValueToEntity[$dv->id] = $entityId;

        return $dv->id;
    }
}
