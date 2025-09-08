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
        Schema::create('organization_entity_relation_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // reports_to, works_for, is_part_of, etc.
            $table->string('name'); // Berichtet an, Arbeitet für, Ist Teil von, etc.
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // arrow-up, briefcase, link, etc.
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_directional')->default(true); // Richtung der Beziehung
            $table->boolean('is_hierarchical')->default(false); // Hierarchische Beziehung
            $table->boolean('is_reciprocal')->default(false); // Gegenseitige Beziehung
            
            // JSON für zukünftige Erweiterungen
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order'], 'org_entity_rel_types_active_order_idx');
            $table->index(['is_directional', 'is_active'], 'org_entity_rel_types_directional_active_idx');
            $table->index(['is_hierarchical', 'is_active'], 'org_entity_rel_types_hierarchical_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_entity_relation_types');
    }
};
