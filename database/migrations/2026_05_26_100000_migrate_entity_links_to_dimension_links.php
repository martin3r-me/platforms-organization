<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if already seeded
        if (DB::table('organization_dimension_definitions')->where('key', 'entity')->exists()) {
            return;
        }

        $now = now();

        // 1. Create DimensionDefinition for 'entity'
        DB::table('organization_dimension_definitions')->insert([
            'key' => 'entity',
            'name' => 'Organisationseinheit',
            'description' => 'Verknuepfung mit Organisationseinheiten — jede Entity hat einen gespiegelten DimensionValue',
            'value_source' => 'entity',
            'mode' => 'multi',
            'team_scoped' => true,
            'is_active' => true,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $entityDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'entity')->value('id');

        // 2. Create DimensionValue for each OrganizationEntity
        $entities = DB::table('organization_entities')
            ->whereNull('deleted_at')
            ->get();

        foreach ($entities as $entity) {
            DB::table('organization_dimension_values')->insert([
                'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                'dimension_definition_id' => $entityDefId,
                'code' => $entity->code,
                'name' => $entity->name,
                'team_id' => $entity->team_id,
                'is_active' => true,
                'sort_order' => 0,
                'metadata' => json_encode(['source_entity_id' => $entity->id]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Build map: entity_id → dimension_value_id
        $dimValues = DB::table('organization_dimension_values')
            ->where('dimension_definition_id', $entityDefId)
            ->get();

        $entityToDimValue = [];
        foreach ($dimValues as $dv) {
            $meta = json_decode($dv->metadata, true);
            if (isset($meta['source_entity_id'])) {
                $entityToDimValue[$meta['source_entity_id']] = $dv->id;
            }
        }

        // 3. Migrate EntityLink rows to DimensionLink rows
        $entityLinks = DB::table('organization_entity_links')->get();

        foreach ($entityLinks as $link) {
            $dimValueId = $entityToDimValue[$link->entity_id] ?? null;
            if (!$dimValueId) {
                continue;
            }

            DB::table('organization_dimension_links')->insert([
                'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                'dimension_definition_id' => $entityDefId,
                'dimension_value_id' => $dimValueId,
                'linkable_type' => $link->linkable_type,
                'linkable_id' => $link->linkable_id,
                'perspective_id' => null,
                'start_date' => $link->start_date ?? null,
                'end_date' => $link->end_date ?? null,
                'percentage' => $link->percentage ?? null,
                'is_primary' => $link->is_primary ?? false,
                'team_id' => $link->team_id,
                'created_by_user_id' => $link->created_by_user_id ?? null,
                'created_at' => $link->created_at ?? $now,
                'updated_at' => $link->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        $entityDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'entity')->value('id');

        if ($entityDefId) {
            DB::table('organization_dimension_links')
                ->where('dimension_definition_id', $entityDefId)
                ->delete();

            DB::table('organization_dimension_values')
                ->where('dimension_definition_id', $entityDefId)
                ->delete();

            DB::table('organization_dimension_definitions')
                ->where('id', $entityDefId)
                ->delete();
        }
    }
};
