<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationType;

/**
 * Traversiert Entity-Relationships als Beer-Channels.
 *
 * Liest die Property `affects_aggregation` aus dem Relation-Type-Katalog und
 * baut darauf einen Channel-Children-Map, der parallel zur Tree-Hierarchie
 * fuer Snapshot/Movement-Aggregation genutzt werden kann.
 *
 * Service-Code-Pattern: NIE auf relation_type_code hardcoden. Verhalten leitet
 * sich ausschliesslich aus den Channel-Properties des Relation-Types ab
 * (channel_class, traversal_direction, cascade_to_children, aggregation_weight).
 */
class EntityChannelTraversalService
{
    /**
     * Baut einen Channel-Children-Map analog zum EntityHierarchyService::buildChildMap.
     *
     * Logik pro Relation:
     *  - traversal_direction='forward'  → map[from] += [to]  (from ist Aggregator)
     *  - traversal_direction='reverse'  → map[to]   += [from] (to ist Aggregator)
     *  - traversal_direction='both'     → beide Richtungen
     *
     * Damit gilt: der Aggregator (Eintrag-Key) sieht den Counterpart als
     * "channel-child" und bezieht dessen Metriken in seine Aggregation ein.
     *
     * Beispiel "Customer aggregiert Engagements":
     *  - relation: Engagement.engagement_with → Customer
     *  - engagement_with hat traversal_direction='reverse', affects_aggregation=true
     *  - → channelMap[Customer] enthaelt Engagement
     *  - Cascade-Logik addiert Engagement.metrics zu Customer.metrics
     *
     * @param Collection|null $relationTypes  Vorgeladene Types; null = automatisch alle aktiven mit affects_aggregation=true
     * @param array|null $entityIdsFilter      Nur Relations beruecksichtigen, deren from oder to in diesem Set liegt; null = alle
     * @param array|null $channelClassesFilter Nur Relations mit channel_class in diesem Set; null = alle
     * @return array<int, array<int>>           [aggregatorEntityId => [contributorEntityId, ...]]
     */
    public function buildChannelChildMap(
        ?Collection $relationTypes = null,
        ?array $entityIdsFilter = null,
        ?array $channelClassesFilter = null,
    ): array {
        $relationTypes = $this->resolveRelationTypes($relationTypes, $channelClassesFilter);

        if ($relationTypes->isEmpty()) {
            return [];
        }

        $typeIds = $relationTypes->pluck('id')->all();
        $typeMap = $relationTypes->keyBy('id');

        $q = OrganizationEntityRelationship::query()
            ->whereIn('relation_type_id', $typeIds)
            ->active();

        if ($entityIdsFilter !== null) {
            $q->where(function ($q) use ($entityIdsFilter) {
                $q->whereIn('from_entity_id', $entityIdsFilter)
                  ->orWhereIn('to_entity_id', $entityIdsFilter);
            });
        }

        $relationships = $q->get(['from_entity_id', 'to_entity_id', 'relation_type_id']);

        $channelMap = [];

        foreach ($relationships as $rel) {
            $type = $typeMap[$rel->relation_type_id] ?? null;
            if (! $type) {
                continue;
            }

            $direction = $type->traversal_direction ?? 'forward';

            if ($direction === 'forward' || $direction === 'both') {
                // from -> to: from is upstream aggregator
                $channelMap[$rel->from_entity_id][] = $rel->to_entity_id;
            }
            if ($direction === 'reverse' || $direction === 'both') {
                // reverse: to -> from
                $channelMap[$rel->to_entity_id][] = $rel->from_entity_id;
            }
        }

        // Deduplizieren
        foreach ($channelMap as $aggregatorId => $children) {
            $channelMap[$aggregatorId] = array_values(array_unique($children));
        }

        return $channelMap;
    }

