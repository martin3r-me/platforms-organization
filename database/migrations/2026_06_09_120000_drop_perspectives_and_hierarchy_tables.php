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
 * WICHTIG: dim_link_unique enthielt perspective_id als Teil des 5-Spalten-
 * Unique-Index. Beim Spalten-Drop kollabiert der Index auf 4 Spalten und
 * Rows, die sich nur durch perspective_id unterschieden, werden zu echten
 * Duplikaten. Reihenfolge daher: FK droppen -> Indizes droppen -> dedupe
 * -> Spalte droppen -> Index ohne perspective_id neu anlegen.
 * (MySQL verweigert sonst Fehler 1553 "needed in a foreign key constraint".)
 *
 * Migration ist **idempotent** gegen Zwischenzustaende (Teil-Drops von
 * vorherigen fehlgeschlagenen Migrations-Laeufen). Jeder DDL-Schritt wird
 * eigenstaendig versucht und Fehler werden geschluckt, falls die jeweilige
 * Struktur bereits weg ist. try/catch MUSS dabei UM den Schema::table()-Aufruf
 * herum stehen — innerhalb der Closure queued der Builder nur, fuehrt aber
 * erst nach Verlassen aus.
 *
 * Voraussetzung: Default-Perspektive war die einzig genutzte. Falls benannte
 * Sub-Perspektiven oder alternative Hierarchien existierten, sind sie vor
 * diesem Migration-Lauf zu sichern — werden hier irreversibel verworfen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('organization_dimension_links', 'perspective_id')) {
            // 1. FK auf perspective_id droppen — falls noch vorhanden.
            try {
                Schema::table('organization_dimension_links', function (Blueprint $table) {
                    $table->dropForeign(['perspective_id']);
                });
            } catch (\Throwable $e) {
                // FK existiert nicht (durch vorigen Teil-Lauf bereits entfernt).
            }

            // 2. Composite-Unique (enthielt perspective_id) droppen — falls noch vorhanden.
            try {
                Schema::table('organization_dimension_links', function (Blueprint $table) {
                    $table->dropUnique('dim_link_unique');
                });
            } catch (\Throwable $e) {
                // Index existiert nicht.
            }

            // 3. Separater Index auf perspective_id droppen — falls noch vorhanden.
            try {
                Schema::table('organization_dimension_links', function (Blueprint $table) {
                    $table->dropIndex(['perspective_id']);
                });
            } catch (\Throwable $e) {
                // Index existiert nicht.
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

            // 5. Spalte droppen.
            Schema::table('organization_dimension_links', function (Blueprint $table) {
                $table->dropColumn('perspective_id');
            });

            // 6. Neuen Unique-Index ohne perspective_id anlegen — falls noch nicht da.
            try {
                Schema::table('organization_dimension_links', function (Blueprint $table) {
                    $table->unique(
                        ['dimension_definition_id', 'linkable_type', 'linkable_id', 'dimension_value_id'],
                        'dim_link_unique'
                    );
                });
            } catch (\Throwable $e) {
                // Index ggf. bereits angelegt durch vorheriges Re-Run.
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
};
