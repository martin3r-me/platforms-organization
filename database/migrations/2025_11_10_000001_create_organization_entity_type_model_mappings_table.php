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
        Schema::create('organization_entity_type_model_mappings', function (Blueprint $table) {
            $table->id();
            
            // Entity Type (FK)
            $table->foreignId('entity_type_id')
                  ->constrained('organization_entity_types')
                  ->onDelete('cascade');
            
            // Module Key (z.B. 'planner', 'crm', 'core')
            $table->string('module_key');
            
            // Model Class (vollständiger Namespace, z.B. 'Platform\Planner\Models\PlannerProject')
            $table->string('model_class');
            
            // Bidirektionalität: Kann von beiden Seiten verlinkt werden?
            $table->boolean('is_bidirectional')->default(true);
            
            // Aktiv/Inaktiv
            $table->boolean('is_active')->default(true);
            
            // Sortierung
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            
            // Unique: Gleiche Mapping nicht doppelt
            $table->unique(['entity_type_id', 'module_key', 'model_class'], 
                   'org_entity_type_model_mappings_unique');
            
            // Indizes mit expliziten, kurzen Namen (MySQL max 64 Zeichen)
            $table->index(['entity_type_id', 'is_active'], 'org_etm_entity_active_idx');
            $table->index(['module_key', 'is_active'], 'org_etm_module_active_idx');
            $table->index(['is_active', 'sort_order'], 'org_etm_active_sort_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_entity_type_model_mappings');
    }
};