    /**
     * Erweitert den Channel-Map um cascade_to_children-Logik:
     * Wenn ein Relation-Type cascade_to_children=true hat, erben alle
     * Tree-Descendants des Contributors die Channel-Beziehung zum Aggregator.
     *
     * Beispiel: Engagement.engagement_with → Customer (cascade_to_children=true)
     *   - Engagement wird channel-child von Customer
     *   - PLUS: alle Sub-Entities von Engagement (Projekte) werden ebenfalls
     *     channel-children von Customer
     *
     * @param Collection|null $relationTypes
     * @param array|null $entityIdsFilter
     * @param EntityHierarchyService $hierarchyService  Fuer Tree-Descendant-Lookup
     */
    public function buildChannelChildMapWithCascade(
        EntityHierarchyService $hierarchyService,
        ?Collection $relationTypes = null,
        ?array $entityIdsFilter = null,
        ?array $channelClassesFilter = null,
    ): array {
        $relationTypes = $this->resolveRelationTypes($relationTypes, $channelClassesFilter);

        if ($relationTypes->isEmpty()) {
            return [];
        }

        // Non-cascading channels normal aufbauen
        $nonCascadingTypes = $relationTypes->filter(fn ($t) => ! $t->cascade_to_children);
        $cascadingTypes = $relationTypes->filter(fn ($t) => (bool) $t->cascade_to_children);

        $channelMap = $this->buildChannelChildMap($nonCascadingTypes, $entityIdsFilter);

        if ($cascadingTypes->isEmpty()) {
            return $channelMap;
        }

        // Cascading channels: erst basis-Map bauen, dann Children expandieren
        $cascadingBaseMap = $this->buildChannelChildMap($cascadingTypes, $entityIdsFilter);

        // Sammle alle "Contributor-Entities", deren Tree-Descendants wir brauchen
        $contributorIds = [];
        foreach ($cascadingBaseMap as $children) {
            $contributorIds = array_merge($contributorIds, $children);
        }
        $contributorIds = array_values(array_unique($contributorIds));

        if (empty($contributorIds)) {
            return $channelMap;
        }

        $descendantsMap = $hierarchyService->getAllDescendantMap($contributorIds);

        foreach ($cascadingBaseMap as $aggregatorId => $contributors) {
            $expanded = $contributors;
            foreach ($contributors as $contributorId) {
                $descendants = $descendantsMap[$contributorId] ?? [];
                $expanded = array_merge($expanded, $descendants);
            }
            $expanded = array_values(array_unique($expanded));

            // Merge mit non-cascading-Map
            $existing = $channelMap[$aggregatorId] ?? [];
            $channelMap[$aggregatorId] = array_values(array_unique(array_merge($existing, $expanded)));
        }

        return $channelMap;
    }

    /**
     * Merged einen Tree-childMap und einen Channel-childMap zu einer
     * gemeinsamen Aggregation-Map.
     *
     * Eintrag-Key ist Aggregator, Value-Array sind alle Contributors
     * (Tree-Children + Channel-Children, dedupliziert).
     */
    public function mergeWithTreeMap(array $treeMap, array $channelMap): array
    {
        $merged = $treeMap;

        foreach ($channelMap as $aggregatorId => $channelChildren) {
            $existing = $merged[$aggregatorId] ?? [];
            $merged[$aggregatorId] = array_values(array_unique(array_merge($existing, $channelChildren)));
        }

        return $merged;
    }

    /**
     * Query-Helper: Findet alle Entities, die von $rootId aus erreichbar sind
     * via Tree-Descendants UND Channels.
     *
     * Typischer Use Case: "Alle Projekte von Customer X" — Walk channel-relations
     * (z.B. engagement_with reverse) und dann Tree-Descendants jedes Treffers.
     *
     * @return array<int>  Liste der Entity-IDs, ohne Root selbst
     */
    public function getAllDescendantsViaChannelsAndTree(
        int $rootId,
        EntityHierarchyService $hierarchyService,
        ?array $channelClassesFilter = null,
    ): array {
        // 1. Direkte Tree-Descendants des Roots
        $treeDescMap = $hierarchyService->getAllDescendantMap([$rootId]);
        $treeDescendants = $treeDescMap[$rootId] ?? [];

        // 2. Direkte Channel-Children des Roots
        $channelMap = $this->buildChannelChildMap(null, [$rootId], $channelClassesFilter);
        $channelChildren = $channelMap[$rootId] ?? [];

        $allReachable = array_merge($treeDescendants, $channelChildren);

        // 3. Tree-Descendants jedes Channel-Childs hinzufuegen
        if (! empty($channelChildren)) {
            $childTreeDescMap = $hierarchyService->getAllDescendantMap($channelChildren);
            foreach ($childTreeDescMap as $childTreeDescs) {
                $allReachable = array_merge($allReachable, $childTreeDescs);
            }
        }

        return array_values(array_unique($allReachable));
    }

    /**
     * Laed alle aktiven Relation-Types mit affects_aggregation=true,
     * optional gefiltert auf bestimmte channel_class-Werte.
     */
    protected function resolveRelationTypes(?Collection $preloaded, ?array $channelClassesFilter): Collection
    {
        if ($preloaded !== null) {
            // Falls explizit uebergeben, vertrauen wir der Filterung des Callers
            return $preloaded;
        }

        $q = OrganizationEntityRelationType::query()
            ->where('is_active', true)
            ->where('affects_aggregation', true);

        if ($channelClassesFilter !== null && ! empty($channelClassesFilter)) {
            $q->whereIn('channel_class', $channelClassesFilter);
        }

        return $q->get();
    }
}
