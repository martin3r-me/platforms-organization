<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contributions LOSE koppeln: den Fremdschlüssel milestone_id → organization_milestones
 * entfernen (inkl. cascadeOnDelete). Die Integrität hält ab jetzt das Model: es prüft den
 * Meilenstein beim Speichern, und OrganizationMilestone räumt seine Beiträge beim
 * endgültigen Löschen selbst ab. linkable_type/linkable_id war schon polymorph ohne FK.
 *
 * Der FK-Name wird aus information_schema gelesen (robust gegen gekürzte Namen).
 * Der Unique-Index (milestone_id, linkable_type, linkable_id) bleibt und deckt
 * Abfragen nach milestone_id weiterhin ab. Die Tabelle war bis hierher ungenutzt (leer).
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = 'organization_milestone_contributions';
        if (!Schema::hasTable($table)) {
            return;
        }

        $fks = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'milestone_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL",
            [Schema::getConnection()->getDatabaseName(), $table]
        ))->pluck('CONSTRAINT_NAME')->unique()->values();

        if ($fks->isEmpty()) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($fks) {
            foreach ($fks as $name) {
                $t->dropForeign($name);
            }
        });
    }

    public function down(): void
    {
        // Bewusst leer: die lose Kopplung ist gewollt, der FK wird nicht wiederhergestellt.
    }
};
