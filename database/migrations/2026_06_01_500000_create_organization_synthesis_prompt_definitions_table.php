<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_synthesis_prompt_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('report_type', 20);
            // weekly | monthly | quarterly
            $table->text('system_prompt');
            $table->text('user_message_template');
            // Supports placeholders: {report_type} {period_start} {period_end} {signals_count} {signals_json}
            $table->unsignedInteger('max_signals')->default(100);
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('max_tokens')->default(8192);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'report_type', 'is_active'], 'synprompt_team_type_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_synthesis_prompt_definitions');
    }
};
