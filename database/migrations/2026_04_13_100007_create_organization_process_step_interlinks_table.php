<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_step_interlinks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('process_step_id')->constrained('organization_process_steps')->cascadeOnDelete();
            $table->foreignId('interlink_id')->constrained('organization_interlinks')->cascadeOnDelete();
            $table->string('role');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['process_step_id', 'interlink_id', 'role'],
                'org_psi_unique'
            );
            $table->index(['interlink_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_process_step_interlinks');
    }
};
