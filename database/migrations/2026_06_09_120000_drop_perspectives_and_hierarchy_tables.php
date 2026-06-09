<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loescht die freistehende Perspective-Logik.
 *
 * Perspektive ergibt sich kuenftig aus der aktiven Carrier-Entity in der Session.
 * Alle datenrelevanten Perspektiv-Bezuege (z.B. signals.perspective_entity_id,
 * entity_vsm_assignments.perspective_entity_id) tragen ihre Sicht als FK auf
 * organization_entities, nicht auf eine eigene Perspektive-Tabelle.
 *
 * WICHTIG: Die Migration ist **maximal defensiv** und prueft pro DDL-Schritt
 * via information_schema, ob die jeweilige Struktur ueberhaupt noch existiert.
 * Damit ueberlebt sie beliebige Teil-Zustaende aus frueheren fehlgeschlagenen
 * Laeufen (FK weg + Indizes da, FK + Unique weg + separater Index da, …).
 *
 * Schema-Builder-try/catch greift nicht zuverlaessig, weil Befehle aus der
 * Closure heraus nur queued und erst spaeter ausgefuehrt werden — daher
 * gehen wir hier ueber DB::statement() mit explizitem Vorab-Check.
 *
 * Voraussetzung: Default-Perspektive war die einzig genutzte. Falls benannte
 * Sub-Perspektiven oder alternative Hierarchien existierten, sind sie vor
 * diesem Migration-Lauf zu sichern — werden hier irreversibel verworfen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = 'organization_dimension_links';
        $fkName = 'organization_dimension_links_perspective_id_foreign';
        $compositeUnique = 'dim_link_unique';
        $perspectiveIdx = 'organization_dimension_links_perspective_id_index';

        if (Schema::hasColumn($table, 'perspective_id')) {
            // 1. FK droppen — nur wenn er noch existiert.
            if ($this->foreignKeyExists($table, $fkName)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }

            // 2. Composite-Unique droppen — nur wenn er noch existiert.
            if ($this->indexExists($table, $compositeUnique)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$compositeUnique}`");
            }

            // 3. Separater Index auf perspective_id droppen — nur wenn er noch existiert.
            if ($this->indexExists($table, $perspectiveIdx)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$perspectiveIdx}`");
            }

            // 4. Duplikate bereinigen: pro logischem Schluessel
            //    (dimension_definition_id, linkable_type, linkable_id, dimension_value_id)
            //    bleibt die Row mit niedrigster id (= aelteste) erhalten.
            DB::statement(<<<'SQL'
                DELETE l1 FROM organization_dimension_links l1
                INNER JOIN organization_dimension_links l2
                    ON l1.dimension_definition_id = l2.dimension_definition_id
                    AND l1.linkable_type = l2.linkable_type
                    AND l1.linkable_id = l2.linkable_id
                    AND l1.dimension_value_id = l2.dimension_value_id
                    AND l1.id > l2.id
            SQL);

            // 5. Spalte droppen — sicher, weil alle Referenzen weg sind.
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('perspective_id');
            });

            // 6. Neuen 4-Spalten-Unique anlegen — nur wenn er noch nicht existiert.
            if (!$this->indexExists($table, $compositeUnique)) {
                DB::statement(
                    "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$compositeUnique}` "
                    . "(`dimension_definition_id`, `linkable_type`, `linkable_id`, `dimension_value_id`)"
                );
            }
        }

        Schema::dropIfExists('organization_entity_hierarchy');
        Schema::dropIfExists('organization_perspectives');
    }

    public function down(): void
    {
        throw new \RuntimeException(
            'Down-Migration nicht unterstuetzt: Perspective-Daten sind irreversibel entfernt. '
            . 'Falls Rollback noetig, Tabellen-Schema manuell aus den Original-Migrationen '
            . '(2026_05_23_100002, 2026_05_24_100000) wiederherstellen und Spalte '
            . 'perspective_id zu organization_dimension_links zurueck-migrieren.'
        );
    }

    /**
     * Prueft via information_schema, ob ein FK-Constraint existiert.
     */
    protected function foreignKeyExists(string $table, string $constraintName): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? '
            . 'AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$table, $constraintName, 'FOREIGN KEY']
        );

        return ((int) ($row->cnt ?? 0)) > 0;
    }

    /**
     * Prueft via information_schema, ob ein Index (Unique oder Standard) existiert.
     */
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
