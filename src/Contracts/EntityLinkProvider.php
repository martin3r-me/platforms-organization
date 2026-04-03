<?php

namespace Platform\Organization\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface EntityLinkProvider
{
    /**
     * Morph-Aliase, die dieser Provider handhabt.
     * z.B. ['project', 'planner_task']
     *
     * @return string[]
     */
    public function morphAliases(): array;

    /**
     * Display-Config pro Alias.
     * Format: [alias => ['label' => string, 'icon' => string, 'route' => string|null]]
     */
    public function linkTypeConfig(): array;

    /**
     * Eager Loading auf die Query anwenden (withCount, selectRaw etc.)
     */
    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void;

    /**
     * Serialisierbare Metadaten aus einem geladenen Model extrahieren.
     */
    public function extractMetadata(string $morphAlias, mixed $model): array;

    /**
     * Deklarative Display-Rules fuer Alpine.js / Blade Renderer.
     * Format: [alias => [['field' => ..., 'format' => ..., ...], ...]]
     *
     * Supported formats: text, time, count, count_ratio, boolean_done,
     * boolean_active, boolean_published, boolean_pinned, percentage,
     * badge, prefixed_text, expandable_children
     */
    public function metadataDisplayRules(): array;

    /**
     * Cascade-Config fuer Zeiterfassung.
     * Format: [alias => [FQCN, [child-relation-paths]]]
     *
     * Nur Typen mit Zeiterfassung. Leeres Array wenn keine.
     */
    public function timeTrackableCascades(): array;

    /**
     * Batch-compute KPIs fuer Snapshot-Speicherung.
     *
     * @param string $morphAlias Der Morph-Alias (z.B. 'project')
     * @param array<int, int[]> $linksByEntity [entityId => [linkable_id, ...]]
     * @return array<int, array> [entityId => ['items_total' => X, 'items_done' => Y]]
     */
    public function metrics(string $morphAlias, array $linksByEntity): array;

    /**
     * Zusaetzliche Activity-relevante Models fuer den Feed resolven.
     * Wird aufgerufen mit den direkt verlinkten IDs pro Alias.
     * Provider gibt zusaetzliche FQCN => [ids] Paare zurueck
     * (z.B. Project-IDs rein → PlannerTask-IDs + FQCN raus).
     *
     * @param string $morphAlias Der Morph-Alias (z.B. 'project')
     * @param int[] $linkableIds Die direkt verlinkten IDs
     * @return array<class-string, int[]> [FQCN => [child-ids]]
     */
    public function activityChildren(string $morphAlias, array $linkableIds): array;
}
