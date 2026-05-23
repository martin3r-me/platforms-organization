<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop from organization_entities
        if (Schema::hasColumn('organization_entities', 'vsm_system_id')) {
            Schema::table('organization_entities', function (Blueprint $table) {
                $table->dropForeign(['vsm_system_id']);
                $table->dropIndex(['vsm_system_id', 'is_active']);
                $table->dropColumn('vsm_system_id');
            });
        }

        // Drop from organization_processes
        if (Schema::hasColumn('organization_processes', 'vsm_system_id')) {
            Schema::table('organization_processes', function (Blueprint $table) {
                $table->dropForeign(['vsm_system_id']);
                $table->dropIndex(['vsm_system_id']);
                $table->dropColumn('vsm_system_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('organization_entities', function (Blueprint $table) {
            $table->foreignId('vsm_system_id')
                ->nullable()
                ->after('entity_type_id')
                ->constrained('organization_vsm_systems')
                ->nullOnDelete();
            $table->index(['vsm_system_id', 'is_active']);
        });

        Schema::table('organization_processes', function (Blueprint $table) {
            $table->foreignId('vsm_system_id')
                ->nullable()
                ->after('owner_entity_id')
                ->constrained('organization_vsm_systems')
                ->nullOnDelete();
            $table->index(['vsm_system_id']);
        });
    }
};
