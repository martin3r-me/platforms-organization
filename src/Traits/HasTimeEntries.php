<?php

namespace Platform\Organization\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Platform\Organization\Models\OrganizationTimeEntry;

trait HasTimeEntries
{
    public function timeEntries(): MorphMany
    {
        return $this->morphMany(OrganizationTimeEntry::class, 'context');
    }

    public function totalLoggedMinutes(): int
    {
        return (int) $this->timeEntries()->sum('minutes');
    }

    public function billedMinutes(): int
    {
        return (int) $this->timeEntries()->where('is_billed', true)->sum('minutes');
    }

    public function unbilledMinutes(): int
    {
        return max(0, $this->totalLoggedMinutes() - $this->billedMinutes());
    }
}

