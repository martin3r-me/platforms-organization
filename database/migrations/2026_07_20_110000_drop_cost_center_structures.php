<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt die komplette alte Kostenstellen-Struktur.
 *
 * Kostenstellen sind keine eigene Dimension/Tabelle mehr — jede Entity IST
 * faktisch ihre eigene Kostenstelle; der KST-Bezeichner lebt als Fremd-ID in
 * organization_entity_external_ids (system='kostenstelle'). Verlinkt wird über
 * die generische entity-Dimension.
 *
 * Betroffen:
 *  - generische Dimension 'cost-center' (definition + values + links)
 *  - Legacy-Tabellen organization_cost_center_links, organization_cost_centers
 *
 * NICHT betroffen: 'cost-driver' (eigenständige Dimension für externe Kostentreiber).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Generische 'cost-center'-Dimension abräumen (links → values → definition)
        $defId = DB::table('organization_dimension_definitions')
            ->where('key', 'cost-center')
            ->value('id');

        if ($defId) {
            DB::table('organization_dimension_links')
                ->where('dimension_definition_id', $defId)
                ->delete();
            DB::table('organization_dimension_values')
                ->where('dimension_definition_id', $defId)
                ->delete();
            DB::table('organization_dimension_definitions')
                ->where('id', $defId)
                ->delete();
        }

        // 2. Legacy-Tabellen droppen (erst Link-Tabelle wegen FK)
        Schema::dropIfExists('organization_cost_center_links');
        Schema::dropIfExists('organization_cost_centers');
    }

    public function down(): void
    {
        // Bewusst nicht reversibel: die alte Struktur wurde ersatzlos abgelöst
        // (Dev-Ballast). Kostenstellen leben jetzt in entity_external_ids.
    }
};
