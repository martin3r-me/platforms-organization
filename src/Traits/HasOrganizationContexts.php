<?php

namespace Platform\Organization\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Platform\Organization\Models\OrganizationContext;
use Platform\Organization\Models\OrganizationEntity;

trait HasOrganizationContexts
{
    /**
     * Polymorphe Beziehung zu Organization Contexts
     */
    public function organizationContexts(): MorphMany
    {
        return $this->morphMany(OrganizationContext::class, 'contextable');
    }

    /**
     * Aktive Organization Contexts
     */
    public function activeOrganizationContexts(): MorphMany
    {
        return $this->organizationContexts()->where('is_active', true);
    }

    /**
     * Verknüpfe diese Module Entity mit einer Organization Entity
     * 
     * @param OrganizationEntity $entity Die Organization Entity
     * @param array|null $includeChildrenRelations Welche Relations sollen inkludiert werden? (z.B. ['tasks', 'projectSlots.tasks'])
     * @return OrganizationContext
     */
    public function attachOrganizationContext(OrganizationEntity $entity, ?array $includeChildrenRelations = null): OrganizationContext
    {
        $team = auth()->user()?->currentTeamRelation;
        
        return $this->organizationContexts()->firstOrCreate(
            [
                'organization_entity_id' => $entity->id,
                'contextable_type' => $this->getMorphClass(),
                'contextable_id' => $this->getKey(),
            ],
            [
                'team_id' => $team?->id,
                'include_children_relations' => $includeChildrenRelations,
                'is_active' => true,
            ]
        );
    }

    /**
     * Entferne Verknüpfung zu einer Organization Entity
     */
    public function detachOrganizationContext(OrganizationEntity $entity): bool
    {
        return $this->organizationContexts()
            ->where('organization_entity_id', $entity->id)
            ->delete();
    }

    /**
     * Prüfe ob diese Entity mit einer Organization Entity verknüpft ist
     */
    public function hasOrganizationContext(OrganizationEntity $entity): bool
    {
        return $this->organizationContexts()
            ->where('organization_entity_id', $entity->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Hole alle verknüpften Organization Entities
     */
    public function getOrganizationEntities()
    {
        return OrganizationEntity::whereHas('contexts', function ($query) {
            $query->where('contextable_type', $this->getMorphClass())
                  ->where('contextable_id', $this->getKey())
                  ->where('is_active', true);
        })->get();
    }
}

