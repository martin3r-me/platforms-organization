<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Platform\Organization\Contracts\CompanyLinkableInterface;
use Platform\Organization\Models\OrganizationCompanyLink;
use Platform\Organization\Models\OrganizationEntity;

class CompanyLinkService
{
    public function linkCompany(CompanyLinkableInterface $linkable, OrganizationEntity $entity): void
    {
        OrganizationCompanyLink::firstOrCreate([
            'organization_entity_id' => $entity->id,
            'linkable_type' => $linkable->getCompanyLinkableType(),
            'linkable_id' => $linkable->getCompanyLinkableId(),
        ], [
            'team_id' => $linkable->getTeamId(),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    public function unlinkCompany(CompanyLinkableInterface $linkable, OrganizationEntity $entity): void
    {
        OrganizationCompanyLink::where('organization_entity_id', $entity->id)
            ->where('linkable_type', $linkable->getCompanyLinkableType())
            ->where('linkable_id', $linkable->getCompanyLinkableId())
            ->delete();
    }

    public function getLinkedCompanies(CompanyLinkableInterface $linkable): Collection
    {
        return OrganizationCompanyLink::where('linkable_type', $linkable->getCompanyLinkableType())
            ->where('linkable_id', $linkable->getCompanyLinkableId())
            ->with('company')
            ->get()
            ->pluck('company');
    }
}



