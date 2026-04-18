<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_run_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('run_id')->constrained('organization_process_runs')->cascadeOnDelete();
            $table->foreignId('process_step_id')->constrained('organization_process_steps')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->integer('position');
            $table->integer('active_duration_minutes')->nullable();
            $table->integer('wait_duration_minutes')->nullable();
            $table->boolean('wait_override')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'position']);
            $table->unique(['run_id', 'process_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_process_run_steps');
    }
};
