<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loescht die freistehende Perspective-Logik.
 *
 * Perspektive ergibt sich kuenftig aus der aktiven Carrier-Entity in der Session.
 * Alle datenrelevanten Perspektiv-Bezuege (z.B. signals.perspective_entity_id,
 * entity_vsm_assignments.perspective_entity_id) tragen ihre Sicht als FK auf
 * organization_entities, nicht auf eine eigene Perspektive-Tabelle.
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
            Schema::table('organization_dimension_links', function (Blueprint $table) {
                try {
                    $table->dropForeign(['perspective_id']);
                } catch (\Throwable $e) {
                    // FK existiert ggf. nicht (SQLite/manuell entfernt) — weiter machen.
                }
                $table->dropColumn('perspective_id');
            });
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
