<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationCostCenterLink;
use Platform\Organization\Contracts\CostCenterLinkableInterface;

class CostCenterLinkService
{
    public function findCostCentersByName(string $query, int $teamId, int $limit = 20): Collection
    {
        return OrganizationEntity::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->whereHas('entityType', function ($q) {
                $q->where('key', 'cost_center');
            })
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function linkCostCenter(CostCenterLinkableInterface $linkable, OrganizationEntity $costCenter, array $meta = []): void
    {
        OrganizationCostCenterLink::create([
            'entity_id' => $costCenter->id,
            'linkable_type' => $linkable->getCostCenterLinkableType(),
            'linkable_id' => $linkable->getCostCenterLinkableId(),
            'start_date' => $meta['start_date'] ?? null,
            'end_date' => $meta['end_date'] ?? null,
            'percentage' => $meta['percentage'] ?? null,
            'is_primary' => $meta['is_primary'] ?? false,
            'team_id' => $linkable->getTeamId(),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    public function unlinkCostCenter(CostCenterLinkableInterface $linkable, OrganizationEntity $costCenter): void
    {
        OrganizationCostCenterLink::where([
            'entity_id' => $costCenter->id,
            'linkable_type' => $linkable->getCostCenterLinkableType(),
            'linkable_id' => $linkable->getCostCenterLinkableId(),
        ])->delete();
    }

    public function getLinkedCostCenters(CostCenterLinkableInterface $linkable): Collection
    {
        return OrganizationCostCenterLink::where([
            'linkable_type' => $linkable->getCostCenterLinkableType(),
            'linkable_id' => $linkable->getCostCenterLinkableId(),
        ])->with('entity')->get()->pluck('entity');
    }
}


