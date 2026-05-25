<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $vsmDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'vsm-system')
            ->value('id');

        if (! $vsmDefId) {
            return;
        }

        // Skip if already exists
        if (DB::table('organization_dimension_values')
            ->where('dimension_definition_id', $vsmDefId)
            ->where('code', 'ENV')
            ->exists()
        ) {
            return;
        }

        DB::table('organization_dimension_values')->insert([
            'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
            'dimension_definition_id' => $vsmDefId,
            'code' => 'ENV',
            'name' => 'Umwelt',
            'description' => 'Externe Umwelt ausserhalb der Systemgrenze. Entitaeten die beobachtet, aber nicht gesteuert werden — Maerkte, Wettbewerber, Makro-Indikatoren, regulatorisches Umfeld.',
            'team_id' => null,
            'is_active' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $vsmDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'vsm-system')
            ->value('id');

        if ($vsmDefId) {
            DB::table('organization_dimension_values')
                ->where('dimension_definition_id', $vsmDefId)
                ->where('code', 'ENV')
                ->delete();
        }
    }
};
