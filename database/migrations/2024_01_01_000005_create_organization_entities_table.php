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
        Schema::create('organization_entities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // für externe Referenzen, eindeutig
            $table->string('name');
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            
            // Foreign Keys zu Lookup-Tables
            $table->foreignId('entity_type_id')
                  ->constrained('organization_entity_types')
                  ->onDelete('restrict');
            $table->foreignId('vsm_system_id')
                  ->nullable()
                  ->constrained('organization_vsm_systems')
                  ->onDelete('set null');
            
            // Hierarchische Beziehung
            $table->foreignId('parent_entity_id')
                  ->nullable()
                  ->constrained('organization_entities')
                  ->onDelete('cascade');
            
            // Status und Metadaten
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // für zukünftige Erweiterungen
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indizes
            $table->index(['team_id', 'is_active']);
            $table->index(['entity_type_id', 'is_active']);
            $table->index(['vsm_system_id', 'is_active']);
            $table->index(['parent_entity_id', 'is_active']);
            $table->index(['uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_entities');
    }
};
