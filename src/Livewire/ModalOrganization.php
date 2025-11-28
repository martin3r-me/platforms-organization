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

    // Flags für erlaubte Aktionen
    public bool $allowTimeEntry = true;
    public bool $allowContextManagement = true;
    public bool $canLinkToEntity = true;

    public string $workDate;
    public int $minutes = 60;
    public ?string $rate = null;
    public ?string $note = null;

    public string $activeTab = 'entry'; // 'entry', 'overview', 'planned', 'team', or 'organization'
    public string $timeRange = 'current_month'; // 'current_week', 'current_month', 'current_year', 'last_week', 'last_month'
    
    // Filter für Übersicht-Tab
    public string $overviewTimeRange = 'all'; // 'all', 'current_week', 'current_month', 'current_year', 'last_week', 'last_month'
    public ?int $selectedUserId = null; // null = alle Personen

    public $entries = [];
    public $plannedEntries = [];
    public $teamEntries = [];
    
    // Organization Context Management
    public $organizationContext = null;
    public $availableOrganizationEntities;
    public $selectedOrganizationEntityId = null;
    public array $selectedChildRelations = [];

    public ?int $plannedMinutes = null;
    public ?string $plannedNote = null;

    // Minute-Optionen: 5, 10, 15, 30, 45 Minuten, dann 1h bis 8h in 0.5h-Schritten
    // Statisch generiert, da Computed Properties in rules() nicht verfügbar sind
    protected function getMinuteOptions(): array
    {
        static $options = null;
        if ($options === null) {
            $options = [];
            // Kurze Zeiten: 5, 10, 15, 30, 45 Minuten
            $options[] = 5;
            $options[] = 10;
            $options[] = 15;
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
        $this->allowContextManagement = $payload['allow_context_management'] ?? true;
        $this->canLinkToEntity = $payload['can_link_to_entity'] ?? true;

        // Wenn Modal bereits offen ist, Daten neu laden
        if ($this->open && $this->contextType && $this->contextId) {
            if ($this->allowTimeEntry && class_exists($this->contextType) && $this->contextSupportsTimeEntries($this->contextType)) {
                $this->loadEntries();
                $this->loadPlannedEntries();
                $this->loadCurrentPlanned();
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
        } elseif ($this->allowContextManagement) {
            $this->activeTab = 'organization';
        } else {
            $this->activeTab = 'overview';
        }

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
        
        // Organization Contexts laden wenn Context Management erlaubt
        if ($this->allowContextManagement && $this->contextType && $this->contextId) {
            $this->loadOrganizationContexts();
            $this->loadAvailableOrganizationEntities();
        }

        $this->open = true;
    }

    public function close(): void
    {
        $this->resetValidation();
        $this->reset('open', 'workDate', 'minutes', 'rate', 'note', 'activeTab', 'entries', 'plannedEntries', 'plannedMinutes', 'plannedNote', 'allowTimeEntry', 'allowContextManagement', 'canLinkToEntity', 'availableChildRelations', 'selectedOrganizationEntityId', 'selectedChildRelations', 'overviewTimeRange', 'selectedUserId');
        
        // Collections zurücksetzen
        $this->organizationContext = null;
        $this->availableOrganizationEntities = collect();
    }

    protected function loadEntries(): void
    {
        if (! $this->contextType || ! $this->contextId) {
            $this->entries = collect();
            return;
        }

        $query = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->with('user');

        // Personen-Filter anwenden
        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }

        // Zeitraum-Filter anwenden
        $query = $this->applyOverviewTimeRangeFilter($query);

        $this->entries = $query
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
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

        return (int) $query->sum('minutes');
    }

    public function getBilledMinutesProperty(): int
    {
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

        return (int) $query->sum('minutes');
    }

    public function getUnbilledMinutesProperty(): int
    {
        return max(0, $this->totalMinutes - $this->billedMinutes);
    }

    public function getUnbilledAmountCentsProperty(): int
    {
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

        return (int) $query->sum('amount_cents');
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
        $user = Auth::user();
        $team = $user?->currentTeamRelation;

        if (! $team) {
            return collect();
        }

        // Hole alle Benutzer, die Zeiten für diesen Kontext haben
        $userIds = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->toArray();

        if (empty($userIds)) {
            return collect();
        }

        return \Platform\Core\Models\User::query()
            ->whereIn('id', $userIds)
            ->whereHas('teams', function($q) use ($team) {
                $q->where('teams.id', $team->id);
            })
            ->orderBy('name')
            ->get();
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

    public function render()
    {
        return view('organization::livewire.modal-organization');
    }
}

