<?php

namespace Platform\Organization\Livewire\PlannedTimes;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Core\Models\Team;
use Illuminate\Support\Facades\Auth;

class Index extends Component
{
    public $search = '';
    public $selectedTeamId = null;
    public $selectedUserId = null;
    public $showInactiveOnly = false;

    #[Computed]
    public function rootTeam()
    {
        $user = Auth::user();
        $baseTeam = $user->currentTeamRelation;
        
        if (!$baseTeam) {
            return null;
        }

        return $baseTeam->getRootTeam();
    }

    #[Computed]
    public function relevantTeamIds()
    {
        $rootTeam = $this->rootTeam;
        
        if (!$rootTeam) {
            return [];
        }

        // Root-Team + alle Kind-Teams
        $teamIds = [$rootTeam->id];
        
        // Rekursiv alle Kind-Teams sammeln
        $this->collectChildTeamIds($rootTeam, $teamIds);
        
        return $teamIds;
    }

    protected function collectChildTeamIds(Team $team, array &$teamIds)
    {
        $childTeams = $team->childTeams()->get();
        
        foreach ($childTeams as $childTeam) {
            $teamIds[] = $childTeam->id;
            $this->collectChildTeamIds($childTeam, $teamIds);
        }
    }

    #[Computed]
    public function plannedTimes()
    {
        $teamIds = $this->relevantTeamIds;
        
        if (empty($teamIds)) {
            return collect();
        }

        $query = OrganizationTimePlanned::query()
            ->whereIn('team_id', $teamIds)
            ->with(['user', 'team', 'context'])
            ->orderBy('created_at', 'desc');

        // Suchfilter
        if ($this->search) {
            $query->where(function($q) {
                $q->where('note', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', function($q) {
                      $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('team', function($q) {
                      $q->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Team-Filter
        if ($this->selectedTeamId) {
            $query->where('team_id', $this->selectedTeamId);
        }

        // User-Filter
        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }

        // Aktiv/Inaktiv-Filter
        if ($this->showInactiveOnly) {
            $query->where('is_active', false);
        } else {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    #[Computed]
    public function availableTeams()
    {
        $teamIds = $this->relevantTeamIds;
        
        if (empty($teamIds)) {
            return collect();
        }

        return Team::whereIn('id', $teamIds)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableUsers()
    {
        $teamIds = $this->relevantTeamIds;
        
        if (empty($teamIds)) {
            return collect();
        }

        return \Platform\Core\Models\User::whereHas('teams', function($q) use ($teamIds) {
            $q->whereIn('teams.id', $teamIds);
        })
        ->orderBy('name')
        ->get();
    }

    #[Computed]
    public function totalPlannedMinutes()
    {
        return $this->plannedTimes->sum('planned_minutes');
    }

    public function updatedSearch()
    {
        // Trigger recomputation
    }

    public function updatedSelectedTeamId()
    {
        // Trigger recomputation
    }

    public function updatedSelectedUserId()
    {
        // Trigger recomputation
    }

    public function updatedShowInactiveOnly()
    {
        // Trigger recomputation
    }

    public function render()
    {
        return view('organization::livewire.planned-times.index')
            ->layout('platform::layouts.app');
    }
}

