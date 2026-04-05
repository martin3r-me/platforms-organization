<?php

namespace Platform\Organization\Contracts;

interface PersonActivityProvider
{
    /**
     * Eindeutiger Section-Key, z.B. 'planner'.
     */
    public function sectionKey(): string;

    /**
     * Display-Config fuer die Section.
     * Format: ['label' => string, 'icon' => string, 'description' => string|null]
     */
    public function sectionConfig(): array;

    /**
     * Vital Signs / Metriken fuer einen User im Team.
     * Gibt ein Array von Metrik-Karten zurueck.
     *
     * Format: [
     *   ['key' => string, 'label' => string, 'value' => int|string, 'variant' => 'default'|'warning'|'success'|'danger'],
     *   ...
     * ]
     */
    public function vitalSigns(int $userId, int $teamId): array;

    /**
     * Statische Metrik-Definitionen fuer Dashboard-Anzeige.
     * Beschreibt welche Keys der Provider in vitalSigns liefert und wie sie darzustellen sind.
     *
     * Format: [
     *   'open_tasks' => ['label' => string, 'type' => 'info'|'warning'|'danger', 'sort_weight' => int],
     *   ...
     * ]
     *
     * type: 'danger' = rot/kritisch, 'warning' = gelb/aufmerksamkeit, 'info' = neutral
     * sort_weight: hoehere Werte werden bei der Personen-Sortierung staerker gewichtet
     */
    public function metricConfig(): array;

    /**
     * Zustaendigkeiten / Responsibilities gruppiert.
     * Gibt Gruppen mit Top-N Items + total_count zurueck.
     *
     * Format: [
     *   [
     *     'key' => string,
     *     'label' => string,
     *     'icon' => string,
     *     'total_count' => int,
     *     'items' => [['id' => int, 'name' => string, 'url' => string|null, 'meta' => string|null], ...],
     *   ],
     *   ...
     * ]
     */
    public function responsibilities(int $userId, int $teamId, int $limit = 5): array;
}
