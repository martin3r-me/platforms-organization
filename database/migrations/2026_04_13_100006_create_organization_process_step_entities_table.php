<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_step_entities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('process_step_id')->constrained('organization_process_steps')->cascadeOnDelete();
            $table->foreignId('entity_type_id')->nullable()->constrained('organization_entity_types')->nullOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->string('role');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['process_step_id', 'entity_type_id', 'entity_id', 'role'],
                'org_pse_unique'
            );
            $table->index(['entity_type_id']);
            $table->index(['entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_process_step_entities');
    }
};
