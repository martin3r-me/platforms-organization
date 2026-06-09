<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Benennt `root_entity_id` -> `scope_entity_id` auf:
 *   - organization_cost_centers
 *   - organization_vsm_functions
 *
 * Hintergrund: "root_entity_id" suggerierte semantisch *die* eine Root-Entity.
 * Tatsaechlich ist es "an welche Carrier-Entity dieses Element gebunden ist"
 * (NULL = global, X = entity-spezifisch mit Hierarchie-Fallback).
 * Nach dem Perspective-Refactor (Carrier-Entity = Perspektive) ist "scope"
 * der praezise Begriff.
 *
 * Migration ist idempotent: pruft jeden Schritt vorab gegen information_schema.
 * Reine Spalten-/Index-Umbenennung, keine Datenmigration noetig — Werte werden
 * beim Rename mitgenommen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->renameOn('organization_cost_centers');
        $this->renameOn('organization_vsm_functions');
    }

    public function down(): void
    {
        $this->reverseOn('organization_cost_centers');
        $this->reverseOn('organization_vsm_functions');
    }

    protected function renameOn(string $table): void
    {
        // Bereits umbenannt? Nichts zu tun.
        if (!Schema::hasColumn($table, 'root_entity_id')) {
            return;
        }

        // 1. Alte Indizes droppen — falls noch da.
        $oldIdx1 = "{$table}_root_entity_id_is_active_index";
        $oldIdx2 = "{$table}_team_id_root_entity_id_index";

        if ($this->indexExists($table, $oldIdx1)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$oldIdx1}`");
        }
        if ($this->indexExists($table, $oldIdx2)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$oldIdx2}`");
        }

        // 2. Spalte umbenennen (Daten bleiben erhalten).
        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->renameColumn('root_entity_id', 'scope_entity_id');
        });

        // 3. Neue Indizes anlegen — falls noch nicht da.
        $newIdx1 = "{$table}_scope_entity_id_is_active_index";
        $newIdx2 = "{$table}_team_id_scope_entity_id_index";

        if (!$this->indexExists($table, $newIdx1)) {
            DB::statement(
                "ALTER TABLE `{$table}` ADD INDEX `{$newIdx1}` (`scope_entity_id`, `is_active`)"
            );
        }
        if (!$this->indexExists($table, $newIdx2)) {
            DB::statement(
                "ALTER TABLE `{$table}` ADD INDEX `{$newIdx2}` (`team_id`, `scope_entity_id`)"
            );
        }
    }

    protected function reverseOn(string $table): void
    {
        if (!Schema::hasColumn($table, 'scope_entity_id')) {
            return;
        }

        $newIdx1 = "{$table}_scope_entity_id_is_active_index";
        $newIdx2 = "{$table}_team_id_scope_entity_id_index";

        if ($this->indexExists($table, $newIdx1)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$newIdx1}`");
        }
        if ($this->indexExists($table, $newIdx2)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$newIdx2}`");
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->renameColumn('scope_entity_id', 'root_entity_id');
        });

        $oldIdx1 = "{$table}_root_entity_id_is_active_index";
        $oldIdx2 = "{$table}_team_id_root_entity_id_index";

        if (!$this->indexExists($table, $oldIdx1)) {
            DB::statement(
                "ALTER TABLE `{$table}` ADD INDEX `{$oldIdx1}` (`root_entity_id`, `is_active`)"
            );
        }
        if (!$this->indexExists($table, $oldIdx2)) {
            DB::statement(
                "ALTER TABLE `{$table}` ADD INDEX `{$oldIdx2}` (`team_id`, `root_entity_id`)"
            );
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName]
        );

        return ((int) ($row->cnt ?? 0)) > 0;
    }
};
