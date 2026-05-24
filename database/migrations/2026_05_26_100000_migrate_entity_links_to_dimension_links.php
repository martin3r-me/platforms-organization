<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Create DimensionDefinition for 'entity' (idempotent)
        $entityDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'entity')->value('id');

        if (!$entityDefId) {
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
        }

        // 2. Create DimensionValue for each OrganizationEntity (skip existing)
        $entities = DB::table('organization_entities')
            ->whereNull('deleted_at')
            ->get();

        // Existing DimensionValues: source_entity_id → dim_value_id
        $existingDimValues = DB::table('organization_dimension_values')
            ->where('dimension_definition_id', $entityDefId)
            ->get();

        $entityToDimValue = [];
        foreach ($existingDimValues as $dv) {
            $meta = json_decode($dv->metadata, true);
            if (isset($meta['source_entity_id'])) {
                $entityToDimValue[$meta['source_entity_id']] = $dv->id;
            }
        }

        foreach ($entities as $entity) {
            if (isset($entityToDimValue[$entity->id])) {
                continue; // Already has a DimensionValue
            }

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

        // Rebuild map after inserts
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

        // 3. Migrate EntityLink rows to DimensionLink rows (skip existing)
        $entityLinks = DB::table('organization_entity_links')->get();

        foreach ($entityLinks as $link) {
            $dimValueId = $entityToDimValue[$link->entity_id] ?? null;
            if (!$dimValueId) {
                continue;
            }

            // Check if this link already exists
            $exists = DB::table('organization_dimension_links')
                ->where('dimension_definition_id', $entityDefId)
                ->where('dimension_value_id', $dimValueId)
                ->where('linkable_type', $link->linkable_type)
                ->where('linkable_id', $link->linkable_id)
                ->exists();

            if ($exists) {
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
