<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityHierarchy;
use Platform\Organization\Models\OrganizationPerspective;

class EntityHierarchyResolver
{
    /**
     * Check if a perspective uses the default (entity-column) hierarchy.
     */
    public function isDefaultHierarchy(OrganizationPerspective $perspective): bool
    {
        return $perspective->is_default;
    }

    /**
     * Get all entity IDs that belong to a given perspective.
     * Default perspective: all team entities.
     * Non-default: only those with entries in hierarchy table.
     */
    public function entityIdsInPerspective(OrganizationPerspective $perspective, int $teamId): Collection
    {
        if ($this->isDefaultHierarchy($perspective)) {
            return OrganizationEntity::forTeam($teamId)->pluck('id');
        }

        return OrganizationEntityHierarchy::where('perspective_id', $perspective->id)
            ->where('team_id', $teamId)
            ->pluck('entity_id');
    }

    /**
     * Returns [entity_id => parent_entity_id|null] for a perspective.
     * Default perspective: reads from entity column.
     * Non-default: reads from hierarchy table.
     */
    public function getParentMap(OrganizationPerspective $perspective, int $teamId): array
    {
        if ($this->isDefaultHierarchy($perspective)) {
            return OrganizationEntity::forTeam($teamId)
                ->pluck('parent_entity_id', 'id')
                ->toArray();
        }

        return OrganizationEntityHierarchy::where('perspective_id', $perspective->id)
            ->where('team_id', $teamId)
            ->pluck('parent_entity_id', 'entity_id')
            ->toArray();
    }

    /**
     * Returns [parent_id => [child_ids]] for a perspective.
     */
    public function getChildMap(OrganizationPerspective $perspective, int $teamId): array
    {
        $parentMap = $this->getParentMap($perspective, $teamId);
        $childMap = [];

        foreach ($parentMap as $entityId => $parentId) {
            if ($parentId !== null) {
                $childMap[$parentId][] = $entityId;
            }
        }

        return $childMap;
    }

    /**
     * Returns entity IDs that are roots (no parent) in a given perspective.
     */
    public function getRootIds(OrganizationPerspective $perspective, int $teamId): array
    {
        $parentMap = $this->getParentMap($perspective, $teamId);

        $rootIds = [];
        foreach ($parentMap as $entityId => $parentId) {
            if ($parentId === null) {
                $rootIds[] = $entityId;
            }
        }

        return $rootIds;
    }

    /**
     * Batch-collect descendant entity IDs for multiple roots using a CTE.
     * Perspective-aware: default uses entity column, non-default uses hierarchy table.
     */
    public function getAllDescendantMap(array $rootIds, OrganizationPerspective $perspective): array
    {
        if (empty($rootIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($rootIds), '?'));

        if ($this->isDefaultHierarchy($perspective)) {
            $rows = DB::select("
                WITH RECURSIVE entity_tree AS (
                    SELECT id, parent_entity_id, parent_entity_id as root_id
                    FROM organization_entities
                    WHERE parent_entity_id IN ({$placeholders})
                    UNION ALL
                    SELECT e.id, e.parent_entity_id, et.root_id
                    FROM organization_entities e
                    INNER JOIN entity_tree et ON e.parent_entity_id = et.id
                )
                SELECT root_id, id FROM entity_tree
            ", $rootIds);
        } else {
            $bindings = array_merge([$perspective->id], $rootIds, [$perspective->id]);
            $rows = DB::select("
                WITH RECURSIVE entity_tree AS (
                    SELECT entity_id as id, parent_entity_id, parent_entity_id as root_id
                    FROM organization_entity_hierarchy
                    WHERE perspective_id = ?
                      AND parent_entity_id IN ({$placeholders})
                    UNION ALL
                    SELECT h.entity_id, h.parent_entity_id, et.root_id
                    FROM organization_entity_hierarchy h
                    INNER JOIN entity_tree et ON h.parent_entity_id = et.id
                    WHERE h.perspective_id = ?
                )
                SELECT root_id, id FROM entity_tree
            ", $bindings);
        }

        $result = array_fill_keys($rootIds, []);
        foreach ($rows as $row) {
            $result[$row->root_id][] = $row->id;
        }
        return $result;
    }

    /**
     * Validate that setting a new parent does not create a circular hierarchy.
     * Walks up ancestors from newParentId; throws if entityId is found.
     */
    public function validateNoCircularHierarchy(int $entityId, int $newParentId, OrganizationPerspective $perspective): void
    {
        if ($this->isDefaultHierarchy($perspective)) {
            // Delegate to entity's own validation (walks entity column)
            $entity = OrganizationEntity::findOrFail($entityId);
            $entity->validateNoCircularHierarchy($newParentId);
            return;
        }

        // Walk hierarchy table ancestors
        $parentMap = OrganizationEntityHierarchy::getParentMap($perspective->id);

        $visited = [$entityId];
        $currentId = $newParentId;

        while ($currentId !== null) {
            if (in_array($currentId, $visited)) {
                throw new \InvalidArgumentException(
                    "Circular hierarchy detected: entity {$entityId} cannot be a child of entity {$newParentId} in perspective {$perspective->id}."
                );
            }
            $visited[] = $currentId;
            $currentId = $parentMap[$currentId] ?? null;
        }
    }
}
