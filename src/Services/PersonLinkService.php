<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationPerson;
use Platform\Organization\Models\OrganizationPersonLink;
use Platform\Organization\Contracts\PersonLinkableInterface;

class PersonLinkService
{
    public function findPersonsByName(string $query, int $teamId, int $limit = 20): Collection
    {
        return OrganizationPerson::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function linkPerson(PersonLinkableInterface $linkable, OrganizationPerson $person, array $meta = []): void
    {
        OrganizationPersonLink::create([
            'person_id' => $person->id,
            'linkable_type' => $linkable->getPersonLinkableType(),
            'linkable_id' => $linkable->getPersonLinkableId(),
            'start_date' => $meta['start_date'] ?? null,
            'end_date' => $meta['end_date'] ?? null,
            'percentage' => $meta['percentage'] ?? null,
            'is_primary' => $meta['is_primary'] ?? false,
            'team_id' => $linkable->getTeamId(),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    public function unlinkPerson(PersonLinkableInterface $linkable, OrganizationPerson $person): void
    {
        OrganizationPersonLink::where([
            'person_id' => $person->id,
            'linkable_type' => $linkable->getPersonLinkableType(),
            'linkable_id' => $linkable->getPersonLinkableId(),
        ])->delete();
    }

    public function getLinkedPersons(PersonLinkableInterface $linkable): Collection
    {
        return OrganizationPersonLink::where([
            'linkable_type' => $linkable->getPersonLinkableType(),
            'linkable_id' => $linkable->getPersonLinkableId(),
        ])->with('person')->get()->pluck('person');
    }
}
