<?php

namespace Platform\Organization\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Platform\Organization\Models\OrganizationTimePeriod;

trait HasPlannedPeriod
{
    public function plannedPeriodEntries(): MorphMany
    {
        return $this->morphMany(OrganizationTimePeriod::class, 'context');
    }

    public function activePlannedPeriod(): ?OrganizationTimePeriod
    {
        if ($this->relationLoaded('plannedPeriodEntries')) {
            return $this->plannedPeriodEntries->where('is_active', true)->first();
        }

        return $this->plannedPeriodEntries()->active()->first();
    }

    public function plannedStart(): ?\Carbon\Carbon
    {
        return $this->activePlannedPeriod()?->planned_start;
    }

    public function plannedEnd(): ?\Carbon\Carbon
    {
        return $this->activePlannedPeriod()?->planned_end;
    }
}
