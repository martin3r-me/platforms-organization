<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_person_job_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->foreignId('person_entity_id')
                  ->constrained('organization_entities')
                  ->cascadeOnDelete();

            $table->foreignId('job_profile_id')
                  ->constrained('organization_job_profiles')
                  ->cascadeOnDelete();

            $table->unsignedTinyInteger('percentage')->default(100); // 0–100, soft (kein Hard-Constraint)
            $table->boolean('is_primary')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['person_entity_id']);
            $table->index(['job_profile_id']);
            $table->index(['team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_person_job_profiles');
    }
};
