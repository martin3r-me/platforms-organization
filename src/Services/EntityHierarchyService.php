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
        $reachMemo = [];
        $results = [];

        $allIds = array_keys($ownMetrics);

        foreach ($allIds as $rootId) {
            // Erreichbare Entities inkl. Root selbst — visited-Set garantiert,
            // dass jede Entity genau einmal in der Aggregation auftaucht,
            // egal ueber wieviele Pfade sie erreichbar ist. Damit:
            //  - keine Doppel-Zaehlung bei DAG mit Mehrfach-Pfaden
            //  - Zyklen werden inherent absorbiert (Entity ist nur einmal im Set)
            $reachable = $this->getReachableSet($rootId, $childMap, $reachMemo);

            $sums = [];
            foreach ($keys as $key) {
                $sums[$key . '_cascaded'] = 0;
            }

            foreach (array_keys($reachable) as $entityId) {
                foreach ($keys as $key) {
                    $sums[$key . '_cascaded'] += $ownMetrics[$entityId][$key] ?? 0;
                }
            }

            // Direkte Kinder zaehlen (Tree-Kinder + Channel-Kinder, falls vorhanden)
            $sums['children_count'] = count($childMap[$rootId] ?? []);

            $results[$rootId] = $sums;
        }

        return $results;
    }

    /**
     * Sammelt rekursiv ueber DFS alle vom Root aus erreichbaren Entity-IDs
     * (inkl. Root selbst). Garantien:
     *
     *  - Visited-Set: jede Entity wird genau einmal besucht. Zyklen werden
     *    durch isset()-Check inherent behandelt — kein Stack-Overflow, keine
     *    Doppel-Zaehlung.
     *  - Memoization: pro Root das Reachability-Set cachen. Selbst wenn
     *    mehrere Roots ueberlappende Subtrees haben, wird der DFS pro Root
     *    nur einmal ausgefuehrt. (Optimierung fuer wiederholte Calls.)
     *
     * @return array<int, true>  Map[entityId => true] — als Set verwendbar
     */
    protected function getReachableSet(int $rootId, array &$childMap, array &$reachMemo): array
    {
        if (isset($reachMemo[$rootId])) {
            return $reachMemo[$rootId];
        }

        $reachable = [$rootId => true];
        $stack = [$rootId];

        while (! empty($stack)) {
            $current = array_pop($stack);
            $children = $childMap[$current] ?? [];

            foreach ($children as $child) {
                if (! isset($reachable[$child])) {
                    $reachable[$child] = true;
                    $stack[] = $child;
                }
            }
        }

        $reachMemo[$rootId] = $reachable;
        return $reachable;
    }
}
