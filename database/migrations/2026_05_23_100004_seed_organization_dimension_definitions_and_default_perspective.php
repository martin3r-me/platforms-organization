<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Seed dimension definitions
        $definitions = [
            [
                'key' => 'vsm-system',
                'name' => 'VSM-System',
                'description' => 'Viable System Model — Systemfunktion (S1–S5) aus einer bestimmten Perspektive',
                'value_source' => 'lookup',
                'mode' => 'single',
                'team_scoped' => false,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'cost-center',
                'name' => 'Kostenstelle',
                'description' => 'Finanzielle Zuordnung — welcher Kostenstelle werden Aufwände zugerechnet',
                'value_source' => 'lookup',
                'mode' => 'multi_percent',
                'team_scoped' => true,
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'vsm-function',
                'name' => 'VSM-Funktion',
                'description' => 'Konkrete Funktion innerhalb eines Wertstroms',
                'value_source' => 'lookup',
                'mode' => 'multi',
                'team_scoped' => true,
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('organization_dimension_definitions')->insert($definitions);

        // 2. Seed VSM-System values (global, no team)
        $vsmDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'vsm-system')->value('id');

        $vsmSystems = DB::table('organization_vsm_systems')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($vsmSystems as $vsm) {
            DB::table('organization_dimension_values')->insert([
                'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                'dimension_definition_id' => $vsmDefId,
                'code' => $vsm->code,
                'name' => $vsm->name,
                'description' => $vsm->description,
                'team_id' => null, // global
                'is_active' => true,
                'sort_order' => $vsm->sort_order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. Copy cost centers as dimension values
        $ccDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'cost-center')->value('id');

        $costCenters = DB::table('organization_cost_centers')
            ->whereNull('deleted_at')
            ->get();

        foreach ($costCenters as $cc) {
            DB::table('organization_dimension_values')->insert([
                'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                'dimension_definition_id' => $ccDefId,
                'code' => $cc->code,
                'name' => $cc->name,
                'description' => $cc->description,
                'team_id' => $cc->team_id,
                'is_active' => $cc->is_active,
                'sort_order' => 0,
                'metadata' => json_encode(['legacy_cost_center_id' => $cc->id, 'legacy_root_entity_id' => $cc->root_entity_id ?? null]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 4. Copy VSM functions as dimension values
        $vfDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'vsm-function')->value('id');

        $vsmFunctions = DB::table('organization_vsm_functions')
            ->whereNull('deleted_at')
            ->get();

        foreach ($vsmFunctions as $vf) {
            DB::table('organization_dimension_values')->insert([
                'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                'dimension_definition_id' => $vfDefId,
                'code' => $vf->code,
                'name' => $vf->name,
                'description' => $vf->description,
                'team_id' => $vf->team_id,
                'is_active' => $vf->is_active,
                'sort_order' => 0,
                'metadata' => json_encode(['legacy_vsm_function_id' => $vf->id, 'legacy_root_entity_id' => $vf->root_entity_id ?? null]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 5. Create default perspective per team + migrate vsm_system_id assignments
        $teams = DB::table('teams')->pluck('id');

        foreach ($teams as $teamId) {
            // Create default perspective
            $perspectiveId = DB::table('organization_perspectives')->insertGetId([
                'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                'team_id' => $teamId,
                'name' => 'Standard',
                'description' => 'Standard-Perspektive — bildet den aktuellen Status quo ab',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Migrate entity vsm_system_id assignments to dimension links
            $entities = DB::table('organization_entities')
                ->where('team_id', $teamId)
                ->whereNotNull('vsm_system_id')
                ->whereNull('deleted_at')
                ->get();

            foreach ($entities as $entity) {
                // Find the dimension value for this vsm_system
                $vsmSystem = DB::table('organization_vsm_systems')
                    ->where('id', $entity->vsm_system_id)
                    ->first();

                if (!$vsmSystem) {
                    continue;
                }

                $dimensionValueId = DB::table('organization_dimension_values')
                    ->where('dimension_definition_id', $vsmDefId)
                    ->where('code', $vsmSystem->code)
                    ->value('id');

                if (!$dimensionValueId) {
                    continue;
                }

                DB::table('organization_dimension_links')->insert([
                    'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                    'dimension_definition_id' => $vsmDefId,
                    'dimension_value_id' => $dimensionValueId,
                    'linkable_type' => 'organization_entity',
                    'linkable_id' => $entity->id,
                    'perspective_id' => $perspectiveId,
                    'team_id' => $teamId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('organization_dimension_links')->truncate();
        DB::table('organization_perspectives')->truncate();
        DB::table('organization_dimension_values')->truncate();
        DB::table('organization_dimension_definitions')->truncate();
    }
};
