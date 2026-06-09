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
 * **Backing-Index-Problem:** dim_link_unique (Composite-Unique mit
 * dimension_definition_id als erste Spalte) wird von MySQL als Backing-Index
 * fuer den FK auf dimension_definition_id genutzt. DROP des Composites
 * scheitert daher mit 1553 "needed in a foreign key constraint", solange
 * kein alternativer Index fuer dimension_definition_id existiert.
 *
 * Loesung: temporaeren Backup-Index auf dimension_definition_id anlegen,
 * dann ist dim_link_unique freigegeben und kann gedroppt werden. Nach dem
 * Anlegen des neuen 4-Spalten-Unique (der dimension_definition_id wieder
 * als erste Spalte hat) ist der Backup obsolet und wird entfernt.
 *
 * Migration ist **idempotent**: pro DDL-Schritt wird via information_schema
 * geprueft, ob die Struktur noch existiert / schon existiert.
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
        $backupIdx = 'dim_link_def_backup_idx';

        if (Schema::hasColumn($table, 'perspective_id')) {
            // 1. Backup-Index auf dimension_definition_id anlegen — damit der FK auf
            //    dimension_definition_id weiterhin ein Backing hat, wenn dim_link_unique
            //    spaeter weggeht.
            if (!$this->indexExists($table, $backupIdx)) {
                DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$backupIdx}` (`dimension_definition_id`)");
            }

            // 2. FK auf perspective_id droppen — nur wenn er noch existiert.
            if ($this->foreignKeyExists($table, $fkName)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }

            // 3. Composite-Unique droppen — jetzt freigegeben durch Backup-Index.
            if ($this->indexExists($table, $compositeUnique)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$compositeUnique}`");
            }

            // 4. Separater Index auf perspective_id droppen — nur wenn er noch existiert.
            if ($this->indexExists($table, $perspectiveIdx)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$perspectiveIdx}`");
            }

            // 5. Duplikate bereinigen: pro logischem Schluessel
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

            // 6. perspective_id-Spalte droppen.
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('perspective_id');
            });

            // 7. Neuen 4-Spalten-Unique anlegen — nur wenn er noch nicht existiert.
            if (!$this->indexExists($table, $compositeUnique)) {
                DB::statement(
                    "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$compositeUnique}` "
                    . "(`dimension_definition_id`, `linkable_type`, `linkable_id`, `dimension_value_id`)"
                );
            }

            // 8. Backup-Index droppen — der neue Unique uebernimmt das Backing fuer
            //    den FK auf dimension_definition_id.
            if ($this->indexExists($table, $backupIdx)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$backupIdx}`");
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
     * Prueft via information_schema, ob ein Index existiert.
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
