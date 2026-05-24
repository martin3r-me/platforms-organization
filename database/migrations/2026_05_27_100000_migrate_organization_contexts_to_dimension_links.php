<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Get entity dimension definition (must exist from prior migration)
        $entityDefId = DB::table('organization_dimension_definitions')
            ->where('key', 'entity')->value('id');

        if (!$entityDefId) {
            return; // Nothing to do — entity dimension not set up
        }

        // 2. Build entity_id → dimension_value_id map
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

        // 3. Load active organization_contexts
        $contexts = DB::table('organization_contexts')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotNull('organization_entity_id')
            ->get();

        // 4. Build Laravel morph map for normalization
        $morphMap = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        $reverseMorphMap = array_flip($morphMap);

        foreach ($contexts as $ctx) {
            $entityId = $ctx->organization_entity_id;

            // Ensure DimensionValue exists for this entity
            $dimValueId = $entityToDimValue[$entityId] ?? null;
            if (!$dimValueId) {
                // Auto-create dimension value
                $entity = DB::table('organization_entities')->find($entityId);
                if (!$entity) {
                    continue;
                }

                DB::table('organization_dimension_values')->insert([
                    'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                    'dimension_definition_id' => $entityDefId,
                    'code' => $entity->code ?? "entity-{$entityId}",
                    'name' => $entity->name,
                    'team_id' => $entity->team_id,
                    'is_active' => true,
                    'sort_order' => 0,
                    'metadata' => json_encode(['source_entity_id' => $entityId]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $dimValueId = DB::table('organization_dimension_values')
                    ->where('dimension_definition_id', $entityDefId)
                    ->where('metadata->source_entity_id', $entityId)
                    ->value('id');

                if (!$dimValueId) {
                    continue;
                }

                $entityToDimValue[$entityId] = $dimValueId;
            }

            // Normalize contextable_type — use morph alias if available, otherwise keep FQCN
            $linkableType = $ctx->contextable_type;
            // If it's already a short alias (in morph map values), keep it
            // If it's a FQCN, check if there's a morph alias
            if (isset($reverseMorphMap[$linkableType])) {
                $linkableType = $reverseMorphMap[$linkableType];
            }

            $linkableId = $ctx->contextable_id;

            // Check if link already exists (idempotent)
            $exists = DB::table('organization_dimension_links')
                ->where('dimension_definition_id', $entityDefId)
                ->where('dimension_value_id', $dimValueId)
                ->where('linkable_type', $linkableType)
                ->where('linkable_id', $linkableId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('organization_dimension_links')->insert([
                'uuid' => (string) \Symfony\Component\Uid\UuidV7::generate(),
                'dimension_definition_id' => $entityDefId,
                'dimension_value_id' => $dimValueId,
                'linkable_type' => $linkableType,
                'linkable_id' => $linkableId,
                'perspective_id' => null,
                'start_date' => null,
                'end_date' => null,
                'percentage' => null,
                'is_primary' => false,
                'team_id' => $ctx->team_id,
                'created_by_user_id' => null,
                'created_at' => $ctx->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No rollback — data is additive and idempotent.
        // OrganizationContext table remains untouched.
    }
};
