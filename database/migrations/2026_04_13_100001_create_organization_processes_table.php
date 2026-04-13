<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_processes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('owner_entity_id')->nullable()->constrained('organization_entities')->nullOnDelete();
            $table->foreignId('vsm_system_id')->nullable()->constrained('organization_vsm_systems')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active', 'deleted_at']);
            $table->index(['status', 'team_id']);
            $table->index(['owner_entity_id']);
            $table->index(['vsm_system_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_processes');
    }
};
