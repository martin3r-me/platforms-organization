<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationType;

/**
 * Channel-aware Ancestor-Resolver fuer Module-Sidebars und UI-Trees.
 *
 * Beer-Rationale:
 * Eine Entity lebt in mehreren Perspektiven gleichzeitig — der Tree (Owner-
 * Hierarchie via parent_entity_id) ist nur eine davon. Channels wie
 * `engagement_with` druecken weitere legitime Sichten aus, in denen dieselbe
 * Entity auftaucht (z.B. ein Engagement gehoert Owner-seitig unter unsere
 * Carrier-BU, aber aus Customer-Sicht zur Customer-Variety).
 *
 * Dieser Service liefert beide Wege:
 *  - Tree-Ancestors (parent_entity_id aufwaerts)
 *  - Channel-Ancestors (bestimmte Relations rueckwaerts/vorwaerts)
 *
 * Use Cases:
 *  - Planner/Helpdesk/Sales-Sidebars: Customer-Perspektive zusaetzlich zu
 *    Owner-Tree anzeigen, ohne Daten zu duplizieren
 *  - Ops-Room: "alle Engagements pro Customer" als Sub-Tree
 *  - Reporting: cross-perspektivische Aggregationen
 *
 * Konvention fuer Channel-Direction:
 * Aktuell wird per Default das "to_entity" einer Relation als virtual-parent
 * des "from_entity" behandelt. Das passt fuer engagement_with
 * (Engagement -> Customer, Customer wird Eltern in der Customer-Sicht).
 * Fuer komplexere Channel-Typen kann das spaeter feiner parametriert werden.
 */
class EntityAncestorService
{
    /**
     * Erweitert eine Liste von "direkten" Entity-IDs um alle Ancestors via
     * Tree und Channels. Ergebnis enthaelt die Eingabe-IDs + Tree-Parents +
     * Channel-Targets (rekursiv, dedupliziert).
     *
     * Typischer Sidebar-Aufruf:
     *   $allIds = $svc->expandEntitiesWithAncestors($linkedEntityIds, ['engagement_with']);
     *
     * @param array<int> $directEntityIds  Direkt mit Modul-Objekten verlinkte Entities
     * @param array<string> $channelTypes  relation_type-Codes deren Targets als virtual-Ancestors gelten
     * @return array<int>  Dedup-Liste aller relevanten Entity-IDs
     */
    public function expandEntitiesWithAncestors(
        array $directEntityIds,
        array $channelTypes = ['engagement_with']
    ): array {
        if (empty($directEntityIds)) {
            return [];
        }

        $expanded = [];
        foreach ($directEntityIds as $id) {
            $expanded[(int) $id] = true;
        }

        $stack = array_values($directEntityIds);
        $channelTypeIds = $this->resolveChannelTypeIds($channelTypes);

        // Iterative DFS — visited-Set verhindert Zyklen und Doppel-Arbeit.
        while (! empty($stack)) {
            $currentId = (int) array_pop($stack);

            $entity = OrganizationEntity::find($currentId);
            if (! $entity) {
                continue;
            }

            // Tree-Parent (klassischer Owner-Ancestor)
            $parentId = $entity->parent_entity_id;
            if ($parentId && ! isset($expanded[$parentId])) {
                $expanded[$parentId] = true;
                $stack[] = $parentId;
            }

            // Channel-Targets (z.B. Customer einer engagement_with-Relation)
            if (! empty($channelTypeIds)) {
                $channelTargets = OrganizationEntityRelationship::query()
                    ->where('from_entity_id', $currentId)
                    ->whereIn('relation_type_id', $channelTypeIds)
                    ->active()
                    ->pluck('to_entity_id')
                    ->all();

                foreach ($channelTargets as $targetId) {
                    $targetId = (int) $targetId;
                    if (! isset($expanded[$targetId])) {
                        $expanded[$targetId] = true;
                        $stack[] = $targetId;
                    }
                }
            }
        }

        return array_keys($expanded);
    }

    /**
     * Baut die Parent-Children-Map fuer Tree-Rendering, kombinierend
     * Tree-Edges (parent_entity_id) und Channel-Edges (z.B. engagement_with).
     *
     * Channel-Konvention: to_entity wird virtual-parent von from_entity.
     * Beispiel engagement_with(Engagement -> Customer):
     *   parent_to_children[Customer] enthaelt Engagement
     *   → Engagement erscheint im Tree UNTER dem Customer.
     *
     * Roots = Entities ohne eingehende Kante im uebergebenen Set
     * (weder Tree-Parent in der Menge noch Channel-Quelle in der Menge).
     *
     * @param Collection<int, OrganizationEntity> $entities  zu betrachtende Entities
     * @param array<string> $channelTypes  Channel-Codes die als virtuelle Edges zaehlen
     * @return array{parent_to_children: array<int, array<int>>, roots: array<int>}
     */
    public function buildParentChildrenMap(
        Collection $entities,
        array $channelTypes = ['engagement_with']
    ): array {
        $entityIdSet = [];
        foreach ($entities as $entity) {
            $entityIdSet[(int) $entity->id] = true;
        }

        if (empty($entityIdSet)) {
            return ['parent_to_children' => [], 'roots' => []];
        }

        $parentToChildren = [];
        $childToParents = [];

        // Tree-Edges (nur innerhalb des Sets — Ancestors ausserhalb ignorieren)
        foreach ($entities as $entity) {
            $parentId = $entity->parent_entity_id;
            if ($parentId && isset($entityIdSet[$parentId])) {
                $parentToChildren[$parentId][] = (int) $entity->id;
                $childToParents[(int) $entity->id] = true;
            }
        }

        // Channel-Edges (innerhalb des Sets)
        $channelTypeIds = $this->resolveChannelTypeIds($channelTypes);
        if (! empty($channelTypeIds)) {
            $entityIds = array_keys($entityIdSet);
            $channelRelations = OrganizationEntityRelationship::query()
                ->whereIn('from_entity_id', $entityIds)
                ->whereIn('to_entity_id', $entityIds)
                ->whereIn('relation_type_id', $channelTypeIds)
                ->active()
                ->get(['from_entity_id', 'to_entity_id']);

            foreach ($channelRelations as $rel) {
                // to_entity wird virtual-parent von from_entity
                $parentToChildren[(int) $rel->to_entity_id][] = (int) $rel->from_entity_id;
                $childToParents[(int) $rel->from_entity_id] = true;
            }
        }

        // Children deduplizieren
        foreach ($parentToChildren as $parentId => $children) {
            $parentToChildren[$parentId] = array_values(array_unique($children));
        }

        // Roots = keine eingehende Kante innerhalb des Sets
        $roots = [];
        foreach (array_keys($entityIdSet) as $id) {
            if (! isset($childToParents[$id])) {
                $roots[] = $id;
            }
        }

        return [
            'parent_to_children' => $parentToChildren,
            'roots' => $roots,
        ];
    }

    /**
     * Resolves relation-type codes to their IDs (nur aktive).
     */
    protected function resolveChannelTypeIds(array $channelTypes): array
    {
        if (empty($channelTypes)) {
            return [];
        }

        return OrganizationEntityRelationType::query()
            ->whereIn('code', $channelTypes)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }
}
