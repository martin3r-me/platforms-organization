<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_memory_entries', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->foreignId('inference_prompt_id')->nullable()->constrained('organization_signal_inference_prompts')->nullOnDelete();
            $table->string('memory_type', 30);
            // entity_profile | baseline | suppression | relationship | prompt_experience | inquiry_outcome
            $table->text('content');
            $table->json('structured_data')->nullable();
            $table->float('confidence')->default(0.5);
            $table->string('source_type', 30);
            // signal_feedback | inquiry_response | inference | implicit_feedback | manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('reinforcement_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'entity_id', 'memory_type', 'is_active'], 'mem_team_entity_type_active');
            $table->index(['team_id', 'inference_prompt_id', 'memory_type'], 'mem_team_prompt_type');
            $table->index('valid_until', 'mem_valid_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_memory_entries');
    }
};
