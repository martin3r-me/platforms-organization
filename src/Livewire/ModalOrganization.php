<?php

namespace Platform\Organization\Livewire;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Models\OrganizationTimeEntryContext;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Services\StoreTimeEntry;
use Platform\Organization\Services\StorePlannedTime;
use Platform\Organization\Services\TimeContextResolver;
use Platform\Organization\Traits\HasTimeEntries;

class ModalOrganization extends Component
{

    public bool $open = false;

    public ?string $contextType = null;
    public ?int $contextId = null;

    public array $linkedContexts = [];
    
    // Verfügbare Relations für Children-Cascade (aus Dispatch)
    public array $availableChildRelations = [];

    // Flags für erlaubte Aktionen (granular)
    public bool $allowTimeEntry = true;
    public bool $allowEntities = true; // Entitäten-Verknüpfung
    public bool $allowDimensions = true; // Cost Centers und VSM-Systeme

    public string $workDate;
    public int $minutes = 60;
    public ?string $rate = null;
    public ?string $note = null;

    public string $activeTab = 'entry'; // 'entry', 'overview', 'planned', 'team', 'organization', 'cost-centers', or 'vsm-systems'
    public string $timeRange = 'current_month'; // 'current_week', 'current_month', 'current_year', 'last_week', 'last_month'

    // Filter für Übersicht-Tab
    public string $overviewTimeRange = 'all'; // 'all', 'current_week', 'current_month', 'current_year', 'last_week', 'last_month'
    public ?int $selectedUserId = null; // null = alle Personen

    public $entries = [];
    public $plannedEntries = [];
    public $teamEntries = [];
    
    // Gecachte Statistiken für Performance
    public ?int $cachedTotalMinutes = null;
    public ?int $cachedBilledMinutes = null;
    public ?int $cachedUnbilledAmountCents = null;
    public $cachedAvailableUsers = null;
    
    // Organization Context Management
    public $organizationContext = null;
    public $availableOrganizationEntities;
    public $selectedOrganizationEntityId = null;
    public array $selectedChildRelations = [];

    // Cost Center Management
    public $availableCostCenters = [];
    public $linkedCostCenters = [];
    public ?int $selectedCostCenterId = null;
    public string $costCenterSearch = '';

    // VSM System Management
    public $availableVsmSystems = [];
    public $linkedVsmSystems = [];
    public ?int $selectedVsmSystemId = null;
    public string $vsmSystemSearch = '';

    public ?int $plannedMinutes = null;
    public ?string $plannedNote = null;

    // Minute-Optionen: 5, 10, 15, 20, 25, 30, 45 Minuten, dann 1h bis 8h in 0.5h-Schritten
    // Statisch generiert, da Computed Properties in rules() nicht verfügbar sind
    protected function getMinuteOptions(): array
    {
        static $options = null;
        if ($options === null) {
            $options = [];
            // Kurze Zeiten: 5, 10, 15, 20, 25, 30, 45 Minuten
            $options[] = 5;
            $options[] = 10;
            $options[] = 15;
            $options[] = 20;
            $options[] = 25;
            $options[] = 30;
            $options[] = 45;
            // Dann 1h bis 8h in 0.5h-Schritten (30 Minuten)
            for ($minutes = 60; $minutes <= 480; $minutes += 30) {
                $options[] = $minutes;
            }
        }
        return $options;
    }
    
    // Computed Property für die View
    public function getMinuteOptionsProperty(): array
    {
        return $this->getMinuteOptions();
    }

    public function getContextLabelProperty(): ?string
    {
        if (! $this->contextType || ! $this->contextId) {
            return null;
        }

        $resolver = app(TimeContextResolver::class);
        return $resolver->resolveLabel($this->contextType, $this->contextId);
    }

    public function getContextBreadcrumbProperty(): array
    {
        if (! $this->contextType || ! $this->contextId) {
            return [];
        }

        $breadcrumb = [];
        
        // Primärkontext
        $resolver = app(TimeContextResolver::class);
        $label = $resolver->resolveLabel($this->contextType, $this->contextId);
        if ($label) {
            $breadcrumb[] = [
                'type' => class_basename($this->contextType),
                'label' => $label,
            ];
        }

        // Vorfahren-Kontexte
        if (class_exists($this->contextType)) {
            $model = $this->contextType::find($this->contextId);
            if ($model && $model instanceof \Platform\Core\Contracts\HasTimeAncestors) {
                $ancestors = $model->timeAncestors();
                foreach ($ancestors as $ancestor) {
                    $ancestorLabel = $ancestor['label'] ?? $resolver->resolveLabel($ancestor['type'], $ancestor['id']);
                    if ($ancestorLabel) {
                        $breadcrumb[] = [
                            'type' => class_basename($ancestor['type']),
                            'label' => $ancestorLabel,
                        ];
                    }
                }
            }
        }

        return $breadcrumb;
    }

