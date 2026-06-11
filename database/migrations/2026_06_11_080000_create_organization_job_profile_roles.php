<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: JobProfile <-> Role mit percentage_share.
 *
 * Ein JobProfile buendelt mehrere Rollen, jede mit einem Anteil. Die Summe
 * der percentage_share pro JobProfile sollte 100 ergeben — wir erzwingen
 * es nicht hart, weil Profile auch unter- oder ueberauslastend definiert
 * werden koennen (z.B. fuer Karriere-Stufen oder Hybrid-Profile).
 *
 * Die effektive Rollen-Verteilung einer Person ergibt sich aus:
 *   Person -> PersonJobProfile (mit Auslastung in %)
 *           -> JobProfile -> JobProfileRoles (percentage_share)
 *   = Person-Auslastung * percentage_share / 100 pro Rolle
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_job_profile_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_profile_id')
                ->constrained('organization_job_profiles')
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained('organization_roles')
                ->cascadeOnDelete();

            // Anteil dieser Rolle am JobProfile (0..100). Summe ueber alle Rollen
            // sollte typisch 100 sein, wird aber nicht hart erzwungen.
            $table->unsignedTinyInteger('percentage_share')->default(0);

            // Reihenfolge fuer UI-Anzeige (z.B. wichtigste Rolle zuerst).
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['job_profile_id', 'role_id'], 'org_jp_role_unique');
            $table->index(['job_profile_id', 'sort_order'], 'org_jp_role_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_job_profile_roles');
    }
};
