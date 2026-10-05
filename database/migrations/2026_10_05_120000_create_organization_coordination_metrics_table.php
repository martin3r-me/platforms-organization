<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Koordinations-Kennzahlen pro Agent (Mensch/Agent-Mitglied): was die Emberons untereinander und
 * mit Menschen auf TRAAN/Parlan koordinieren, kommt hier in der Org an — die Comms-Daten verlassen
 * das Parlan-Silo und laufen in die 7½ Dimensionen (Durchsatz/Energie/Qualität/Org-Kapital).
 *
 * Parlan (hält den gefalteten Fabric-Stand) pusht pro HANDLE fertige Kennzahlen; die Plattform
 * löst Handle→User auf und speichert den jeweils neuesten Stand (eine Zeile je Handle, upsert).
 * Der Snapshot-Command merged sie dann wie die terminal-Kommunikations-Metriken in die Entität.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organization_coordination_metrics');

        Schema::create('organization_coordination_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // aufgelöst aus handle (users.email)
            $table->string('handle');                          // TRAAN-Handle (E-Mail) — die Brücke
            $table->json('metrics');                           // { coord_decisions_7d: 3, coord_latency_days: 1.2, … }
            $table->timestamp('reported_at')->nullable();      // wann Parlan es berechnet hat
            $table->timestamps();

            // Explizite Kurznamen (<64) → deploy-sicher, unabhängig vom SafeBlueprint-Resolver.
            $table->unique('handle', 'org_coord_metrics_handle_uq');
            $table->index('user_id', 'org_coord_metrics_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_coordination_metrics');
    }
};
