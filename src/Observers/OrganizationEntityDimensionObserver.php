<?php

namespace Platform\Organization\Observers;

use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionValue;
use Platform\Organization\Models\OrganizationEntity;

class OrganizationEntityDimensionObserver
{
    /**
     * All dimensions with value_source='entity' mirror OrganizationEntities.
     */
    private function getEntitySourcedDefinitions()
    {
        return OrganizationDimensionDefinition::where('value_source', 'entity')
            ->where('is_active', true)
            ->get();
    }

    public function created(OrganizationEntity $entity): void
    {
        foreach ($this->getEntitySourcedDefinitions() as $def) {
            $exists = OrganizationDimensionValue::where('dimension_definition_id', $def->id)
                ->where('metadata->source_entity_id', $entity->id)
                ->exists();

            if ($exists) {
                continue;
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
    }

    public function updated(OrganizationEntity $entity): void
    {
        if (!$entity->isDirty(['name', 'code'])) {
            return;
        }

        foreach ($this->getEntitySourcedDefinitions() as $def) {
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
    }

    public function deleted(OrganizationEntity $entity): void
    {
        foreach ($this->getEntitySourcedDefinitions() as $def) {
            $dimValue = OrganizationDimensionValue::where('dimension_definition_id', $def->id)
                ->where('metadata->source_entity_id', $entity->id)
                ->first();

            if ($dimValue) {
                $dimValue->delete();
            }
        }
    }
}
