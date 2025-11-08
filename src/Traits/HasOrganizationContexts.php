<?php

namespace Platform\Organization\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Platform\Organization\Models\OrganizationContext;
use Platform\Organization\Models\OrganizationEntity;

trait HasOrganizationContexts
{
    /**
     * Polymorphe Beziehung zu Organization Context
     * Eine Module Entity kann nur EINMAL an eine Organization Entity gelinkt werden
     */
    public function organizationContext(): MorphOne
    {
        return $this->morphOne(OrganizationContext::class, 'contextable');
    }

    /**
     * Aktiver Organization Context
     */
    public function activeOrganizationContext()
    {
        return $this->organizationContext()->where('is_active', true);
    }

    /**
     * Verknüpfe diese Module Entity mit einer Organization Entity
     * Eine Entity kann nur EINMAL gelinkt werden - bei erneutem Aufruf wird die Verlinkung aktualisiert
     * 
     * @param OrganizationEntity $entity Die Organization Entity
     * @param array|null $includeChildrenRelations Welche Relations sollen inkludiert werden? (z.B. ['tasks', 'projectSlots.tasks'])
     * @return OrganizationContext
     */
    public function attachOrganizationContext(OrganizationEntity $entity, ?array $includeChildrenRelations = null): OrganizationContext
    {
        $team = auth()->user()?->currentTeamRelation;
        
        return $this->organizationContext()->updateOrCreate(
            [
                'contextable_type' => $this->getMorphClass(),
                'contextable_id' => $this->getKey(),
            ],
            [
                'organization_entity_id' => $entity->id,
                'team_id' => $team?->id,
                'include_children_relations' => $includeChildrenRelations,
                'is_active' => true,
            ]
        );
    }

    /**
     * Entferne Verknüpfung zu Organization Entity
     */
    public function detachOrganizationContext(): bool
    {
        return $this->organizationContext()->delete();
    }

    /**
     * Prüfe ob diese Entity mit einer Organization Entity verknüpft ist
     */
    public function hasOrganizationContext(): bool
    {
        return $this->organizationContext()->where('is_active', true)->exists();
    }

    /**
     * Hole die verknüpfte Organization Entity (falls vorhanden)
     */
    public function getOrganizationEntity(): ?OrganizationEntity
    {
        $context = $this->activeOrganizationContext()->first();
        return $context?->organizationEntity;
    }
}

