<?php

namespace Platform\Organization\Livewire\TimeEntries;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\EntityTimeResolver;
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

        $teamIds = [$rootTeam->id];
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

        if ($this->selectedTeamId) {
            $query->where('team_id', $this->selectedTeamId);
        }

        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }

        if ($this->dateFrom) {
            $query->where('work_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->where('work_date', '<=', $this->dateTo);
        }

        if ($this->showBilledOnly) {
            $query->where('is_billed', true);
        }

        return $query->get();
    }

    /**
     * Baut eine Reverse-Map: "context_type:context_id" → Entity
     * für alle geladenen Entries, basierend auf EntityLinks + Cascade-Registry.
     */
    #[Computed]
    public function contextToEntityMap()
    {
        $entries = $this->timeEntries;
        if ($entries->isEmpty()) {
            return collect();
        }

        $cascades = EntityTimeResolver::getTimeTrackableCascades();

        // Alle EntityLinks laden mit Entity-Relation
        $links = OrganizationEntityLink::query()
            ->whereIn('linkable_type', array_keys($cascades))
            ->with('entity.type')
            ->get();

        $map = [];

        foreach ($links as $link) {
            $morphAlias = $link->linkable_type;

            if (!isset($cascades[$morphAlias])) {
                continue;
            }

            $entity = $link->entity;
            if (!$entity) {
                continue;
            }

            [$fqcn, $childRelations] = $cascades[$morphAlias];

            // Direkte Zuordnung: FQCN:ID → Entity
            $map[$fqcn . ':' . $link->linkable_id] = $entity;

            // Child-Relations traversieren
            if (!empty($childRelations) && class_exists($fqcn)) {
                $model = $fqcn::find($link->linkable_id);
                if ($model) {
                    foreach ($childRelations as $relationPath) {
                        $this->resolveRelationPathForMapping($model, $relationPath, $entity, $map);
                    }
                }
            }
        }

        return collect($map);
    }

    protected function resolveRelationPathForMapping($model, string $path, $entity, array &$map): void
    {
        $segments = explode('.', $path);
        $currentModels = collect([$model]);

        foreach ($segments as $segment) {
            $nextModels = collect();
            foreach ($currentModels as $currentModel) {
                if (!method_exists($currentModel, $segment)) {
                    continue;
                }
                $related = $currentModel->{$segment};
                if ($related instanceof \Illuminate\Database\Eloquent\Collection) {
                    $nextModels = $nextModels->merge($related);
                } elseif ($related instanceof \Illuminate\Database\Eloquent\Model) {
                    $nextModels->push($related);
                }
            }
            $currentModels = $nextModels;
        }

        foreach ($currentModels as $leafModel) {
            $leafKey = get_class($leafModel) . ':' . $leafModel->id;
            $map[$leafKey] = $entity;
        }
    }

    #[Computed]
    public function timeEntriesGroupedByTeamAndRoot()
    {
        $entityMap = $this->contextToEntityMap;

        $groupedByTeam = $this->timeEntries->groupBy('team_id');

        return $groupedByTeam->map(function($teamEntries, $teamId) use ($entityMap) {
            $team = $teamEntries->first()->team ?? null;

            // Innerhalb des Teams nach Entity gruppieren
            $groupedByEntity = $teamEntries->groupBy(function($entry) use ($entityMap) {
                $key = ($entry->context_type ?? '') . ':' . ($entry->context_id ?? 0);
                $entity = $entityMap[$key] ?? null;
                return $entity ? 'entity:' . $entity->id : 'none:' . $key;
            });

            $rootGroups = $groupedByEntity->map(function($entries, $groupKey) use ($entityMap) {
                $rootName = 'Nicht verknüpft';
                $rootType = null;
                $rootId = null;
                $rootModel = null;

                if (str_starts_with($groupKey, 'entity:')) {
                    $entityId = (int) substr($groupKey, 7);
                    // Finde Entity aus einem der Entries
                    foreach ($entries as $entry) {
                        $key = ($entry->context_type ?? '') . ':' . ($entry->context_id ?? 0);
                        $entity = $entityMap[$key] ?? null;
                        if ($entity && $entity->id === $entityId) {
                            $rootName = $entity->name;
                            $rootType = 'OrganizationEntity';
                            $rootId = $entity->id;
                            $rootModel = $entity;
                            break;
                        }
                    }
                } else {
                    // Kein Entity → nach direktem Kontext benennen
                    $firstEntry = $entries->first();
                    if ($firstEntry && $firstEntry->context_type && $firstEntry->context_id && class_exists($firstEntry->context_type)) {
                        $ctxModel = $firstEntry->context_type::find($firstEntry->context_id);
                        if ($ctxModel) {
                            if ($ctxModel instanceof \Platform\Core\Contracts\HasDisplayName) {
                                $rootName = $ctxModel->getDisplayName() ?? 'Unbekannt';
                            } else {
                                $rootName = $ctxModel->name ?? $ctxModel->title ?? 'Unbekannt';
                            }
                            $rootType = $firstEntry->context_type;
                            $rootId = $firstEntry->context_id;
                            $rootModel = $ctxModel;
                        }
                    }
                }

                $sourceModules = $entries->map(fn($e) => $e->source_module)->filter()->unique()->values();
                $sourceModuleTitle = null;
                if ($sourceModules->count() === 1) {
                    $moduleKey = $sourceModules->first();
                    $module = \Platform\Core\PlatformCore::getModule($moduleKey);
                    $sourceModuleTitle = ($module && isset($module['title'])) ? $module['title'] : ucfirst($moduleKey);
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
        $entityMap = $this->contextToEntityMap;

        $groupedByDate = $this->timeEntries->groupBy(function($entry) {
            return $entry->work_date->format('Y-m-d');
        });

        return $groupedByDate->map(function($dateEntries, $dateKey) use ($entityMap) {
            $workDate = $dateEntries->first()->work_date;

            $groupedByTeam = $dateEntries->groupBy('team_id');

            $teamGroups = $groupedByTeam->map(function($teamEntries, $teamId) use ($entityMap) {
                $team = $teamEntries->first()->team ?? null;

                $groupedByEntity = $teamEntries->groupBy(function($entry) use ($entityMap) {
                    $key = ($entry->context_type ?? '') . ':' . ($entry->context_id ?? 0);
                    $entity = $entityMap[$key] ?? null;
                    return $entity ? 'entity:' . $entity->id : 'none:' . $key;
                });

                $rootGroups = $groupedByEntity->map(function($entries, $groupKey) use ($entityMap) {
                    $rootName = 'Nicht verknüpft';
                    $rootType = null;
                    $rootId = null;
                    $rootModel = null;

                    if (str_starts_with($groupKey, 'entity:')) {
                        $entityId = (int) substr($groupKey, 7);
                        foreach ($entries as $entry) {
                            $key = ($entry->context_type ?? '') . ':' . ($entry->context_id ?? 0);
                            $entity = $entityMap[$key] ?? null;
                            if ($entity && $entity->id === $entityId) {
                                $rootName = $entity->name;
                                $rootType = 'OrganizationEntity';
                                $rootId = $entity->id;
                                $rootModel = $entity;
                                break;
                            }
                        }
                    } else {
                        $firstEntry = $entries->first();
                        if ($firstEntry && $firstEntry->context_type && $firstEntry->context_id && class_exists($firstEntry->context_type)) {
                            $ctxModel = $firstEntry->context_type::find($firstEntry->context_id);
                            if ($ctxModel) {
                                if ($ctxModel instanceof \Platform\Core\Contracts\HasDisplayName) {
                                    $rootName = $ctxModel->getDisplayName() ?? 'Unbekannt';
                                } else {
                                    $rootName = $ctxModel->name ?? $ctxModel->title ?? 'Unbekannt';
                                }
                                $rootType = $firstEntry->context_type;
                                $rootId = $firstEntry->context_id;
                                $rootModel = $ctxModel;
                            }
                        }
                    }

                    $sourceModules = $entries->map(fn($e) => $e->source_module)->filter()->unique()->values();
                    $sourceModuleTitle = null;
                    if ($sourceModules->count() === 1) {
                        $moduleKey = $sourceModules->first();
                        $module = \Platform\Core\PlatformCore::getModule($moduleKey);
                        $sourceModuleTitle = ($module && isset($module['title'])) ? $module['title'] : ucfirst($moduleKey);
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

    public function updatedSearch() {}
    public function updatedSelectedTeamId() {}
    public function updatedSelectedUserId() {}
    public function updatedDateFrom() {}
    public function updatedDateTo() {}
    public function updatedShowBilledOnly() {}

    public function render()
    {
        return view('organization::livewire.time-entries.index')
            ->layout('platform::layouts.app');
    }
}
