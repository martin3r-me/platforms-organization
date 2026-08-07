<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2a: Personen-Skill-Zuordnungen aus Organization entfernen.
 *
 * Der Fähigkeits-Bestand lebt jetzt im People-Modul (people_employee_skills);
 * die ETL-Migration im People-Modul (2026_08_07_130000) hat die Daten vorher
 * gewandert. Hier fallen nur die beiden Zuordnungs-Tabellen.
 *
 * NICHT hier: der Skill-KATALOG (organization_skills / _soft_skills) — der ist
 * via FK an organization_job_profile_skills gebunden und wandert in Phase 2b
 * gemeinsam mit den JobProfiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('organization_person_skills');
        Schema::dropIfExists('organization_person_soft_skills');
    }

    public function down(): void
    {
        if (! Schema::hasTable('organization_person_skills')) {
            Schema::create('organization_person_skills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('person_entity_id')->constrained('organization_entities')->cascadeOnDelete();
                $table->foreignId('skill_id')->constrained('organization_skills')->cascadeOnDelete();
                $table->string('level')->default('basic');
                $table->date('certified_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['person_entity_id', 'skill_id'], 'org_person_skills_entity_skill_unique');
            });
        }

        if (! Schema::hasTable('organization_person_soft_skills')) {
            Schema::create('organization_person_soft_skills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('person_entity_id')->constrained('organization_entities')->cascadeOnDelete();
                $table->foreignId('soft_skill_id')->constrained('organization_soft_skills')->cascadeOnDelete();
                $table->string('level')->default('basic');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['person_entity_id', 'soft_skill_id'], 'org_person_soft_skills_entity_ss_unique');
            });
        }
    }
};
