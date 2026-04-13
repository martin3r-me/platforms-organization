<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_flows', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('process_id')->constrained('organization_processes')->cascadeOnDelete();
            $table->foreignId('from_step_id')->constrained('organization_process_steps')->cascadeOnDelete();
            $table->foreignId('to_step_id')->constrained('organization_process_steps')->cascadeOnDelete();
            $table->string('condition_label')->nullable();
            $table->json('condition_expression')->nullable();
            $table->boolean('is_default')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['from_step_id', 'to_step_id']);
            $table->index(['process_id']);
            $table->index(['to_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_process_flows');
    }
};
