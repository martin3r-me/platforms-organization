<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2b: JobProfiles + Skill-Katalog aus Organization entfernen.
 *
 * Daten leben jetzt im People-Modul (people_job_profiles / people_skills / …);
 * der Transfer lief über die Commands people:import-skills und
 * people:import-job-profiles. Diese Migration droppt die acht Quell-Tabellen
 * in FK-sicherer Reihenfolge (Kinder zuerst).
 *
 * ACHTUNG: Einweg-Extraktion. down() ist bewusst ein No-op — ein Rollback würde
 * die gewanderten Daten nicht zurückschreiben. Vor dem Deploy sicherstellen,
 * dass der People-Transfer gelaufen ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organization_person_job_profile_roles');
        Schema::dropIfExists('organization_person_job_profiles');
        Schema::dropIfExists('organization_job_profile_roles');
        Schema::dropIfExists('organization_job_profile_skills');
        Schema::dropIfExists('organization_job_profile_soft_skills');
        Schema::dropIfExists('organization_job_profiles');
        Schema::dropIfExists('organization_skills');
        Schema::dropIfExists('organization_soft_skills');
    }

    public function down(): void
    {
        // Bewusst leer: Einweg-Extraktion nach People. Kein verlustfreier Rollback.
    }
};
