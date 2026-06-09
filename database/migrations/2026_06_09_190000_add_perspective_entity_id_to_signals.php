<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VSM-Phase 1, Schritt 5a: perspective_entity_id am Signal.
 *
 * Jedes Signal entsteht aus einer Carrier-Perspektive (strenger Beer).
 * Bisher war das nicht persistent, nur indirekt ueber den
 * Inference-Prompt erkennbar.
 *
 * Backfill: bestehende Signale werden auf den Root-Carrier ihres Teams
 * gesetzt (bisher gab es nur eine genutzte Perspektive pro Team —
 * BHG.DIGITAL). Damit keine Legacy-Signale "perspektivlos" sind.
 *
 * Spalte ist nullable, damit kuenftig Signale ohne klare Perspektive
 * (z.B. fremde Quellen) abgebildet werden koennen. UI-Filter behandeln
 * NULL als "in allen Perspektiven sichtbar".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            $table->unsignedBigInteger('perspective_entity_id')->nullable()->after('entity_id');

            $table->foreign('perspective_entity_id', 'signal_perspective_fk')
                ->references('id')->on('organization_entities')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->index('perspective_entity_id', 'signal_perspective_idx');
        });

        // Backfill: pro Team den Root-Carrier suchen und auf existierende Signale schreiben.
        $teams = DB::table('teams')->pluck('id');

        foreach ($teams as $teamId) {
            $rootCarrierId = DB::table('organization_entities')
                ->join('organization_entity_types', 'organization_entities.entity_type_id', '=', 'organization_entity_types.id')
                ->whereNull('organization_entities.parent_entity_id')
                ->where('organization_entity_types.vsm_class', 'carrier')
                ->where('organization_entities.team_id', $teamId)
                ->orderBy('organization_entities.id')
                ->value('organization_entities.id');

            if ($rootCarrierId) {
                DB::table('organization_signals')
                    ->where('team_id', $teamId)
                    ->whereNull('perspective_entity_id')
                    ->update(['perspective_entity_id' => $rootCarrierId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('organization_signals', function (Blueprint $table) {
            try {
                $table->dropForeign('signal_perspective_fk');
            } catch (\Throwable $e) {}
            try {
                $table->dropIndex('signal_perspective_idx');
            } catch (\Throwable $e) {}
            $table->dropColumn('perspective_entity_id');
        });
    }
};
