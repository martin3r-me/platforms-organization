<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_contexts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Polymorphe Beziehung zu Module Entities (z.B. Planner Project, CRM Contact)
            $table->string('contextable_type');
            $table->unsignedBigInteger('contextable_id');
            
            // Foreign Key zu Organization Entity
            $table->foreignId('organization_entity_id')
                  ->constrained('organization_entities')
                  ->cascadeOnDelete();
            
            // Team für Multi-Tenancy
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            
            // Cascade-Relations: welche Relations sollen inkludiert werden? (z.B. ['tasks', 'projectSlots.tasks'])
            $table->json('include_children_relations')->nullable();
            
            // Status und Metadaten
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indizes
            $table->index(['contextable_type', 'contextable_id'], 'organization_contexts_contextable_index');
            $table->index(['organization_entity_id', 'is_active']);
            $table->index(['team_id', 'is_active']);
            
            // Unique: ein Module Entity kann nur einmal an eine Organization Entity gehängt werden
            $table->unique(['contextable_type', 'contextable_id', 'organization_entity_id'], 'organization_contexts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_contexts');
    }
};

