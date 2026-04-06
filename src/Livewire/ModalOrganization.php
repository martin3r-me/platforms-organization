<?php

namespace Platform\Organization\Livewire;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Organization\Models\OrganizationContext;
use Platform\Organization\Models\OrganizationTimeEntry;
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
    
    // Flags für erlaubte Aktionen (granular)
    public bool $allowTimeEntry = true;

    public string $workDate;
    public int $minutes = 60;
    public ?string $rate = null;
    public ?string $note = null;

    public string $activeTab = 'entry'; // 'entry', 'overview'
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
        $resolver = app(TimeContextResolver::class);

        // Primärkontext
        $label = $resolver->resolveLabel($this->contextType, $this->contextId);
        if ($label) {
            $breadcrumb[] = [
                'type' => class_basename($this->contextType),
                'label' => $label,
            ];
        }

        // Entity-Verknüpfung über OrganizationContext nachschlagen
        $orgContext = OrganizationContext::query()
            ->where('contextable_type', $this->contextType)
            ->where('contextable_id', $this->contextId)
            ->where('is_active', true)
            ->with('organizationEntity.type')
            ->first();

        if ($orgContext && $orgContext->organizationEntity) {
            $entity = $orgContext->organizationEntity;
            $breadcrumb[] = [
                'type' => $entity->type?->name ?? 'Entity',
                'label' => $entity->name,
            ];
        }

        return $breadcrumb;
    }

    public function mount(): void
    {
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

        // Flags setzen (defaults: alle true für Rückwärtskompatibilität)
        $this->allowTimeEntry = $payload['allow_time_entry'] ?? true;

        // Wenn Modal bereits offen ist, Daten neu laden
        if ($this->open && $this->contextType && $this->contextId) {
            if ($this->allowTimeEntry && class_exists($this->contextType) && $this->contextSupportsTimeEntries($this->contextType)) {
                $this->loadEntries();
                $this->loadPlannedEntries();
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
        $this->activeTab = $this->allowTimeEntry ? 'entry' : 'overview';

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
            }
        }
        
        // Team-Übersicht nur laden wenn TimeEntry erlaubt
        if ($this->allowTimeEntry) {
            $this->loadTeamEntries();
        }
        
        $this->open = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset('open', 'workDate', 'minutes', 'rate', 'note', 'activeTab', 'entries', 'plannedEntries', 'plannedMinutes', 'plannedNote', 'allowTimeEntry', 'overviewTimeRange', 'selectedUserId');

        // Cache zurücksetzen
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

        // Sammle alle Context-Paare: direkt + über include_children_relations
        $contextPairs = [$this->contextType => [$this->contextId]];
        $this->collectChildContextPairs($contextPairs);

        // Query mit allen Context-Paaren bauen
        $baseQuery = OrganizationTimeEntry::query()
            ->where(function ($q) use ($contextPairs) {
                foreach ($contextPairs as $type => $ids) {
                    $q->orWhere(function ($sq) use ($type, $ids) {
                        $sq->where('context_type', $type)
                           ->whereIn('context_id', array_unique($ids));
                    });
                }
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

        // Statistiken berechnen
        $this->cachedTotalMinutes = (int) (clone $baseQuery)->sum('minutes');
        $this->cachedBilledMinutes = (int) (clone $baseQuery)->where('is_billed', true)->sum('minutes');
        $this->cachedUnbilledAmountCents = (int) (clone $baseQuery)->where('is_billed', false)->sum('amount_cents');

        // Cache für verfügbare Benutzer zurücksetzen
        $this->cachedAvailableUsers = null;
    }

    /**
     * Sammelt Kind-Context-Paare über OrganizationContext include_children_relations.
     */
    protected function collectChildContextPairs(array &$pairs): void
    {
        $orgContext = OrganizationContext::query()
            ->where('contextable_type', $this->contextType)
            ->where('contextable_id', $this->contextId)
            ->where('is_active', true)
            ->first();

        if (! $orgContext || empty($orgContext->include_children_relations)) {
            return;
        }

        if (! class_exists($this->contextType)) {
            return;
        }

        $model = $this->contextType::find($this->contextId);
        if (! $model) {
            return;
        }

        foreach ($orgContext->include_children_relations as $relationPath) {
            $this->resolveRelationPathForPairs($model, $relationPath, $pairs);
        }
    }

    /**
     * Löst einen Relation-Pfad auf und sammelt die Ergebnis-Models als Context-Paare.
     */
    protected function resolveRelationPathForPairs($model, string $path, array &$pairs): void
    {
        $segments = explode('.', $path);
        $currentModels = collect([$model]);

        foreach ($segments as $segment) {
            $nextModels = collect();
            foreach ($currentModels as $currentModel) {
                if (! method_exists($currentModel, $segment)) {
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
            $type = get_class($leafModel);
            $pairs[$type][] = $leafModel->id;
        }
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
            ->with('user');

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

    public function getTotalPlannedMinutesProperty(): ?int
    {
        if (! $this->contextType || ! $this->contextId) {
            return null;
        }

        $sum = OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->active()
            ->sum('planned_minutes');

        return $sum > 0 ? (int) $sum : null;
    }

    public function deletePlannedEntry(int $id): void
    {
        $entry = OrganizationTimePlanned::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->where('id', $id)
            ->where('is_active', true)
            ->firstOrFail();

        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $team || $entry->team_id !== $team->id) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sie haben keine Berechtigung für diesen Eintrag.',
            ]);
            return;
        }

        $entry->update(['is_active' => false]);

        $this->loadPlannedEntries();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Budget-Eintrag deaktiviert.',
        ]);
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

        $this->plannedMinutes = null;
        $this->plannedNote = null;
        $this->loadPlannedEntries();
        $this->resetValidation();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Budget hinzugefügt.',
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

    public function render()
    {
        return view('organization::livewire.modal-organization');
    }
}

