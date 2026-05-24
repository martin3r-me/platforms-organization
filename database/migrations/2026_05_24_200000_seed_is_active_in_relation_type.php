<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('organization_entity_relation_types')->updateOrInsert(
            ['code' => 'is_active_in'],
            [
                'code' => 'is_active_in',
                'name' => 'Ist aktiv in',
                'description' => 'Person ist aktiv in einer Capability-Area (metadata.percentage fuer Anteil)',
                'icon' => 'user-group',
                'sort_order' => 21,
                'is_active' => true,
                'is_directional' => true,
                'is_hierarchical' => false,
                'is_reciprocal' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('organization_entity_relation_types')
            ->where('code', 'is_active_in')
            ->delete();
    }
};
