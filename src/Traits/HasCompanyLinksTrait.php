<?php

namespace Platform\Organization\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Platform\Organization\Models\OrganizationCompanyLink;
use Platform\Organization\Models\OrganizationEntity;

trait HasCompanyLinksTrait
{
    public function organizationCompanyLinks(): MorphMany
    {
        return $this->morphMany(OrganizationCompanyLink::class, 'linkable');
    }

    public function organizations()
    {
        return $this->organizationCompanyLinks()->with('company');
    }

    public function attachOrganization(OrganizationEntity $entity): void
    {
        $this->organizationCompanyLinks()->firstOrCreate([
            'organization_entity_id' => $entity->id,
            'linkable_type' => $this->getMorphClass(),
            'linkable_id' => $this->getKey(),
        ], [
            'team_id' => method_exists($this, 'getTeamId') ? $this->getTeamId() : (auth()->user()->currentTeam->id ?? null),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    public function detachOrganization(OrganizationEntity $entity): void
    {
        $this->organizationCompanyLinks()
            ->where('organization_entity_id', $entity->id)
            ->delete();
    }
}



