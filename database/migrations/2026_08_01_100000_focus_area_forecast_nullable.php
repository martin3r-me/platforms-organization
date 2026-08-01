<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modell-Shift (Pilot): Fokusräume lösen sich von der Regnose.
 *
 * organization_focus_areas.forecast_id wird nullable — ein Fokusraum hängt jetzt
 * primär an der Carrier-Entity (entity_id, existiert bereits), nicht mehr zwingend
 * an einem Forecast. forecast_id bleibt als optionaler Soft-Link (nullOnDelete
 * statt cascade), damit das Löschen einer Regnose die Fokusräume NICHT mitnimmt.
 *
 * Kein Daten-Backfill nötig: entity_id ist auf allen Zeilen schon gesetzt.
 * Additiv (Constraint aufgeweitet) — bricht nichts, alter Endpunkt läuft weiter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_focus_areas', function (Blueprint $table) {
            $table->dropForeign(['forecast_id']);
        });
        Schema::table('organization_focus_areas', function (Blueprint $table) {
            $table->unsignedBigInteger('forecast_id')->nullable()->change();
        });
        Schema::table('organization_focus_areas', function (Blueprint $table) {
            $table->foreign('forecast_id')->references('id')->on('organization_forecasts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Best-effort: schlägt fehl, falls bereits forecast-lose Fokusräume existieren.
        Schema::table('organization_focus_areas', function (Blueprint $table) {
            $table->dropForeign(['forecast_id']);
        });
        Schema::table('organization_focus_areas', function (Blueprint $table) {
            $table->unsignedBigInteger('forecast_id')->nullable(false)->change();
        });
        Schema::table('organization_focus_areas', function (Blueprint $table) {
            $table->foreign('forecast_id')->references('id')->on('organization_forecasts')->cascadeOnDelete();
        });
    }
};
