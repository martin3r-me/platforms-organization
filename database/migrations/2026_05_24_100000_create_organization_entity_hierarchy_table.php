<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_entity_hierarchy')) {
            return;
        }

        Schema::create('organization_entity_hierarchy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perspective_id')
                ->constrained('organization_perspectives')
                ->cascadeOnDelete();
            $table->foreignId('entity_id')
                ->constrained('organization_entities')
                ->cascadeOnDelete();
            $table->foreignId('parent_entity_id')
                ->nullable()
                ->constrained('organization_entities')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['perspective_id', 'entity_id'], 'org_hierarchy_perspective_entity_unique');
            $table->index(['perspective_id', 'parent_entity_id'], 'org_hierarchy_perspective_parent_idx');
            $table->index(['team_id', 'perspective_id'], 'org_hierarchy_team_perspective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_entity_hierarchy');
    }
};
