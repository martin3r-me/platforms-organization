<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Create DimensionDefinition for 'cost-driver' (idempotent)
        $costDriverDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'cost-driver')->value('id');

        if (!$costDriverDefId) {
            DB::table('organization_dimension_definitions')->insert([
                'key' => 'cost-driver',
                'name' => 'Kostenverursacher',
                'description' => 'Wer verursacht die Kosten? Prozentuale Aufteilung auf Organisationseinheiten.',
                'value_source' => 'entity',
                'mode' => 'multi_percent',
                'team_scoped' => true,
                'is_active' => true,
                'sort_order' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $costDriverDefId = DB::table('organization_dimension_definitions')
                ->where('key', 'cost-driver')->value('id');
        }

        // 2. Mirror DimensionValues from entity dimension (same entities, different dimension)
        $entityDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'entity')->value('id');

        if (!$entityDefId) {
            return; // entity dimension not set up yet
        }

        $entityValues = DB::table('organization_dimension_values')
            ->where('dimension_definition_id', $entityDefId)
            ->whereNull('deleted_at')
            ->get();

        // Existing cost-driver values: source_entity_id => id
        $existingValues = DB::table('organization_dimension_values')
            ->where('dimension_definition_id', $costDriverDefId)
            ->get();

        $existingEntityIds = [];
        foreach ($existingValues as $v) {
            $meta = json_decode($v->metadata, true);
            if (isset($meta['source_entity_id'])) {
                $existingEntityIds[$meta['source_entity_id']] = true;
            }
        }

        foreach ($entityValues as $ev) {
            $meta = json_decode($ev->metadata, true);
            $sourceEntityId = $meta['source_entity_id'] ?? null;

            if (!$sourceEntityId || isset($existingEntityIds[$sourceEntityId])) {
                continue;
            }

            DB::table('organization_dimension_values')->insert([
                'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                'dimension_definition_id' => $costDriverDefId,
                'code' => $ev->code,
                'name' => $ev->name,
                'team_id' => $ev->team_id,
                'is_active' => true,
                'sort_order' => $ev->sort_order,
                'metadata' => json_encode(['source_entity_id' => $sourceEntityId]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $costDriverDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'cost-driver')->value('id');

        if ($costDriverDefId) {
            // Remove links first, then values, then definition
            DB::table('organization_dimension_links')
                ->where('dimension_definition_id', $costDriverDefId)
                ->delete();

            DB::table('organization_dimension_values')
                ->where('dimension_definition_id', $costDriverDefId)
                ->forceDelete();

            DB::table('organization_dimension_definitions')
                ->where('id', $costDriverDefId)
                ->delete();
        }
    }
};
