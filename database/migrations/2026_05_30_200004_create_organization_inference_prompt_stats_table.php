<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_inference_prompt_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inference_prompt_id')->constrained('organization_signal_inference_prompts')->cascadeOnDelete();
            $table->date('period');
            $table->unsignedInteger('signals_created')->default(0);
            $table->unsignedInteger('signals_acknowledged')->default(0);
            $table->unsignedInteger('signals_dismissed')->default(0);
            $table->unsignedInteger('signals_resolved')->default(0);
            $table->float('precision')->default(0.0);
            $table->json('entity_type_breakdown')->nullable();
            $table->timestamps();

            $table->index(['inference_prompt_id', 'period'], 'pstat_prompt_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_inference_prompt_stats');
    }
};