    public function mount(): void
    {
        // Initialisiere Collections für Organization Context Management
        $this->organizationContext = null;
        $this->availableOrganizationEntities = collect();
        $this->availableCostCenters = collect();
        $this->linkedCostCenters = collect();
        $this->availableVsmSystems = collect();
        $this->linkedVsmSystems = collect();
        
        \Log::info('ModalOrganization: mount() called', [
            'component_id' => $this->getId(),
        ]);
    }

    #[On('organization')]
    public function setContext(array $payload = []): void
    {
        \Log::info('ModalOrganization: organization event received!', [
            'component_id' => $this->getId(),
            'payload' => $payload,
            'timestamp' => now(),
        ]);
        
        $this->contextType = $payload['context_type'] ?? null;
        $this->contextId = isset($payload['context_id']) ? (int) $payload['context_id'] : null;
        $this->linkedContexts = $payload['linked_contexts'] ?? [];
        
        // Verfügbare Relations für Children-Cascade (z.B. ['tasks', 'projectSlots.tasks'])
        $this->availableChildRelations = $payload['include_children_relations'] ?? [];

        // Flags setzen (defaults: alle true für Rückwärtskompatibilität)
        $this->allowTimeEntry = $payload['allow_time_entry'] ?? true;
        
        // Neue granular Flags (mit Fallback auf alte Flags für Rückwärtskompatibilität)
        $this->allowEntities = $payload['allow_entities'] 
            ?? $payload['allow_context_management'] 
            ?? $payload['can_link_to_entity'] 
            ?? true;
        
        $this->allowDimensions = $payload['allow_dimensions'] 
            ?? $payload['allow_context_management'] 
            ?? true;

        // Wenn Modal bereits offen ist, Daten neu laden
        if ($this->open && $this->contextType && $this->contextId) {
            if ($this->allowTimeEntry && class_exists($this->contextType) && $this->contextSupportsTimeEntries($this->contextType)) {
                $this->loadEntries();
                $this->loadPlannedEntries();
                $this->loadCurrentPlanned();
            }
            
            // Organization Contexts laden wenn erlaubt
            if ($this->allowEntities) {
                $this->loadOrganizationContexts();
                $this->loadAvailableOrganizationEntities();
            }
            if ($this->allowDimensions) {
                $this->loadCostCenters();
                $this->loadLinkedCostCenters();
                $this->loadVsmSystems();
                $this->loadLinkedVsmSystems();
            }
        }
    }

    #[On('organization:open')]
    public function open(): void
    {
        if (! Auth::check() || ! Auth::user()->currentTeamRelation) {
            return;
        }

        // Initialisiere Formular
        $this->workDate = now()->toDateString();
        $this->minutes = 60;
        $this->rate = null;
        $this->note = null;
        
        // Filter zurücksetzen
        $this->overviewTimeRange = 'all';
        $this->selectedUserId = null;
        
        // Tab basierend auf erlaubten Aktionen setzen
        if ($this->allowTimeEntry) {
            $this->activeTab = 'entry';
        } elseif ($this->allowEntities) {
            $this->activeTab = 'organization';
        } elseif ($this->allowDimensions) {
            $this->activeTab = 'cost-centers';
        } else {
            $this->activeTab = 'overview';
        }

        // Cache zurücksetzen beim Öffnen
        $this->cachedTotalMinutes = null;
        $this->cachedBilledMinutes = null;
        $this->cachedUnbilledAmountCents = null;
        $this->cachedAvailableUsers = null;

        // Wenn Kontext vorhanden und TimeEntry erlaubt, Daten laden
        if ($this->allowTimeEntry && $this->contextType && $this->contextId) {
            if (class_exists($this->contextType) && $this->contextSupportsTimeEntries($this->contextType)) {
                $this->loadEntries();
                $this->loadPlannedEntries();
                $this->loadCurrentPlanned();
            }
        }
        
        // Team-Übersicht nur laden wenn TimeEntry erlaubt
        if ($this->allowTimeEntry) {
            $this->loadTeamEntries();
        }
        
        // Organization Contexts laden wenn erlaubt
        if ($this->contextType && $this->contextId) {
            if ($this->allowEntities) {
                $this->loadOrganizationContexts();
                $this->loadAvailableOrganizationEntities();
            }
            if ($this->allowDimensions) {
                $this->loadCostCenters();
                $this->loadLinkedCostCenters();
                $this->loadVsmSystems();
                $this->loadLinkedVsmSystems();
            }
        }

        $this->open = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset('open', 'workDate', 'minutes', 'rate', 'note', 'activeTab', 'entries', 'plannedEntries', 'plannedMinutes', 'plannedNote', 'allowTimeEntry', 'allowEntities', 'allowDimensions', 'availableChildRelations', 'selectedOrganizationEntityId', 'selectedChildRelations', 'overviewTimeRange', 'selectedUserId', 'costCenterSearch', 'vsmSystemSearch');
        
        // Collections und Cache zurücksetzen
        $this->organizationContext = null;
        $this->availableOrganizationEntities = collect();
        $this->availableCostCenters = collect();
        $this->linkedCostCenters = collect();
        $this->availableVsmSystems = collect();
        $this->linkedVsmSystems = collect();
        $this->cachedTotalMinutes = null;
        $this->cachedBilledMinutes = null;
        $this->cachedUnbilledAmountCents = null;
        $this->cachedAvailableUsers = null;
    }

