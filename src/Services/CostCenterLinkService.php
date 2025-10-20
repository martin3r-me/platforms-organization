<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Collection;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationCostCenterLink;
use Platform\Organization\Contracts\CostCenterLinkableInterface;

class CostCenterLinkService
{
    public function findCostCentersByName(string $query, int $teamId, int $limit = 20): Collection
    {
        return OrganizationCostCenter::query()
            ->where('team_id', $teamId)
            ->where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function linkCostCenter(CostCenterLinkableInterface $linkable, OrganizationCostCenter $costCenter, array $meta = []): void
    {
        OrganizationCostCenterLink::create([
            'cost_center_id' => $costCenter->id,
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

    public function unlinkCostCenter(CostCenterLinkableInterface $linkable, OrganizationCostCenter $costCenter): void
    {
        OrganizationCostCenterLink::where([
            'cost_center_id' => $costCenter->id,
            'linkable_type' => $linkable->getCostCenterLinkableType(),
            'linkable_id' => $linkable->getCostCenterLinkableId(),
        ])->delete();
    }

    public function getLinkedCostCenters(CostCenterLinkableInterface $linkable): Collection
    {
        return OrganizationCostCenterLink::where([
            'linkable_type' => $linkable->getCostCenterLinkableType(),
            'linkable_id' => $linkable->getCostCenterLinkableId(),
        ])->with('costCenter')->get()->pluck('costCenter');
    }
}


