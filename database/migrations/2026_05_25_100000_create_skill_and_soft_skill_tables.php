<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_skills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('technical'); // technical, methodical, domain
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'is_active']);
        });

        Schema::create('organization_soft_skills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'name']);
            $table->index(['team_id', 'is_active']);
        });

        Schema::create('organization_job_profile_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_profile_id')->constrained('organization_job_profiles')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('organization_skills')->cascadeOnDelete();
            $table->string('level')->default('expert'); // basic, advanced, expert
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);

            $table->unique(['job_profile_id', 'skill_id']);
        });

        Schema::create('organization_job_profile_soft_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_profile_id')->constrained('organization_job_profiles')->cascadeOnDelete();
            $table->foreignId('soft_skill_id')->constrained('organization_soft_skills')->cascadeOnDelete();
            $table->string('level')->default('expert'); // basic, advanced, expert
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);

            $table->unique(['job_profile_id', 'soft_skill_id']);
        });

        Schema::create('organization_person_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('organization_skills')->cascadeOnDelete();
            $table->string('level')->default('basic'); // basic, advanced, expert
            $table->date('certified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['person_entity_id', 'skill_id']);
        });

        Schema::create('organization_person_soft_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_entity_id')->constrained('organization_entities')->cascadeOnDelete();
            $table->foreignId('soft_skill_id')->constrained('organization_soft_skills')->cascadeOnDelete();
            $table->string('level')->default('basic'); // basic, advanced, expert
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['person_entity_id', 'soft_skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_person_soft_skills');
        Schema::dropIfExists('organization_person_skills');
        Schema::dropIfExists('organization_job_profile_soft_skills');
        Schema::dropIfExists('organization_job_profile_skills');
        Schema::dropIfExists('organization_soft_skills');
        Schema::dropIfExists('organization_skills');
    }
};
