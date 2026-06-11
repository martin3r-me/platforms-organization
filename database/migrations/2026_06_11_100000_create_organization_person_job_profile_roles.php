<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Override-Pivot: pro Person-zu-Profile-Zuweisung individuelle Rollen-Anteile.
 *
 * Berechnungs-Logik fuer effektive Rollen-Verteilung einer Person:
 *  1. Es gibt Override-Eintraege in dieser Tabelle fuer die PersonJobProfile-Zuweisung?
 *     → JA: Override-Anteile nutzen
 *     → NEIN: Default-Anteile aus JobProfile (organization_job_profile_roles) erben
 *
 * Damit ist das JobProfile eine Stellenbeschreibung mit Default-Vorschlag,
 * jede Person kann pro Zuweisung anpassen, ohne ein eigenes Profile zu brauchen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_person_job_profile_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_job_profile_id')
                ->constrained('organization_person_job_profiles')
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained('organization_roles')
                ->cascadeOnDelete();

            // Individueller Anteil dieser Rolle an der Person-Profile-Zuweisung (0..100).
            // Summe ueber alle Rollen einer Zuweisung sollte typisch 100 sein.
            $table->unsignedTinyInteger('percentage_share')->default(0);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['person_job_profile_id', 'role_id'], 'pjpr_unique');
            $table->index(['person_job_profile_id', 'sort_order'], 'pjpr_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_person_job_profile_roles');
    }
};
