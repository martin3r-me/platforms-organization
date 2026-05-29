<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_inference_runs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedBigInteger('trigger_id')->nullable();
            $table->string('trigger_type', 30);
            // scheduled | event | on_demand | inquiry_response | meta_event
            $table->string('status', 20)->default('running');
            // running | completed | failed
            $table->unsignedInteger('prompts_evaluated')->default(0);
            $table->unsignedInteger('entities_analyzed')->default(0);
            $table->unsignedInteger('signals_created')->default(0);
            $table->unsignedInteger('inquiries_created')->default(0);
            $table->unsignedInteger('memory_updates')->default(0);
            $table->unsignedInteger('do_nothing_count')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('llm_model', 50)->nullable();
            $table->json('token_usage')->nullable();
            $table->text('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'trigger_type', 'created_at'], 'run_team_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_inference_runs');
    }
};
