<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_contexts', function (Blueprint $table) {
            // Alten Unique Constraint entfernen (wenn er existiert)
            $table->dropUnique('organization_contexts_unique');
            
            // Neuer Unique Constraint: Eine Module Entity kann nur EINMAL gelinkt werden
            $table->unique(['contextable_type', 'contextable_id'], 'organization_contexts_contextable_unique');
        });
    }

    public function down(): void
    {
        Schema::table('organization_contexts', function (Blueprint $table) {
            $table->dropUnique('organization_contexts_contextable_unique');
            
            // Alten Constraint wiederherstellen
            $table->unique(['contextable_type', 'contextable_id', 'organization_entity_id'], 'organization_contexts_unique');
        });
    }
};

