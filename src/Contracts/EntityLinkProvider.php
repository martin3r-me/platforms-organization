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
}
