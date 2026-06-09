<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EntityHierarchyService
{
    /**
     * Build a parent->children map from a flat collection of entities.
     * Returns: [parentId => [childId, ...]]
     */
    public function buildChildMap(Collection $entities): array
    {
        $map = [];
        foreach ($entities as $entity) {
            if ($entity->parent_entity_id !== null) {
                $map[$entity->parent_entity_id][] = $entity->id;
            }
        }
        return $map;
    }

    /**
     * Batch-collect descendant entity IDs for multiple roots using a single recursive CTE.
     * Returns: [rootId => [descendantId, ...]]
     */
    public function getAllDescendantMap(array $rootIds): array
    {
        if (empty($rootIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($rootIds), '?'));
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

        $result = array_fill_keys($rootIds, []);
        foreach ($rows as $row) {
            $result[$row->root_id][] = $row->id;
        }
        return $result;
    }

    /**
     * Bottom-up cascade metrics through a hierarchy.
     *
     * @param array $ownMetrics  [entityId => [key => value, ...]]
     * @param array $childMap    [parentId => [childId, ...]]
     * @param array $keys        Keys to cascade (e.g. ['items_total', 'items_done'])
     * @return array [entityId => [key_cascaded => value, ...]]
     */
    public function cascadeMetrics(array $ownMetrics, array $childMap, array $keys): array
    {
        $memo = [];

        // Get all entity IDs involved
        $allIds = array_keys($ownMetrics);

        foreach ($allIds as $id) {
            $this->computeCascaded($id, $ownMetrics, $childMap, $keys, $memo);
        }

        return $memo;
    }

    /**
     * Recursive memoized computation of cascaded metrics for one entity.
     */
    protected function computeCascaded(int $id, array &$ownMetrics, array &$childMap, array &$keys, array &$memo): array
    {
        if (isset($memo[$id])) {
            return $memo[$id];
        }

        // Start with own values
        $cascaded = [];
        foreach ($keys as $key) {
            $cascaded[$key . '_cascaded'] = $ownMetrics[$id][$key] ?? 0;
        }

        // Add children's cascaded values
        $children = $childMap[$id] ?? [];
        foreach ($children as $childId) {
            $childCascaded = $this->computeCascaded($childId, $ownMetrics, $childMap, $keys, $memo);
            foreach ($keys as $key) {
                $cascaded[$key . '_cascaded'] += $childCascaded[$key . '_cascaded'] ?? 0;
            }
        }

        // Count direct children
        $cascaded['children_count'] = count($children);

        $memo[$id] = $cascaded;
        return $cascaded;
    }
}
