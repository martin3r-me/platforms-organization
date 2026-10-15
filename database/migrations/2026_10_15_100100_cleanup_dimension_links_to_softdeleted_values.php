<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cleanup (Phase-0-Befund 1): entfernt Geister-Links, die auf soft-gelöschte
 * Dimension-Values zeigen.
 *
 * Das Phase-0-Audit fand 9 solcher Links (Values BHG.CONSULTING/BHG.IT/
 * BROICH.AKADEMIE wurden gelöscht, Links blieben). Da der Value gelöscht ist,
 * ist die Zuordnung ohnehin bedeutungslos — die betroffenen Objekte verlieren
 * lediglich eine bereits ungültige Dimension-Zuordnung.
 *
 * Idempotent: ein erneuter Lauf findet nichts mehr. Nicht umkehrbar (die
 * ursprüngliche Zuordnung auf einen gelöschten Wert wird nicht wiederhergestellt).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_dimension_links') || ! Schema::hasTable('organization_dimension_values')) {
            return;
        }
        if (! Schema::hasColumn('organization_dimension_values', 'deleted_at')) {
            return;
        }

        $ids = DB::table('organization_dimension_links as l')
            ->join('organization_dimension_values as v', 'v.id', '=', 'l.dimension_value_id')
            ->whereNotNull('v.deleted_at')
            ->pluck('l.id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('organization_dimension_links')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // Nicht umkehrbar — Links auf bereits gelöschte Values werden nicht restauriert.
    }
};
