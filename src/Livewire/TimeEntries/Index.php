<?php

namespace Platform\Organization\Livewire\TimeEntries;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\EntityTimeResolver;
use Platform\Core\Models\Team;
use Illuminate\Support\Facades\Auth;

class Index extends Component
{
    public $search = '';
    public $selectedEntityTypeId = null;
    public $selectedEntityId = null;
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

        if ($this->search) {
            $query->where(function($q) {
                $q->where('note', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', function($q) {
                      $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                  });
            });
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
     * Filtert timeEntries nach Entity/EntityType Selection.
     * Wenn kein Entity-Filter aktiv, werden alle Entries zurückgegeben.
     */
    #[Computed]
    public function filteredTimeEntries()
    {
        $entries = $this->timeEntries;
        $entityMap = $this->contextToEntityMap;

        if (!$this->selectedEntityId && !$this->selectedEntityTypeId) {
            return $entries;
        }

        return $entries->filter(function($entry) use ($entityMap) {
            $key = ($entry->context_type ?? '') . ':' . ($entry->context_id ?? 0);
            $entity = $entityMap[$key] ?? null;

            if ($this->selectedEntityId) {
                return $entity && $entity->id == $this->selectedEntityId;
            }

            if ($this->selectedEntityTypeId) {
                return $entity && $entity->entity_type_id == $this->selectedEntityTypeId;
            }

            return true;
        })->values();
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

    /**
     * Prüft ob ein Heroicon existiert, gibt den Namen zurück oder null als Fallback.
     */
    protected function resolveHeroicon(string $iconName): ?string
    {
        try {
            $factory = app(\BladeUI\Icons\Factory::class);
            $factory->svg('heroicon-o-' . $iconName);
            return $iconName;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Hilfsmethode: Löst eine Entry zu ihrem Entity-Gruppenschlüssel auf.
     */
    protected function resolveEntityGroupKey($entry, $entityMap): string
    {
        $key = ($entry->context_type ?? '') . ':' . ($entry->context_id ?? 0);
        $entity = $entityMap[$key] ?? null;
        return $entity ? 'entity:' . $entity->id : 'none';
    }

    /**
     * Hilfsmethode: Baut Entity-Info für eine Gruppe aus Entries.
     */
    protected function buildEntityGroupInfo(string $groupKey, $entries, $entityMap): array
    {
        $entityName = 'Nicht verknüpft';
        $entityType = null;
        $entityModel = null;
        $entityTypeIcon = null;

        if (str_starts_with($groupKey, 'entity:')) {
            $entityId = (int) substr($groupKey, 7);
            foreach ($entries as $entry) {
                $key = ($entry->context_type ?? '') . ':' . ($entry->context_id ?? 0);
                $entity = $entityMap[$key] ?? null;
                if ($entity && $entity->id === $entityId) {
                    $entityName = $entity->name;
                    $entityModel = $entity;
                    $entityType = $entity->type;
                    $rawIcon = $entityType->icon ?? null;
                    $entityTypeIcon = $rawIcon ? $this->resolveHeroicon($rawIcon) : null;
                    break;
                }
            }
        }

        return [
            'entity_name' => $entityName,
            'entity_model' => $entityModel,
            'entity_type' => $entityType,
            'entity_type_icon' => $entityTypeIcon,
            'entries' => $entries,
            'total_minutes' => $entries->sum('minutes'),
            'total_amount_cents' => $entries->sum('amount_cents'),
        ];
    }

    /**
     * Dashboard Tiles: Gruppiert nach Entity (statt Team).
     */
    #[Computed]
    public function timeEntriesGroupedByEntity()
    {
        $entityMap = $this->contextToEntityMap;
        $entries = $this->filteredTimeEntries;

        $grouped = $entries->groupBy(function($entry) use ($entityMap) {
            return $this->resolveEntityGroupKey($entry, $entityMap);
        });

        return $grouped->map(function($groupEntries, $groupKey) use ($entityMap) {
            return $this->buildEntityGroupInfo($groupKey, $groupEntries, $entityMap);
        })->sortBy('entity_name')->values();
    }

    /**
     * Tabelle: Datum → Entity → Entries (statt Datum → Team → Entity).
     */
    #[Computed]
    public function timeEntriesGroupedByDateAndEntity()
    {
        $entityMap = $this->contextToEntityMap;
        $entries = $this->filteredTimeEntries;

        $groupedByDate = $entries->groupBy(function($entry) {
            return $entry->work_date->format('Y-m-d');
        });

        return $groupedByDate->map(function($dateEntries, $dateKey) use ($entityMap) {
            $workDate = $dateEntries->first()->work_date;

            $groupedByEntity = $dateEntries->groupBy(function($entry) use ($entityMap) {
                return $this->resolveEntityGroupKey($entry, $entityMap);
            });

            $entityGroups = $groupedByEntity->map(function($groupEntries, $groupKey) use ($entityMap) {
                $info = $this->buildEntityGroupInfo($groupKey, $groupEntries, $entityMap);

                // Kontext-Details für jede Entry innerhalb der Entity-Gruppe
                $info['context_details'] = $groupEntries->map(function($entry) {
                    if (!$entry->context) return null;
                    $contextName = $entry->context instanceof \Platform\Core\Contracts\HasDisplayName
                        ? $entry->context->getDisplayName()
                        : ($entry->context->name ?? $entry->context->title ?? null);
                    return [
                        'type' => class_basename($entry->context_type),
                        'name' => $contextName,
                        'id' => $entry->context_id,
                    ];
                })->filter()->unique(fn($item) => ($item['type'] ?? '') . ':' . ($item['id'] ?? 0))->values();

                $sourceModules = $groupEntries->map(fn($e) => $e->source_module)->filter()->unique()->values();
                $info['source_module_title'] = null;
                if ($sourceModules->count() === 1) {
                    $moduleKey = $sourceModules->first();
                    $module = \Platform\Core\PlatformCore::getModule($moduleKey);
                    $info['source_module_title'] = ($module && isset($module['title'])) ? $module['title'] : ucfirst($moduleKey);
                }

                return $info;
            })->sortBy('entity_name')->values();

            return [
                'date' => $workDate,
                'date_key' => $dateKey,
                'entity_groups' => $entityGroups,
                'total_minutes' => $dateEntries->sum('minutes'),
                'total_amount_cents' => $dateEntries->sum('amount_cents'),
            ];
        })->sortByDesc('date_key')->values();
    }

    #[Computed]
    public function availableEntityTypes()
    {
        return OrganizationEntityType::active()->ordered()->get();
    }

    #[Computed]
    public function availableEntities()
    {
        $query = OrganizationEntity::active()
            ->with('type')
            ->orderBy('name');

        if ($this->selectedEntityTypeId) {
            $query->where('entity_type_id', $this->selectedEntityTypeId);
        }

        return $query->get();
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
        return $this->filteredTimeEntries->sum('minutes');
    }

    #[Computed]
    public function totalBilledMinutes()
    {
        return $this->filteredTimeEntries->where('is_billed', true)->sum('minutes');
    }

    #[Computed]
    public function totalAmountCents()
    {
        return $this->filteredTimeEntries->sum('amount_cents');
    }

    #[Computed]
    public function totalBilledAmountCents()
    {
        return $this->filteredTimeEntries->where('is_billed', true)->sum('amount_cents');
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
    public function updatedSelectedEntityTypeId()
    {
        $this->selectedEntityId = null;
    }
    public function updatedSelectedEntityId() {}
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
