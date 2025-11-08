<?php

namespace Platform\Organization\Livewire\TimeEntries;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Core\Models\Team;
use Illuminate\Support\Facades\Auth;

class Index extends Component
{
    public $search = '';
    public $selectedTeamId = null;
    public $selectedUserId = null;
    public $dateFrom = null;
    public $dateTo = null;
    public $showBilledOnly = false;

    public function mount()
    {
        // Setze Standard-Datum auf aktuellen Monat
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

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
    public function timeEntries()
    {
        $teamIds = $this->relevantTeamIds;
        
        if (empty($teamIds)) {
            return collect();
        }

        $query = OrganizationTimeEntry::query()
            ->whereIn('team_id', $teamIds)
            ->with(['user', 'team', 'context'])
            ->orderBy('work_date', 'desc')
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

        // Datum-Filter
        if ($this->dateFrom) {
            $query->where('work_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->where('work_date', '<=', $this->dateTo);
        }

        // Abgerechnet-Filter
        if ($this->showBilledOnly) {
            $query->where('is_billed', true);
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
    public function totalMinutes()
    {
        return $this->timeEntries->sum('minutes');
    }

    #[Computed]
    public function totalBilledMinutes()
    {
        return $this->timeEntries->where('is_billed', true)->sum('minutes');
    }

    #[Computed]
    public function totalAmountCents()
    {
        return $this->timeEntries->sum('amount_cents');
    }

    #[Computed]
    public function totalBilledAmountCents()
    {
        return $this->timeEntries->where('is_billed', true)->sum('amount_cents');
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

    public function updatedDateFrom()
    {
        // Trigger recomputation
    }

    public function updatedDateTo()
    {
        // Trigger recomputation
    }

    public function updatedShowBilledOnly()
    {
        // Trigger recomputation
    }

    public function render()
    {
        return view('organization::livewire.time-entries.index')
            ->layout('platform::layouts.app');
    }
}