    protected function loadEntries(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->entries = collect();
            $this->cachedTotalMinutes = 0;
            $this->cachedBilledMinutes = 0;
            $this->cachedUnbilledAmountCents = 0;
            return;
        }

        // Einträge, deren Root-Kontext auf den aktuellen Kontext zeigt
        // plus Fallback: root_context_* leer, aber direct context match,
        // plus additionalContexts mit is_root = true.
        $baseQuery = OrganizationTimeEntry::query()
            ->where(function ($q) {
                $q->where(function ($rq) {
                    $rq->where('root_context_type', $this->contextType)
                       ->where('root_context_id', $this->contextId);
                })
                // Fallback: root_context_* leer, aber direkter Kontext
                ->orWhere(function ($rq) {
                    $rq->whereNull('root_context_type')
                       ->where('context_type', $this->contextType)
                       ->where('context_id', $this->contextId);
                })
                // Direkter Kontext, auch wenn root_context_* gesetzt ist (z. B. Task als Kontext, root=Project)
                ->orWhere(function ($rq) {
                    $rq->where('context_type', $this->contextType)
                       ->where('context_id', $this->contextId);
                })
                // Zusätzliche Kontexte mit is_root = true
                ->orWhereHas('additionalContexts', function ($aq) {
                    $aq->where('is_root', true)
                       ->where('context_type', $this->contextType)
                       ->where('context_id', $this->contextId);
                });
            });

        // Personen-Filter anwenden
        if ($this->selectedUserId) {
            $baseQuery->where('user_id', $this->selectedUserId);
        }

        // Zeitraum-Filter anwenden
        $baseQuery = $this->applyOverviewTimeRangeFilter($baseQuery);

        // Einträge laden
        $this->entries = (clone $baseQuery)
            ->with('user')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        // Statistiken in einem Durchgang berechnen (Performance-Optimierung)
        $this->cachedTotalMinutes = (int) (clone $baseQuery)->sum('minutes');
        $this->cachedBilledMinutes = (int) (clone $baseQuery)->where('is_billed', true)->sum('minutes');
        $this->cachedUnbilledAmountCents = (int) (clone $baseQuery)->where('is_billed', false)->sum('amount_cents');
        
