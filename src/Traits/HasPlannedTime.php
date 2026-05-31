<?php

namespace Platform\Organization\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Platform\Organization\Models\OrganizationTimePlanned;

trait HasPlannedTime
{
    public function plannedTimeEntries(): MorphMany
    {
        return $this->morphMany(OrganizationTimePlanned::class, 'context');
    }

    public function totalPlannedMinutes(): int
    {
        if ($this->relationLoaded('plannedTimeEntries')) {
            return (int) $this->plannedTimeEntries
                ->where('is_active', true)
                ->sum('planned_minutes');
        }

        return (int) $this->plannedTimeEntries()->active()->sum('planned_minutes');
    }

    public function totalPlannedHours(): float
    {
        return round($this->totalPlannedMinutes() / 60, 2);
    }
}
