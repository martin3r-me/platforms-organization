<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_entity_relationships', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // für externe Referenzen, eindeutig
            
            // Team und User
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Von-Entity
            $table->foreignId('from_entity_id')
                  ->constrained('organization_entities')
                  ->onDelete('cascade');
            
            // Zu-Entity
            $table->foreignId('to_entity_id')
                  ->constrained('organization_entities')
                  ->onDelete('cascade');
            
            // Relation Type (FK statt Enum!)
            $table->foreignId('relation_type_id')
                  ->constrained('organization_entity_relation_types')
                  ->onDelete('restrict');
            
            // Zeitliche Gültigkeit (optional)
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            
            // Metadaten
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Unique: Gleiche Relation nicht doppelt (nur für nicht-gelöschte)
            $table->unique(['from_entity_id', 'to_entity_id', 'relation_type_id'], 
                   'org_entity_relationships_unique');
            
            // Indizes
            $table->index(['from_entity_id', 'team_id']);
            $table->index(['to_entity_id', 'team_id']);
            $table->index(['relation_type_id', 'team_id']);
            $table->index(['team_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_entity_relationships');
    }
};

