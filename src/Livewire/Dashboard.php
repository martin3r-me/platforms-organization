<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;

class Dashboard extends Component
{
    public function getTeamId(): ?int
    {
        $user = auth()->user();
        return $user && $user->currentTeam ? $user->currentTeam->id : null;
    }

    public function getTotalEntitiesProperty(): int
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return 0;
        }
        return (int) OrganizationEntity::forTeam($teamId)->count();
    }

    public function getActiveEntitiesProperty(): int
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return 0;
        }
        return (int) OrganizationEntity::forTeam($teamId)->active()->count();
    }

    public function getRootEntitiesProperty(): int
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return 0;
        }
        return (int) OrganizationEntity::forTeam($teamId)->whereNull('parent_entity_id')->count();
    }

    public function getLeafEntitiesProperty(): int
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return 0;
        }
        // Leaf = keine Children
        return (int) OrganizationEntity::forTeam($teamId)
            ->whereDoesntHave('children')
            ->count();
    }

    public function getRecentEntitiesProperty()
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return collect();
        }
        return OrganizationEntity::forTeam($teamId)
            ->with(['type', 'vsmSystem'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    public function getEntitiesByTypeProperty()
    {
        $teamId = $this->getTeamId();
        if (!$teamId) {
            return collect();
        }

        $types = OrganizationEntityType::getActiveOrdered();
        $counts = OrganizationEntity::forTeam($teamId)
            ->selectRaw('entity_type_id, COUNT(*) as aggregate_count')
            ->groupBy('entity_type_id')
            ->pluck('aggregate_count', 'entity_type_id');

        return $types->map(function ($type) use ($counts) {
            return (object) [
                'id' => $type->id,
                'name' => $type->name,
                'icon' => $type->icon,
                'count' => (int) ($counts[$type->id] ?? 0),
            ];
        });
    }

    public function render()
    {
        return view('organization::livewire.dashboard')
            ->layout('platform::layouts.app');
    }
}