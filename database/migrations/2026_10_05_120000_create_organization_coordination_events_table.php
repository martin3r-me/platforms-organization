<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ROH-Koordinations-Events aus TRAAN/Parlan: was die Mitglieder (Mensch & Agent) untereinander
 * abstimmen, kommt als echtes Datum in der Org an — wie correspondence_threads / terminal_messages,
 * nicht als eingefrorene Zahl. Parlan (hält den gefalteten Fabric-Stand) pusht ein Event je
 * verbindlicher Handlung (Entscheidung getroffen, Aufgabe erledigt); der Snapshot aggregiert lokal
 * in die 7½ Dimensionen (re-aggregierbar, auditierbar).
 *
 * Idempotent über event_id (TRAAN-Event-ID) — mehrfaches Pushen desselben Events dedupliziert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organization_coordination_events');

        Schema::create('organization_coordination_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64);                      // TRAAN-Event-ID → dedup
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable(); // aufgelöst aus actor_handle
            $table->string('actor_handle');                      // wer gehandelt hat (E-Mail)
            $table->string('counterpart_handle')->nullable();    // das Gegenüber (Anforderer/Owner)
            $table->string('kind', 32);                          // decision | handoff_done | …
            $table->string('thread', 64)->nullable();            // Vorgang-Wurzel
            $table->unsignedInteger('latency_seconds')->nullable(); // offen→aufgelöst (von Parlan gerechnet)
            $table->timestamp('occurred_at')->nullable();        // server_ts des Events
            $table->timestamp('created_at')->nullable();

            // Explizite Kurznamen (<64) → deploy-sicher, unabhängig vom SafeBlueprint-Resolver.
            $table->unique('event_id', 'org_coord_events_event_uq');
            $table->index(['actor_user_id', 'occurred_at'], 'org_coord_events_actor_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_coordination_events');
    }
};
