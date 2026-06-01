<?php

namespace Platform\Organization\Traits;

use Carbon\Carbon;
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

    public function plannedMinutesForDate(string|Carbon $date): int
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        if ($this->relationLoaded('plannedTimeEntries')) {
            return (int) $this->plannedTimeEntries
                ->where('is_active', true)
                ->filter(fn ($e) =>
                    ($e->valid_from === null || $e->valid_from->lte($date)) &&
                    ($e->valid_to === null || $e->valid_to->gte($date))
                )
                ->sum('planned_minutes');
        }

        return (int) $this->plannedTimeEntries()->active()->forDate($date)->sum('planned_minutes');
    }
}