        // Cache für verfügbare Benutzer zurücksetzen, damit er neu berechnet wird
        $this->cachedAvailableUsers = null;
    }

    protected function loadTeamEntries(): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation; // Child-Team (nicht dynamisch)

        if (! $team) {
            $this->teamEntries = collect();
            return;
        }

        $query = OrganizationTimeEntry::query()
            ->where('team_id', $team->id)
            ->with('user')
            ->with('additionalContexts');

        // Zeitraum-Filter anwenden
        $query = $this->applyTimeRangeFilter($query);

        $this->teamEntries = $query
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    protected function applyTimeRangeFilter($query)
    {
        $now = now();
        
        return match($this->timeRange) {
            'current_week' => $query->whereBetween('work_date', [
                $now->startOfWeek()->toDateString(),
                $now->endOfWeek()->toDateString()
            ]),
            'current_month' => $query->whereBetween('work_date', [
                $now->startOfMonth()->toDateString(),
                $now->endOfMonth()->toDateString()
            ]),
            'current_year' => $query->whereBetween('work_date', [
                $now->startOfYear()->toDateString(),
                $now->endOfYear()->toDateString()
            ]),
            'last_week' => $query->whereBetween('work_date', [
                $now->copy()->subWeek()->startOfWeek()->toDateString(),
                $now->copy()->subWeek()->endOfWeek()->toDateString()
            ]),
            'last_month' => $query->whereBetween('work_date', [
                $now->copy()->subMonth()->startOfMonth()->toDateString(),
                $now->copy()->subMonth()->endOfMonth()->toDateString()
            ]),
            default => $query,
        };
    }

    protected function applyOverviewTimeRangeFilter($query)
    {
        if ($this->overviewTimeRange === 'all') {
            return $query;
        }

        $now = now();
        
        return match($this->overviewTimeRange) {
            'current_week' => $query->whereBetween('work_date', [
                $now->startOfWeek()->toDateString(),
                $now->endOfWeek()->toDateString()
            ]),
            'current_month' => $query->whereBetween('work_date', [
                $now->startOfMonth()->toDateString(),
                $now->endOfMonth()->toDateString()
            ]),
            'current_year' => $query->whereBetween('work_date', [
                $now->startOfYear()->toDateString(),
                $now->endOfYear()->toDateString()
            ]),
            'last_week' => $query->whereBetween('work_date', [
                $now->copy()->subWeek()->startOfWeek()->toDateString(),
                $now->copy()->subWeek()->endOfWeek()->toDateString()
            ]),
            'last_month' => $query->whereBetween('work_date', [
                $now->copy()->subMonth()->startOfMonth()->toDateString(),
                $now->copy()->subMonth()->endOfMonth()->toDateString()
            ]),
            default => $query,
        };
    }

    public function getTeamTotalMinutesProperty(): int
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation; // Child-Team (nicht dynamisch)

        if (! $team) {
            return 0;
        }

        $query = OrganizationTimeEntry::query()
            ->where('team_id', $team->id);
        
        $query = $this->applyTimeRangeFilter($query);

        return (int) $query->sum('minutes');
    }

    public function getTeamBilledMinutesProperty(): int
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation; // Child-Team (nicht dynamisch)

        if (! $team) {
            return 0;
        }

        $query = OrganizationTimeEntry::query()
            ->where('team_id', $team->id)
            ->where('is_billed', true);
        
        $query = $this->applyTimeRangeFilter($query);

        return (int) $query->sum('minutes');
    }

    public function getTeamUnbilledMinutesProperty(): int
    {
        return max(0, $this->teamTotalMinutes - $this->teamBilledMinutes);
    }

    public function getTeamPlannedMinutesProperty(): ?int
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation; // Child-Team (nicht dynamisch)

        if (! $team) {
            return null;
        }

        // Summe aller aktiven Soll-Zeiten im Team
        return (int) OrganizationTimePlanned::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->sum('planned_minutes');
    }

    public function getTotalMinutesProperty(): int
    {
        // Verwende gecachte Werte für bessere Performance
        if ($this->cachedTotalMinutes !== null) {
            return $this->cachedTotalMinutes;
        }

        if (! $this->contextType || ! $this->contextId) {
            return 0;
        }

        $query = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId);

        // Filter anwenden
        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }
        $query = $this->applyOverviewTimeRangeFilter($query);

        $this->cachedTotalMinutes = (int) $query->sum('minutes');
        return $this->cachedTotalMinutes;
    }

    public function getBilledMinutesProperty(): int
    {
        // Verwende gecachte Werte für bessere Performance
        if ($this->cachedBilledMinutes !== null) {
            return $this->cachedBilledMinutes;
        }

        if (! $this->contextType || ! $this->contextId) {
            return 0;
        }

        $query = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->where('is_billed', true);

        // Filter anwenden
        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }
        $query = $this->applyOverviewTimeRangeFilter($query);

        $this->cachedBilledMinutes = (int) $query->sum('minutes');
        return $this->cachedBilledMinutes;
    }

    public function getUnbilledMinutesProperty(): int
    {
        return max(0, $this->totalMinutes - $this->billedMinutes);
    }

    public function getUnbilledAmountCentsProperty(): int
    {
        // Verwende gecachte Werte für bessere Performance
        if ($this->cachedUnbilledAmountCents !== null) {
            return $this->cachedUnbilledAmountCents;
        }

        if (! $this->contextType || ! $this->contextId) {
            return 0;
        }

        $query = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->where('is_billed', false);

        // Filter anwenden
        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }
        $query = $this->applyOverviewTimeRangeFilter($query);

        $this->cachedUnbilledAmountCents = (int) $query->sum('amount_cents');
        return $this->cachedUnbilledAmountCents;
    }

    public function toggleBilled(int $entryId): void
    {
        $entry = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->findOrFail($entryId);

        $user = Auth::user();
        $team = $user?->currentTeamRelation; // Child-Team (nicht dynamisch)

        if (! $team || $entry->team_id !== $team->id) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sie haben keine Berechtigung für diesen Eintrag.',
            ]);
            return;
        }

        $entry->is_billed = ! $entry->is_billed;
        $entry->save();

        // Cache zurücksetzen, damit Statistiken neu berechnet werden
        $this->cachedTotalMinutes = null;
        $this->cachedBilledMinutes = null;
        $this->cachedUnbilledAmountCents = null;
        
        $this->loadEntries();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $entry->is_billed ? 'Eintrag als abgerechnet markiert.' : 'Eintrag wieder auf offen gesetzt.',
        ]);
    }

    public function deleteEntry(int $entryId): void
    {
        $entry = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->findOrFail($entryId);

        $user = Auth::user();
        $team = $user?->currentTeamRelation; // Child-Team (nicht dynamisch)

        if (! $team || $entry->team_id !== $team->id) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sie haben keine Berechtigung für diesen Eintrag.',
            ]);
            return;
        }

        $entry->delete();
        
        // Cache zurücksetzen, damit Statistiken neu berechnet werden
        $this->cachedTotalMinutes = null;
        $this->cachedBilledMinutes = null;
        $this->cachedUnbilledAmountCents = null;
        
        $this->loadEntries();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Zeiteintrag gelöscht.',
        ]);
    }

    protected function loadPlannedEntries(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->plannedEntries = collect();
            return;
        }

        $this->plannedEntries = OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    protected function loadCurrentPlanned(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->plannedMinutes = null;
            return;
        }

        $current = OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->active()
            ->first();

        $this->plannedMinutes = $current?->planned_minutes;
        $this->plannedNote = $current?->note;
    }

    public function getCurrentPlannedMinutesProperty(): ?int
    {
        if (! $this->contextType || ! $this->contextId) {
            return null;
        }

        $current = OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->active()
            ->first();

        return $current?->planned_minutes;
    }

    public function savePlanned(): void
    {
        if (! Auth::check() || ! Auth::user()->currentTeamRelation) {
            $this->addError('plannedMinutes', 'Kein Team-Kontext vorhanden.');
            return;
        }

        $this->validate([
            'plannedMinutes' => ['required', 'integer', 'min:1'],
            'plannedNote' => ['nullable', 'string', 'max:500'],
        ]);

        $user = Auth::user();
        $team = $user?->currentTeamRelation; // Child-Team (nicht dynamisch)

        $contextClass = $this->contextType;
        $context = $contextClass::find($this->contextId);

        if (! $context) {
            $this->addError('plannedMinutes', 'Kontext nicht gefunden.');
            return;
        }

        if (property_exists($context, 'team_id') && (int) $context->team_id !== $team->id) {
            $this->addError('plannedMinutes', 'Kontext gehört nicht zu Ihrem Team.');
            return;
        }

        // Verwende StorePlannedTime Service für automatische Kontext-Kaskade
        $storePlannedTime = app(StorePlannedTime::class);
        
        $storePlannedTime->store([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'planned_minutes' => (int) $this->plannedMinutes,
            'note' => $this->plannedNote,
            'is_active' => true,
        ]);

        $this->loadPlannedEntries();
        $this->loadCurrentPlanned();
        $this->resetValidation();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Soll-Zeit aktualisiert.',
        ]);
    }

    public function rules(): array
    {
        return [
            'contextType' => ['required', 'string'],
            'contextId' => ['required', 'integer'],
            'workDate' => ['required', 'date'],
            'minutes' => ['required', 'integer', Rule::in($this->getMinuteOptions())],
            'rate' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function updatedMinutes($value): void
    {
        $this->minutes = (int) $value;
    }

    public function updatedTimeRange(): void
    {
        $this->loadTeamEntries();
    }

    public function updatedOverviewTimeRange(): void
    {
        $this->loadEntries();
    }

    public function updatedSelectedUserId(): void
    {
        $this->loadEntries();
    }

    public function getAvailableUsersProperty()
    {
        // Verwende gecachte Werte für bessere Performance
        if ($this->cachedAvailableUsers !== null) {
            return $this->cachedAvailableUsers;
        }

        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $team || ! $this->contextType || ! $this->contextId) {
            $this->cachedAvailableUsers = collect();
            return $this->cachedAvailableUsers;
        }

        // Hole alle Benutzer, die Zeiten für diesen Kontext haben
        // Optimiert: Verwende select() um nur die benötigten Spalten zu laden
        $userIds = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->toArray();

        if (empty($userIds)) {
            $this->cachedAvailableUsers = collect();
            return $this->cachedAvailableUsers;
        }

        $this->cachedAvailableUsers = \Platform\Core\Models\User::query()
            ->whereIn('id', $userIds)
            ->whereHas('teams', function($q) use ($team) {
                $q->where('teams.id', $team->id);
            })
            ->orderBy('name')
            ->get();
            
        return $this->cachedAvailableUsers;
    }

    public function save(): void
    {
        if (! Auth::check() || ! Auth::user()->currentTeamRelation) {
            $this->addError('contextType', 'Kein Team-Kontext vorhanden.');
            return;
        }

        $this->validate();

        $user = Auth::user();
        $team = $user?->currentTeamRelation; // Child-Team (nicht dynamisch)

        $rateCents = $this->rateToCents($this->rate);
        if ($this->rate && $rateCents === null) {
            $this->addError('rate', 'Bitte einen gültigen Betrag eingeben.');
            return;
        }

        $minutes = max(1, (int) $this->minutes);
        $amountCents = $rateCents !== null
            ? (int) round($rateCents * ($minutes / 60))
            : null;

        $contextClass = $this->contextType;
        $context = $contextClass::find($this->contextId);

        if (! $context) {
            $this->addError('contextType', 'Kontext nicht gefunden.');
            return;
        }

        if (property_exists($context, 'team_id') && (int) $context->team_id !== $team->id) {
            $this->addError('contextType', 'Kontext gehört nicht zu Ihrem Team.');
            return;
        }

        // Verwende StoreTimeEntry Service für automatische Kontext-Kaskade
        $storeTimeEntry = app(StoreTimeEntry::class);
        
        $entry = $storeTimeEntry->store([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'work_date' => $this->workDate,
            'minutes' => $minutes,
            'rate_cents' => $rateCents,
            'amount_cents' => $amountCents,
            'is_billed' => false,
            'metadata' => null,
            'note' => $this->note,
        ]);

        $this->loadEntries();
        $this->activeTab = 'overview';
        $this->resetValidation();
        $this->reset('workDate', 'minutes', 'rate', 'note');
        $this->workDate = now()->toDateString();
        $this->minutes = 60;

        $this->dispatch('organization:time-entry:saved', id: $entry->id);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Zeit erfasst',
        ]);
    }

    protected function contextSupportsTimeEntries(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return in_array(HasTimeEntries::class, class_uses_recursive($class));
    }

    protected function rateToCents(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = str_replace([' ', "'"], '', $value);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            return null;
        }

        $float = (float) $normalized;
        if ($float <= 0) {
            return null;
        }

        return (int) round($float * 100);
    }

    protected function loadOrganizationContexts(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->organizationContext = null;
            return;
        }

        $this->organizationContext = \Platform\Organization\Models\OrganizationContext::query()
            ->where('contextable_type', $this->contextType)
            ->where('contextable_id', $this->contextId)
            ->where('is_active', true)
            ->with('organizationEntity.type')
            ->first();
    }

    protected function loadAvailableOrganizationEntities(): void
    {
        $user = Auth::user();
        $team = $user?->currentTeamRelation;
        
        if (! $team) {
            $this->availableOrganizationEntities = collect();
            return;
        }

        $query = \Platform\Organization\Models\OrganizationEntity::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->with('type')
            ->orderBy('name');

        // Bereits gelinkte Entity ausschließen (falls vorhanden)
        if ($this->organizationContext && $this->organizationContext->organization_entity_id) {
            $query->where('id', '!=', $this->organizationContext->organization_entity_id);
        }

        $this->availableOrganizationEntities = $query->get();
    }

    public function attachOrganizationContext(): void
    {
        if (! $this->contextType || ! $this->contextId || ! $this->selectedOrganizationEntityId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Bitte wählen Sie eine Organization Entity aus.',
            ]);
            return;
        }

        $entity = \Platform\Organization\Models\OrganizationEntity::find($this->selectedOrganizationEntityId);
        if (! $entity) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Organization Entity nicht gefunden.',
            ]);
            return;
        }

        $contextClass = $this->contextType;
        $contextModel = $contextClass::find($this->contextId);
        if (! $contextModel) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kontext nicht gefunden.',
            ]);
            return;
        }

        // Prüfe ob Trait vorhanden
        if (! in_array(\Platform\Organization\Traits\HasOrganizationContexts::class, class_uses_recursive($contextClass))) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Dieser Kontext unterstützt keine Organization-Verknüpfungen.',
            ]);
            return;
        }

        // Verknüpfe mit ausgewählten Relations (falls vorhanden)
        $includeRelations = !empty($this->selectedChildRelations) ? $this->selectedChildRelations : null;
        
        $contextModel->attachOrganizationContext($entity, $includeRelations);

        $this->loadOrganizationContexts();
        $this->selectedOrganizationEntityId = null;
        $this->selectedChildRelations = [];

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Verknüpfung erstellt.',
        ]);
    }

    public function detachOrganizationContext(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kontext nicht gefunden.',
            ]);
            return;
        }

        $contextClass = $this->contextType;
        $contextModel = $contextClass::find($this->contextId);
        if (! $contextModel) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kontext nicht gefunden.',
            ]);
            return;
        }

        // Prüfe ob Trait vorhanden
        if (! in_array(\Platform\Organization\Traits\HasOrganizationContexts::class, class_uses_recursive($contextClass))) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Dieser Kontext unterstützt keine Organization-Verknüpfungen.',
            ]);
            return;
        }

        $contextModel->detachOrganizationContext();
        $this->loadOrganizationContexts();
        $this->loadAvailableOrganizationEntities();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Verknüpfung entfernt.',
        ]);
    }

    // Cost Center Management Methods
    protected function loadCostCenters(): void
    {
        if (! Auth::check() || ! Auth::user()->currentTeamRelation) {
            $this->availableCostCenters = collect();
            return;
        }

        $user = Auth::user();
        $team = $user->currentTeamRelation;

        $query = \Platform\Organization\Models\OrganizationCostCenter::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->orderBy('name');

        // Suche
        if (!empty($this->costCenterSearch)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->costCenterSearch . '%')
                  ->orWhere('code', 'like', '%' . $this->costCenterSearch . '%');
            });
        }

        $this->availableCostCenters = $query->get();
    }

    protected function loadLinkedCostCenters(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->linkedCostCenters = collect();
            return;
        }

        // Prüfe ob das Model das HasCostCenterLinksTrait verwendet
        if (! class_exists($this->contextType)) {
            $this->linkedCostCenters = collect();
            return;
        }

        $model = $this->contextType::find($this->contextId);
        if (! $model) {
            $this->linkedCostCenters = collect();
            return;
        }

        // Prüfe ob Trait vorhanden
        if (! in_array(\Platform\Organization\Traits\HasCostCenterLinksTrait::class, class_uses_recursive($this->contextType))) {
            $this->linkedCostCenters = collect();
            return;
        }

        // Lade verknüpfte Cost Centers
        $links = \Platform\Organization\Models\OrganizationCostCenterLink::query()
            ->where('linkable_type', $this->contextType)
            ->where('linkable_id', $this->contextId)
            ->with('costCenter')
            ->get();

        $this->linkedCostCenters = $links->map(function ($link) {
            return $link->costCenter;
        })->filter();
    }

    public function updatedCostCenterSearch(): void
    {
        $this->loadCostCenters();
    }

    public function attachCostCenter(int $costCenterId): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kein Kontext gesetzt.',
            ]);
            return;
        }

        $costCenter = \Platform\Organization\Models\OrganizationCostCenter::find($costCenterId);
        if (! $costCenter) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kostenstelle nicht gefunden.',
            ]);
            return;
        }

        $contextClass = $this->contextType;
        $contextModel = $contextClass::find($this->contextId);
        if (! $contextModel) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kontext nicht gefunden.',
            ]);
            return;
        }

        // Prüfe ob Trait vorhanden
        if (! in_array(\Platform\Organization\Traits\HasCostCenterLinksTrait::class, class_uses_recursive($contextClass))) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Dieser Kontext unterstützt keine Kostenstellen-Verknüpfungen.',
            ]);
            return;
        }

        // Verknüpfe Cost Center
        $contextModel->attachCostCenter($costCenter);

        $this->loadLinkedCostCenters();
        $this->loadCostCenters();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Kostenstelle verknüpft.',
        ]);
    }

    public function detachCostCenter(int $costCenterId): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kein Kontext gesetzt.',
            ]);
            return;
        }

        $costCenter = \Platform\Organization\Models\OrganizationCostCenter::find($costCenterId);
        if (! $costCenter) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kostenstelle nicht gefunden.',
            ]);
            return;
        }

        $contextClass = $this->contextType;
        $contextModel = $contextClass::find($this->contextId);
        if (! $contextModel) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kontext nicht gefunden.',
            ]);
            return;
        }

        // Prüfe ob Trait vorhanden
        if (! in_array(\Platform\Organization\Traits\HasCostCenterLinksTrait::class, class_uses_recursive($contextClass))) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Dieser Kontext unterstützt keine Kostenstellen-Verknüpfungen.',
            ]);
            return;
        }

        // Entferne Verknüpfung
        $contextModel->detachCostCenter($costCenter);

        $this->loadLinkedCostCenters();
        $this->loadCostCenters();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Verknüpfung entfernt.',
        ]);
    }

    // VSM System Management Methods
    protected function loadVsmSystems(): void
    {
        if (! Auth::check() || ! Auth::user()->currentTeamRelation) {
            $this->availableVsmSystems = collect();
            return;
        }

        $query = \Platform\Organization\Models\OrganizationVsmSystem::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        // Suche
        if (!empty($this->vsmSystemSearch)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->vsmSystemSearch . '%')
                  ->orWhere('code', 'like', '%' . $this->vsmSystemSearch . '%');
            });
        }

        $this->availableVsmSystems = $query->get();
    }

    protected function loadLinkedVsmSystems(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->linkedVsmSystems = collect();
            return;
        }

        // VSM-Systeme werden über Entities verknüpft
        // Wenn der Kontext eine Entity hat, zeige das VSM-System dieser Entity
        if (! class_exists($this->contextType)) {
            $this->linkedVsmSystems = collect();
            return;
        }

        $model = $this->contextType::find($this->contextId);
        if (! $model) {
            $this->linkedVsmSystems = collect();
            return;
        }

        // Prüfe ob der Kontext eine Organization Entity hat
        if (in_array(\Platform\Organization\Traits\HasOrganizationContexts::class, class_uses_recursive($this->contextType))) {
            $organizationEntity = $model->getOrganizationEntity();
            if ($organizationEntity && $organizationEntity->vsmSystem) {
                $this->linkedVsmSystems = collect([$organizationEntity->vsmSystem]);
                return;
            }
        }

        // Alternative: Direkte Verknüpfung über Entity (z.B. wenn der Kontext selbst eine Entity ist)
        if ($this->contextType === \Platform\Organization\Models\OrganizationEntity::class) {
            if ($model->vsmSystem) {
                $this->linkedVsmSystems = collect([$model->vsmSystem]);
                return;
            }
        }

        $this->linkedVsmSystems = collect();
    }

    public function updatedVsmSystemSearch(): void
    {
        $this->loadVsmSystems();
    }

    public function attachVsmSystem(int $vsmSystemId): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kein Kontext gesetzt.',
            ]);
            return;
        }

        $vsmSystem = \Platform\Organization\Models\OrganizationVsmSystem::find($vsmSystemId);
        if (! $vsmSystem) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'VSM-System nicht gefunden.',
            ]);
            return;
        }

        $contextClass = $this->contextType;
        $contextModel = $contextClass::find($this->contextId);
        if (! $contextModel) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kontext nicht gefunden.',
            ]);
            return;
        }

        // VSM-Systeme werden über Organization Entities verknüpft
        // Prüfe ob der Kontext eine Organization Entity hat oder erstelle/verknüpfe eine
        if (in_array(\Platform\Organization\Traits\HasOrganizationContexts::class, class_uses_recursive($contextClass))) {
            $organizationEntity = $contextModel->getOrganizationEntity();
            
            if (! $organizationEntity) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Bitte verknüpfen Sie zuerst eine Organization Entity.',
                ]);
                return;
            }

            // Aktualisiere das VSM-System der Entity
            $organizationEntity->vsm_system_id = $vsmSystemId;
            $organizationEntity->save();

            $this->loadLinkedVsmSystems();
            $this->loadVsmSystems();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'VSM-System verknüpft.',
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Dieser Kontext unterstützt keine VSM-System-Verknüpfungen.',
            ]);
        }
    }

    public function detachVsmSystem(int $vsmSystemId): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kein Kontext gesetzt.',
            ]);
            return;
        }

        $contextClass = $this->contextType;
        $contextModel = $contextClass::find($this->contextId);
        if (! $contextModel) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Kontext nicht gefunden.',
            ]);
            return;
        }

        // VSM-Systeme werden über Organization Entities verknüpft
        if (in_array(\Platform\Organization\Traits\HasOrganizationContexts::class, class_uses_recursive($contextClass))) {
            $organizationEntity = $contextModel->getOrganizationEntity();
            
            if (! $organizationEntity) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Keine Organization Entity verknüpft.',
                ]);
                return;
            }

            // Entferne VSM-System
            $organizationEntity->vsm_system_id = null;
            $organizationEntity->save();

            $this->loadLinkedVsmSystems();
            $this->loadVsmSystems();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Verknüpfung entfernt.',
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Dieser Kontext unterstützt keine VSM-System-Verknüpfungen.',
            ]);
        }
    }

    public function render()
    {
        return view('organization::livewire.modal-organization');
    }
}

