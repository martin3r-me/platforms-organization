<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_process_triggers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('process_id')->constrained('organization_processes')->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('trigger_type');
            $table->foreignId('entity_type_id')->nullable()->constrained('organization_entity_types')->nullOnDelete();
            $table->foreignId('entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->foreignId('source_process_id')->nullable()->constrained('organization_processes')->nullOnDelete();
            $table->foreignId('interlink_id')->nullable()->constrained('organization_interlinks')->nullOnDelete();
            $table->string('schedule_expression')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['process_id']);
            $table->index(['entity_type_id']);
            $table->index(['source_process_id']);
            $table->index(['interlink_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_process_triggers');
    }
};
