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
        Schema::create('organization_entity_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // person, team, business_unit, etc.
            $table->string('name'); // Person, Team, Business Unit, etc.
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // user, users, factory, etc.
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            // Foreign Key zu Entity Type Groups
            $table->foreignId('entity_type_group_id')
                  ->constrained('organization_entity_type_groups')
                  ->onDelete('restrict');
            
            // JSON für zukünftige Erweiterungen
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            $table->index(['is_active', 'sort_order']);
            $table->index(['entity_type_group_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_entity_types');
    }
};
