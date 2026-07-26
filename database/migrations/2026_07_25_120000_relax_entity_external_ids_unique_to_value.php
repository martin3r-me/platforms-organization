<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'organization_entity_external_ids';

    /**
     * Fremd-IDs: von "eine Entity hat je System höchstens einen Wert" auf
     * "ein Wert je System gehört zu genau einer Entity".
     *
     * Idempotent + FK-sicher: Der Plain-Index (team_id, system, value) deckt den
     * team_id-Foreign-Key ab und darf erst gedroppt werden, NACHDEM der neue
     * Unique (gleiche führende Spalte) existiert. Jeder Schritt prüft vorher,
     * ob der jeweilige Index (noch) da ist — so läuft die Migration auch aus
     * einem halb angewandten Zustand sauber durch.
     */
    public function up(): void
    {
        // 1. Neuen Unique (team_id, system, value) anlegen — übernimmt die
        //    FK-Index-Rolle für team_id vom alten Plain-Index.
        if (!$this->hasIndex($this->table . '_team_id_system_value_unique')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->unique(['team_id', 'system', 'value']);
            });
        }

        // 2. Alten Plain-Index (team_id, system, value) entfernen — jetzt sicher,
        //    da der Unique denselben führenden Spalten-Prefix bietet.
        if ($this->hasIndex($this->table . '_team_id_system_value_index')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->dropIndex(['team_id', 'system', 'value']);
            });
        }

        // 3. Alten Unique (team_id, system, entity_id) entfernen, falls noch da.
        if ($this->hasIndex($this->table . '_team_id_system_entity_id_unique')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->dropUnique(['team_id', 'system', 'entity_id']);
            });
        }
    }

    public function down(): void
    {
        if (!$this->hasIndex($this->table . '_team_id_system_entity_id_unique')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->unique(['team_id', 'system', 'entity_id']);
            });
        }

        if (!$this->hasIndex($this->table . '_team_id_system_value_index')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->index(['team_id', 'system', 'value']);
            });
        }

        if ($this->hasIndex($this->table . '_team_id_system_value_unique')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->dropUnique(['team_id', 'system', 'value']);
            });
        }
    }

    private function hasIndex(string $indexName): bool
    {
        return count(DB::select(
            'SELECT 1 FROM information_schema.statistics '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$this->table, $indexName]
        )) > 0;
    }
};
