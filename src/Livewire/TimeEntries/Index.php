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
            ->with(['user', 'team', 'context', 'rootContext'])
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
    public function timeEntriesGroupedByTeamAndRoot()
    {
        // Zuerst nach Team gruppieren
        $groupedByTeam = $this->timeEntries->groupBy('team_id');
        
        return $groupedByTeam->map(function($teamEntries, $teamId) {
            $team = $teamEntries->first()->team ?? null;
            
            // Innerhalb des Teams nach Root gruppieren
            $groupedByRoot = $teamEntries->groupBy(function($entry) {
                // Gruppiere nach root_context_type und root_context_id
                if ($entry->root_context_type && $entry->root_context_id) {
                    return $entry->root_context_type . ':' . $entry->root_context_id;
                }
                // Fallback: Wenn kein Root vorhanden, nach primärem Kontext gruppieren
                return ($entry->context_type ?? 'unknown') . ':' . ($entry->context_id ?? 0);
            });
            
            $rootGroups = $groupedByRoot->map(function($entries, $key) {
                $parts = explode(':', $key);
                $rootType = $parts[0] ?? null;
                $rootId = $parts[1] ?? null;
                
                $rootModel = null;
                $rootName = 'Unbekannt';
                
                if ($rootType && $rootId && class_exists($rootType)) {
                    $rootModel = $rootType::find($rootId);
                    if ($rootModel) {
                        if ($rootModel instanceof \Platform\Core\Contracts\HasDisplayName) {
                            $rootName = $rootModel->getDisplayName() ?? 'Unbekannt';
                        } else {
                            $rootName = $rootModel->name ?? $rootModel->title ?? 'Unbekannt';
                        }
                    }
                }
                
                // Prüfe ob alle Einträge aus demselben Modul kommen
                $sourceModules = $entries->map(function($entry) {
                    return $entry->source_module;
                })->filter()->unique()->values();
                
                $sourceModuleTitle = null;
                if ($sourceModules->count() === 1) {
                    $moduleKey = $sourceModules->first();
                    $module = \Platform\Core\PlatformCore::getModule($moduleKey);
                    if ($module && isset($module['title'])) {
                        $sourceModuleTitle = $module['title'];
                    } else {
                        $sourceModuleTitle = ucfirst($moduleKey);
                    }
                }
                
                return [
                    'root_type' => $rootType,
                    'root_id' => $rootId,
                    'root_name' => $rootName,
                    'root_model' => $rootModel,
                    'entries' => $entries,
                    'source_module_title' => $sourceModuleTitle,
                    'total_minutes' => $entries->sum('minutes'),
                    'total_amount_cents' => $entries->sum('amount_cents'),
                ];
            })->sortBy('root_name')->values();
            
            return [
                'team' => $team,
                'team_name' => $team->name ?? 'Unbekanntes Team',
                'root_groups' => $rootGroups,
                'total_minutes' => $teamEntries->sum('minutes'),
                'total_amount_cents' => $teamEntries->sum('amount_cents'),
            ];
        })->sortBy('team_name');
    }

    #[Computed]
    public function timeEntriesGroupedByDateAndTeam()
    {
        // Zuerst nach Datum gruppieren (neueste zuerst)
        $groupedByDate = $this->timeEntries->groupBy(function($entry) {
            return $entry->work_date->format('Y-m-d');
        });
        
        return $groupedByDate->map(function($dateEntries, $dateKey) {
            $workDate = $dateEntries->first()->work_date;
            
            // Innerhalb des Datums nach Team gruppieren
            $groupedByTeam = $dateEntries->groupBy('team_id');
            
            $teamGroups = $groupedByTeam->map(function($teamEntries, $teamId) {
                $team = $teamEntries->first()->team ?? null;
                
                // Innerhalb des Teams nach Root gruppieren
                $groupedByRoot = $teamEntries->groupBy(function($entry) {
                    // Gruppiere nach root_context_type und root_context_id
                    if ($entry->root_context_type && $entry->root_context_id) {
                        return $entry->root_context_type . ':' . $entry->root_context_id;
                    }
                    // Fallback: Wenn kein Root vorhanden, nach primärem Kontext gruppieren
                    return ($entry->context_type ?? 'unknown') . ':' . ($entry->context_id ?? 0);
                });
                
                $rootGroups = $groupedByRoot->map(function($entries, $key) {
                    $parts = explode(':', $key);
                    $rootType = $parts[0] ?? null;
                    $rootId = $parts[1] ?? null;
                    
                    $rootModel = null;
                    $rootName = 'Unbekannt';
                    
                    if ($rootType && $rootId && class_exists($rootType)) {
                        $rootModel = $rootType::find($rootId);
                        if ($rootModel) {
                            if ($rootModel instanceof \Platform\Core\Contracts\HasDisplayName) {
                                $rootName = $rootModel->getDisplayName() ?? 'Unbekannt';
                            } else {
                                $rootName = $rootModel->name ?? $rootModel->title ?? 'Unbekannt';
                            }
                        }
                    }
                    
                    // Prüfe ob alle Einträge aus demselben Modul kommen
                    $sourceModules = $entries->map(function($entry) {
                        return $entry->source_module;
                    })->filter()->unique()->values();
                    
                    $sourceModuleTitle = null;
                    if ($sourceModules->count() === 1) {
                        $moduleKey = $sourceModules->first();
                        $module = \Platform\Core\PlatformCore::getModule($moduleKey);
                        if ($module && isset($module['title'])) {
                            $sourceModuleTitle = $module['title'];
                        } else {
                            $sourceModuleTitle = ucfirst($moduleKey);
                        }
                    }
                    
                    return [
                        'root_type' => $rootType,
                        'root_id' => $rootId,
                        'root_name' => $rootName,
                        'root_model' => $rootModel,
                        'entries' => $entries,
                        'source_module_title' => $sourceModuleTitle,
                        'total_minutes' => $entries->sum('minutes'),
                        'total_amount_cents' => $entries->sum('amount_cents'),
                    ];
                })->sortBy('root_name')->values();
                
                return [
                    'team' => $team,
                    'team_name' => $team->name ?? 'Unbekanntes Team',
                    'root_groups' => $rootGroups,
                    'total_minutes' => $teamEntries->sum('minutes'),
                    'total_amount_cents' => $teamEntries->sum('amount_cents'),
                ];
            })->sortBy('team_name')->values();
            
            return [
                'date' => $workDate,
                'date_key' => $dateKey,
                'team_groups' => $teamGroups,
                'total_minutes' => $dateEntries->sum('minutes'),
                'total_amount_cents' => $dateEntries->sum('amount_cents'),
            ];
        })->sortByDesc('date_key')->values();
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

    #[Computed]
    public function totalUnbilledMinutes()
    {
        return $this->totalMinutes - $this->totalBilledMinutes;
    }

    #[Computed]
    public function totalUnbilledAmountCents()
    {
        return $this->totalAmountCents - $this->totalBilledAmountCents;
    }

    // Helper: Minuten zu Tagen (1 Tag = 8 Stunden = 480 Minuten)
    protected function minutesToDays($minutes)
    {
        return $minutes / 480;
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

