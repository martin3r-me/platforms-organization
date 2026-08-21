<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-Aktivitäts-Feed eines Agenten: der Daemon MELDET, was er gerade tut (Claim, Datei-Reads/
 * -Edits, Shell, Git-Schritte, Ergebnis) — die Plattform kognisiert nichts selbst, sie zeigt nur.
 * Ersetzt das flüchtige Electron-Live-Fenster durch einen durablen, pro-Run nachlesbaren Feed.
 * Bewusst schlank + gepruned (kein Voll-Token-Strom).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_agent_run_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_entity_id')->constrained()->cascadeOnDelete();
            $table->string('run_id', 64);          // gruppiert einen Lauf (z. B. Issue-UUID)
            $table->string('kind', 24);            // claimed|sync|read|edit|write|shell|commit|push|text|tool|done|fail
            $table->text('text')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_entity_id', 'id']);
            $table->index(['organization_entity_id', 'run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_agent_run_events');
    }
};
