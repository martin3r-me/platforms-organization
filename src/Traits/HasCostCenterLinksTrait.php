<?php

namespace Platform\Organization\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationCostCenterLink;

trait HasCostCenterLinksTrait
{
    public function costCenterLinks(): MorphMany
    {
        return $this->morphMany(OrganizationCostCenterLink::class, 'linkable');
    }

    public function activeCostCenters(?\Carbon\Carbon $at = null): Collection
    {
        $at = $at ?: now();
        return $this->costCenterLinks()
            ->with('entity')
            ->where(function ($q) use ($at) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $at->toDateString());
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $at->toDateString());
            })
            ->get()
            ->pluck('entity');
    }

    public function attachCostCenter($organizationEntity, ?string $startDate = null, ?string $endDate = null, ?float $percentage = null, bool $isPrimary = false): void
    {
        $this->costCenterLinks()->create([
            'entity_id' => is_object($organizationEntity) ? $organizationEntity->id : $organizationEntity,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'percentage' => $percentage,
            'is_primary' => $isPrimary,
        ]);
    }

    public function detachCostCenter($organizationEntity): void
    {
        $entityId = is_object($organizationEntity) ? $organizationEntity->id : $organizationEntity;
        $this->costCenterLinks()->where('entity_id', $entityId)->delete();
    }

    public function syncCostCenters(array $entityIdToMeta): void
    {
        $this->costCenterLinks()->delete();
        foreach ($entityIdToMeta as $entityId => $meta) {
            $this->attachCostCenter($entityId, $meta['start_date'] ?? null, $meta['end_date'] ?? null, $meta['percentage'] ?? null, $meta['is_primary'] ?? false);
        }
    }
}


