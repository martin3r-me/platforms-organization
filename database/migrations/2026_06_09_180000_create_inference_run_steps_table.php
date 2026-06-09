<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detail-Logging pro Inference-Run: jeder LLM-Iterations-Step
 * (Tool-Call, Assistant-Message, Error) als eigene Zeile.
 *
 * Macht in der UI sichtbar, *was* der Agent in welcher Reihenfolge getan
 * hat — welche Tools mit welchen Argumenten, welche Ergebnisse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_inference_run_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inference_run_id')
                ->constrained('organization_inference_runs', 'id', 'inf_run_step_run_fk')
                ->cascadeOnDelete();

            $table->foreignId('inference_prompt_id')
                ->nullable()
                ->constrained('organization_signal_inference_prompts', 'id', 'inf_run_step_prompt_fk')
                ->nullOnDelete();

            $table->unsignedInteger('step_index')->comment('Aufsteigender Index pro Run, beginnt bei 0');
            $table->string('step_type', 32)->comment('tool_call, assistant_message, error');

            $table->string('tool_name')->nullable();
            $table->json('arguments')->nullable();
            $table->json('result')->nullable()->comment('Kompakter Result-Output (truncated bei sehr grossen Responses)');
            $table->boolean('result_ok')->default(true);
            $table->text('error_message')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('occurred_at')->nullable();

            $table->timestamps();

            $table->index(['inference_run_id', 'step_index'], 'inf_run_step_order_idx');
            $table->index('tool_name', 'inf_run_step_tool_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_inference_run_steps');
    }
};
