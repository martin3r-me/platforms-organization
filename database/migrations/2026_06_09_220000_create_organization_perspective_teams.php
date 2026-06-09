<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapping: Carrier-Entity (= Perspektive) <-> Plattform-Team.
 *
 * M:N — ein Team kann mehrere Perspektiven sehen, eine Perspektive kann
 * mehreren Teams gehoeren. is_default markiert die Standard-Perspektive
 * eines Teams (User des Teams ohne explizite Auswahl landen dort).
 *
 * Per-Team-Default ist nicht hart erzwungen (MySQL kann keinen partiellen
 * Unique-Index ohne Generated-Column). Stattdessen sorgt der Service beim
 * Setzen eines Defaults dafuer, dass die anderen Defaults im selben Team
 * abgeraeumt werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_perspective_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perspective_entity_id')
                ->constrained('organization_entities')
                ->cascadeOnDelete();
            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['perspective_entity_id', 'team_id'], 'org_persp_team_unique');
            $table->index(['team_id', 'is_default'], 'org_persp_team_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_perspective_teams');
    }
};
