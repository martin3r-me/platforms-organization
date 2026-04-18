<?php

namespace Platform\Organization\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;

class Sidebar extends Component
{
    public function getRecentEntitiesProperty()
    {
        return OrganizationEntity::active()
            ->forTeam(auth()->user()->currentTeam->id)
            ->with('type')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    public function getProcessCountsProperty(): array
    {
        $teamId = Auth::user()->currentTeam->id;

        $counts = DB::table('organization_processes')
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return $counts;
    }

    public function render()
    {
        return view('organization::livewire.sidebar')
            ->layout('platform::layouts.app');
    }
}