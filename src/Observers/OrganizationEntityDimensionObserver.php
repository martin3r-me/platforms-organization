<?php

namespace Platform\Organization\Observers;

use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionValue;
use Platform\Organization\Models\OrganizationEntity;

class OrganizationEntityDimensionObserver
{
    public function created(OrganizationEntity $entity): void
    {
        $def = OrganizationDimensionDefinition::where('key', 'entity')->first();
        if (!$def) {
            return;
        }

        OrganizationDimensionValue::create([
            'dimension_definition_id' => $def->id,
            'code' => $entity->code,
            'name' => $entity->name,
            'team_id' => $entity->team_id,
            'is_active' => true,
            'sort_order' => 0,
            'metadata' => ['source_entity_id' => $entity->id],
        ]);
    }

    public function updated(OrganizationEntity $entity): void
    {
        if (!$entity->isDirty(['name', 'code'])) {
            return;
        }

        $def = OrganizationDimensionDefinition::where('key', 'entity')->first();
        if (!$def) {
            return;
        }

        $dimValue = OrganizationDimensionValue::where('dimension_definition_id', $def->id)
            ->where('metadata->source_entity_id', $entity->id)
            ->first();

        if ($dimValue) {
            $dimValue->update([
                'code' => $entity->code,
                'name' => $entity->name,
            ]);
        }
    }

    public function deleted(OrganizationEntity $entity): void
    {
        $def = OrganizationDimensionDefinition::where('key', 'entity')->first();
        if (!$def) {
            return;
        }

        $dimValue = OrganizationDimensionValue::where('dimension_definition_id', $def->id)
            ->where('metadata->source_entity_id', $entity->id)
            ->first();

        if ($dimValue) {
            $dimValue->delete();
        }
    }
}
