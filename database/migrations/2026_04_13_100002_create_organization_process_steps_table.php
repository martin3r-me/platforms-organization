<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('process_id')->constrained('organization_processes')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->string('step_type')->default('action');
            $table->unsignedInteger('duration_target_minutes')->nullable();
            $table->unsignedInteger('wait_target_minutes')->nullable();
            $table->string('corefit_classification')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['process_id', 'position']);
            $table->index(['process_id', 'is_active']);
            $table->index(['team_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_process_steps');
    }
};
