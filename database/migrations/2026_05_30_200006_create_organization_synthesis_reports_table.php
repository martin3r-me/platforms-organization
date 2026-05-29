<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_synthesis_reports', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->unsignedBigInteger('inference_run_id')->nullable();
            $table->string('report_type', 20);
            // weekly | monthly | quarterly | ad_hoc
            $table->date('period_start');
            $table->date('period_end');
            $table->string('title');
            $table->longText('content');
            $table->json('structured_summary')->nullable();
            $table->json('signals_included')->nullable();
            $table->json('inquiries_included')->nullable();
            $table->json('algedonic_signals')->nullable();
            $table->json('recipient_scope')->nullable();
            $table->string('status', 20)->default('draft');
            // draft | published | archived
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'report_type', 'period_start'], 'synth_team_type_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_synthesis_reports');
    }
};
