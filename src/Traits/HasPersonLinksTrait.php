<?php

namespace Platform\Organization\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationPersonLink;

trait HasPersonLinksTrait
{
    public function personLinks(): MorphMany
    {
        return $this->morphMany(OrganizationPersonLink::class, 'linkable');
    }

    public function activePersons(?\Carbon\Carbon $at = null): Collection
    {
        $at = $at ?: now();
        return $this->personLinks()
            ->with('person')
            ->where(function ($q) use ($at) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $at->toDateString());
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $at->toDateString());
            })
            ->get()
            ->pluck('person');
    }

    public function attachPerson($person, ?string $startDate = null, ?string $endDate = null, ?float $percentage = null, bool $isPrimary = false): void
    {
        $this->personLinks()->create([
            'person_id' => is_object($person) ? $person->id : $person,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'percentage' => $percentage,
            'is_primary' => $isPrimary,
        ]);
    }

    public function detachPerson($person): void
    {
        $personId = is_object($person) ? $person->id : $person;
        $this->personLinks()->where('person_id', $personId)->delete();
    }

    public function syncPersons(array $personIdToMeta): void
    {
        $this->personLinks()->delete();
        foreach ($personIdToMeta as $personId => $meta) {
            $this->attachPerson($personId, $meta['start_date'] ?? null, $meta['end_date'] ?? null, $meta['percentage'] ?? null, $meta['is_primary'] ?? false);
        }
    }
}
