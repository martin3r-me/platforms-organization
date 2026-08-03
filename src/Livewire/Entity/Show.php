<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;
use Platform\Organization\Models\OrganizationPerspectiveTeam;
use Platform\Organization\Services\PerspectiveService;
use Platform\Organization\Models\OrganizationInferenceRun;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Models\OrganizationEntityType;
use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationType;
use Platform\Organization\Models\OrganizationEntityRelationshipInterlink;
use Platform\Organization\Models\OrganizationInterlink;
use Platform\Core\Models\Team;
use Platform\Core\Enums\TeamRole;
use Platform\Organization\Services\EntityTimeResolver;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Services\EntityHierarchyService;
use Platform\Organization\Services\SnapshotMovementService;
use Platform\Organization\Services\DimensionRadarService;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Models\OrganizationSkill;
use Platform\Organization\Models\OrganizationSoftSkill;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Core\Models\VerbalizationChannel;
use Platform\Core\Models\VerbalizationFeed;
use Platform\Core\Models\VerbalizationOutput;
use Platform\Core\Models\VerbalizationRecipe;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Show extends Component
{
    public OrganizationEntity $entity;
    public array $form = [];
    public string $activeTab = 'hierarchy';
    public bool $showCreateTeamModal = false;
    public array $newTeam = [
        'name' => '',
        'parent_team_id' => null,
    ];

    public ?string $movementStream = null;
    public bool $analyseLoaded = false;

    // Skills tab
    public string $personSkillSearch = '';
    public string $personSoftSkillSearch = '';

    // Signals tab
    public string $signalStatusFilter = '';

    // VSM tab
    public bool $vsmAssignmentModalShow = false;
    public array $vsmAssignmentForm = [
        'vsm_system' => '',
        'assigned_entity_id' => null,
        'scope' => null,
        'notes' => null,
    ];


    public function loadAnalyseData(): void
    {
        if ($this->analyseLoaded) {
            return;
        }
        $this->analyseLoaded = true;
    }

    #[Computed]
    public function contextSummary(): array
    {
        $links = EntityDimensionBridge::linksForEntity($this->entity->id);
        $morphMap = Relation::morphMap();
        $reverseMorphMap = array_flip($morphMap);
        $registry = resolve(EntityLinkRegistry::class);
        $allConfig = $registry->allLinkTypeConfig();

        $counts = [];
        foreach ($links as $link) {
            $type = $link->linkable_type;
            if (isset($reverseMorphMap[$type])) {
                $type = $reverseMorphMap[$type];
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        $summary = [];
        foreach ($counts as $type => $count) {
            $config = $allConfig[$type] ?? null;
            $label = $config['label'] ?? ucfirst($type);
            $summary[] = [
                'type' => $type,
                'label' => $label,
                'count' => $count,
            ];
        }

        usort($summary, fn($a, $b) => strcmp($a['label'], $b['label']));

        return $summary;
    }

    #[On('perspective-switched')]
    public function onPerspectiveSwitched(): void
    {
        unset(
            $this->treeNodes,
            $this->totalDescendantCount,
            $this->totalLinkCount,
            $this->entitySignals,
            $this->vsmMatrix,
        );
    }

    // Relation CRUD
    public bool $relationFormShow = false;
    public array $relationForm = [
        'to_entity_id' => '',
        'relation_type_id' => '',
        'valid_from' => '',
        'valid_to' => '',
    ];

    // Interlink management
    public ?int $expandedRelationId = null;
    public array $interlinkForm = [
        'interlink_id' => '',
        'note' => '',
    ];

    #[Computed]
    public function linkTypeConfig(): array
    {
        return resolve(EntityLinkRegistry::class)->allLinkTypeConfig();
    }

    // ── VSM-Matrix Tab ────────────────────────────────────────

    #[Computed]
    public function isCarrierEntity(): bool
    {
        return $this->entity->type?->vsm_class === OrganizationEntityType::VSM_CLASS_CARRIER;
    }

    // ── Strategy Tab ──────────────────────────────────────────

    /**
     * Strategy artifacts attached to this carrier-entity for the Strategie-Tab.
     *
     * Shape (Modell-Shift: Fokusräume entity-nativ, Regnose optional):
     * [
     *   'mission'            => ['title','content','version','valid_from'] | null,
     *   'vision'             => ['title','content','version','valid_from'] | null,
     *   'focus_areas'        => [ ...per FA, entity-native, flat ],
     *   'transformation_map' => [
     *      'years' => [int, ...],  // sorted union of years across all focus-area milestones
     *      'grid'  => [focus_area_id => [year => [milestone,...]]],
     *      'no_year' => [focus_area_id => [milestone,...]],  // milestones without year
     *   ],
     *   'forecasts'       => [ ...per Regnose: id, title, target_date, content, current_version ],
     *   'strategy_meta'   => ['status','version','published_at','owner_name'] | null,  // Strategy-Aggregat
     *   'milestone_total' => int,
     *   'has_any'         => bool,
     * ]
     *
     * A focus_area has:
     *   id, title, description, order,
     *   vision_images => [{id,title}], obstacles => [{id,title}],
     *   milestones => [{id,title,target_year,target_quarter,order}]
     */
    #[Computed]
    public function strategy(): ?array
    {
        if (! $this->isCarrierEntity) {
            return null;
        }

        return \Platform\Organization\Services\EntityStrategyPresenter::forEntity($this->entity);
    }

    /** Vollständigkeit der Strategie gegen das Blueprint (percent, Kapitel, Issues). */
    #[Computed]
    public function strategyCompleteness(): ?array
    {
        if (! $this->isCarrierEntity) {
            return null;
        }

        // Entity-nativ (Modell-Shift): Fokusräume über entity_id, eine Regnose.
        return (new \Platform\Organization\Strategy\StrategyCompleteness())->evaluate(
            \Platform\Organization\Strategy\StrategyReader::forEntity($this->entity)
        );
    }

    /** Öffentlich teilbare URL der Strategie-Ansicht (null, wenn kein gültiger Link). */
    #[Computed]
    public function publicStrategyUrl(): ?string
    {
        return $this->entity->isPublicAccessible() ? $this->entity->getPublicUrl() : null;
    }

    /** Erzeugt (oder erneuert) den öffentlichen Strategie-Link. */
    public function generatePublicLink(): void
    {
        $this->entity->generatePublicToken();
        unset($this->publicStrategyUrl);
        $this->dispatch('toast', message: 'Öffentlicher Strategie-Link erstellt');
    }

    /** Widerruft den öffentlichen Strategie-Link. */
    public function revokePublicLink(): void
    {
        $this->entity->revokePublicToken();
        unset($this->publicStrategyUrl);
        $this->dispatch('toast', message: 'Öffentlicher Link widerrufen');
    }

    // ── Perspective ↔ Team Mapping ────────────────────────────

    #[Computed]
    public function perspectiveTeamAssignments(): array
    {
        if (! $this->isCarrierEntity) {
            return [];
        }
        return OrganizationPerspectiveTeam::query()
            ->where('perspective_entity_id', $this->entity->id)
            ->with('team:id,name,parent_team_id', 'team.parentTeam:id,name')
            ->get()
            ->map(fn ($pt) => [
                'id' => $pt->id,
                'team_id' => $pt->team_id,
                'team_name' => $pt->team?->name ?? '#'.$pt->team_id,
                'parent_name' => $pt->team?->parentTeam?->name,
                'is_default' => (bool) $pt->is_default,
            ])
            ->sortByDesc('is_default')
            ->values()
            ->all();
    }

    #[Computed]
    public function perspectiveAvailableTeams(): array
    {
        if (! $this->isCarrierEntity) {
            return [];
        }
        $assigned = OrganizationPerspectiveTeam::query()
            ->where('perspective_entity_id', $this->entity->id)
            ->pluck('team_id')
            ->all();

        // Personal-Teams ausfiltern — fuer VSM-Perspektiven nicht sinnvoll.
        // Spalte heisst personal_team (Jetstream-Konvention), nicht is_personal.
        return Team::query()
            ->whereNotIn('id', $assigned)
            ->where(fn ($q) => $q->where('personal_team', false)->orWhereNull('personal_team'))
            ->with('parentTeam:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_team_id'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'parent_name' => $t->parentTeam?->name,
            ])
            ->all();
    }

    public function attachTeamToPerspective(int $teamId): void
    {
        if (! $this->isCarrierEntity) {
            return;
        }
        $team = Team::query()->find($teamId);
        if (! $team) {
            return;
        }

        // Wenn es noch keinen Default fuer dieses Team gibt, neue Zuordnung als Default.
        $hasDefault = OrganizationPerspectiveTeam::query()
            ->where('team_id', $teamId)
            ->where('is_default', true)
            ->exists();

        OrganizationPerspectiveTeam::updateOrCreate(
            ['perspective_entity_id' => $this->entity->id, 'team_id' => $teamId],
            ['is_default' => ! $hasDefault],
        );

        unset($this->perspectiveTeamAssignments, $this->perspectiveAvailableTeams);
        session()->flash('message', 'Team ' . $team->name . ' zugeordnet.');
    }

    public function detachTeamFromPerspective(int $perspectiveTeamId): void
    {
        if (! $this->isCarrierEntity) {
            return;
        }
        $pt = OrganizationPerspectiveTeam::query()
            ->where('id', $perspectiveTeamId)
            ->where('perspective_entity_id', $this->entity->id)
            ->first();
        if (! $pt) {
            return;
        }
        $pt->delete();

        unset($this->perspectiveTeamAssignments, $this->perspectiveAvailableTeams);
        session()->flash('message', 'Team-Zuordnung entfernt.');
    }

    public function markPerspectiveTeamDefault(int $perspectiveTeamId): void
    {
        if (! $this->isCarrierEntity) {
            return;
        }
        $pt = OrganizationPerspectiveTeam::query()
            ->where('id', $perspectiveTeamId)
            ->where('perspective_entity_id', $this->entity->id)
            ->first();
        if (! $pt) {
            return;
        }

        PerspectiveService::setTeamDefault($this->entity->id, (int) $pt->team_id);

        unset($this->perspectiveTeamAssignments);
        session()->flash('message', 'Als Standard-Perspektive für dieses Team gesetzt.');
    }

    /**
     * Liefert pro VSM-Ebene die Liste der Assignees fuer diese Carrier-Entity.
     * Format: [systemCode => ['label' => ..., 'assignments' => [...]]]
     */
    #[Computed]
    public function vsmMatrix(): array
    {
        if (!$this->isCarrierEntity) {
            return [];
        }

        $assignments = OrganizationEntityVsmAssignment::query()
            ->where('perspective_entity_id', $this->entity->id)
            ->with(['assignedEntity:id,name,code', 'assignedEntity.type:id,name,code'])
            ->orderBy('assigned_entity_id')
            ->get()
            ->groupBy('vsm_system');

        $matrix = [];
        foreach (OrganizationEntityVsmAssignment::VSM_DEFINITIONS as $code => $def) {
            $cellAssignments = ($assignments[$code] ?? collect())->map(fn ($a) => [
                'id' => $a->id,
                'assigned_entity_id' => $a->assigned_entity_id,
                'assigned_name' => $a->assignedEntity?->name,
                'assigned_type' => $a->assignedEntity?->type?->name,
                'scope' => $a->scope,
                'valid_from' => $a->valid_from?->toDateString(),
                'valid_until' => $a->valid_until?->toDateString(),
                'notes' => $a->notes,
                'is_active_today' => $a->isActiveAt(),
            ])->values()->toArray();

            $matrix[$code] = [
                'code' => $code,
                'label' => $def['label'],
                'description' => $def['description'],
                'icon' => $def['icon'],
                'assignments' => $cellAssignments,
                'is_vacant' => count($cellAssignments) === 0,
            ];
        }

        return $matrix;
    }

    /**
     * Alle Actor-Entities im Team — Auswahlliste fuer den Assignee-Picker.
     * Bewusst team-weit (nicht subtree-only): cross-tree-Zuordnungen sind erlaubt.
     */
    #[Computed]
    public function vsmActorEntities()
    {
        return OrganizationEntity::query()
            ->where('team_id', $this->entity->team_id)
            ->active()
            ->whereHas('type', fn ($q) => $q->where('vsm_class', OrganizationEntityType::VSM_CLASS_ACTOR))
            ->with('type:id,name,code')
            ->orderBy('name')
            ->get();
    }

    public function openVsmAssignmentModal(string $system): void
    {
        if (!$this->isCarrierEntity) {
            return;
        }
        if (!in_array($system, OrganizationEntityVsmAssignment::VSM_SYSTEMS, true)) {
            return;
        }

        $this->vsmAssignmentForm = [
            'vsm_system' => $system,
            'assigned_entity_id' => null,
            'scope' => null,
            'notes' => null,
        ];
        $this->vsmAssignmentModalShow = true;
    }

    public function closeVsmAssignmentModal(): void
    {
        $this->vsmAssignmentModalShow = false;
        $this->reset('vsmAssignmentForm');
    }

    public function addVsmAssignment(): void
    {
        if (!$this->isCarrierEntity) {
            return;
        }

        $data = $this->validate([
            'vsmAssignmentForm.vsm_system' => 'required|string',
            'vsmAssignmentForm.assigned_entity_id' => 'required|integer|exists:organization_entities,id',
            'vsmAssignmentForm.scope' => 'nullable|string|max:255',
            'vsmAssignmentForm.notes' => 'nullable|string',
        ])['vsmAssignmentForm'];

        // Duplikat-Check fuer freundlichere Fehlermeldung
        $exists = OrganizationEntityVsmAssignment::query()
            ->where('perspective_entity_id', $this->entity->id)
            ->where('vsm_system', $data['vsm_system'])
            ->where('assigned_entity_id', (int) $data['assigned_entity_id'])
            ->exists();
        if ($exists) {
            session()->flash('error', 'Diese Zuordnung existiert bereits.');
            return;
        }

        try {
            OrganizationEntityVsmAssignment::create([
                'team_id' => $this->entity->team_id,
                'perspective_entity_id' => $this->entity->id,
                'vsm_system' => $data['vsm_system'],
                'assigned_entity_id' => (int) $data['assigned_entity_id'],
                'scope' => $data['scope'] ?: null,
                'notes' => $data['notes'] ?: null,
                'created_by_user_id' => auth()->id(),
            ]);

            $this->closeVsmAssignmentModal();
            unset($this->vsmMatrix);
            session()->flash('message', 'VSM-Zuordnung angelegt.');
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', 'Validierung: ' . $e->getMessage());
        } catch (\Throwable $e) {
            session()->flash('error', 'Fehler: ' . $e->getMessage());
        }
    }

    // ── System-Agent Tab ──────────────────────────────────────

    #[Computed]
    public function isSystemAgent(): bool
    {
        return $this->entity->type?->code === 'system_agent';
    }

    #[Computed]
    public function agentPrompts()
    {
        if (!$this->isSystemAgent) {
            return collect();
        }
        return OrganizationSignalInferencePrompt::query()
            ->where('agent_entity_id', $this->entity->id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function agentRecentRuns()
    {
        if (!$this->isSystemAgent) {
            return collect();
        }
        $promptIds = $this->agentPrompts->pluck('id');
        if ($promptIds->isEmpty()) {
            return collect();
        }

        return OrganizationInferenceRun::query()
            ->whereHas('steps', fn ($q) => $q->whereIn('inference_prompt_id', $promptIds))
            ->latest()
            ->limit(15)
            ->get();
    }

    public function toggleEntityActive(): void
    {
        $this->entity->update(['is_active' => !$this->entity->is_active]);
        $this->entity->refresh();
        unset($this->agentPrompts, $this->agentRecentRuns);
        session()->flash('message', $this->entity->is_active
            ? 'Entity aktiviert.'
            : 'Entity deaktiviert.');
    }

    public function removeVsmAssignment(int $assignmentId): void
    {
        $assignment = OrganizationEntityVsmAssignment::query()
            ->where('id', $assignmentId)
            ->where('perspective_entity_id', $this->entity->id)
            ->first();

        if (!$assignment) {
            return;
        }

        $assignment->delete();
        unset($this->vsmMatrix);
        session()->flash('message', 'VSM-Zuordnung entfernt.');
    }

    public function mount(OrganizationEntity $entity)
    {
        $this->entity = $entity->load([
            'type.group',
            'parent',
            'children.type',
            'children.children',
            'team',
            'user',
            'linkedUser',
            'relationsFrom.toEntity.type',
            'relationsFrom.relationType',
            'relationsTo.fromEntity.type',
            'relationsTo.relationType'
        ]);
        $this->loadForm();
    }

    /** Fremd-ID-Editor: Zeilen [['system','value','label'], ...]. */
    public array $identifiers = [];

    public function loadForm()
    {
        $this->form = [
            'name' => $this->entity->name,
            'code' => $this->entity->code,
            'description' => $this->entity->description,
            'entity_type_id' => $this->entity->entity_type_id,
            'parent_entity_id' => $this->entity->parent_entity_id,
            'linked_user_id' => $this->entity->linked_user_id,
            'is_active' => $this->entity->is_active,
        ];

        $this->loadIdentifiers();
    }

    /** Lädt die Fremd-IDs der Entity in den Editor (Kostenstelle ist nur eine Zeile davon). */
    public function loadIdentifiers()
    {
        $this->entity->unsetRelation('externalIds');

        $this->identifiers = $this->entity->externalIds
            ->sortBy('system')
            ->map(fn ($e) => [
                'system' => $e->system,
                'value'  => $e->value,
                'label'  => $e->label,
            ])
            ->values()
            ->all();
    }

    /** Fügt eine leere Fremd-ID-Zeile hinzu. */
    public function addIdentifier(): void
    {
        $this->identifiers[] = ['system' => '', 'value' => '', 'label' => ''];
    }

    /** Entfernt eine Fremd-ID-Zeile aus dem Editor (persistiert erst beim Speichern). */
    public function removeIdentifier(int $index): void
    {
        unset($this->identifiers[$index]);
        $this->identifiers = array_values($this->identifiers);
    }

    /** Normalisierte, nicht-leere Editor-Zeilen (system+value gesetzt). */
    private function normalizedIdentifiers(): array
    {
        return collect($this->identifiers)
            ->map(fn ($r) => [
                'system' => trim($r['system'] ?? ''),
                'value'  => trim($r['value'] ?? ''),
                'label'  => (trim($r['label'] ?? '') ?: null),
            ])
            ->filter(fn ($r) => $r['system'] !== '' && $r['value'] !== '')
            ->sortBy('system')
            ->values()
            ->all();
    }

    public function edit()
    {
        $this->activeTab = 'data';
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.code' => 'nullable|string|max:255',
            'form.description' => 'nullable|string',
            'form.entity_type_id' => 'required|exists:organization_entity_types,id',
            'form.parent_entity_id' => 'nullable|exists:organization_entities,id',
            'form.linked_user_id' => 'nullable|exists:users,id',
            'form.is_active' => 'boolean',
        ]);

        $identifiers = $this->normalizedIdentifiers();

        // Ein System darf pro Entity nur einmal vorkommen (system ist der Schlüssel).
        $systems = array_column($identifiers, 'system');
        if (count($systems) !== count(array_unique($systems))) {
            session()->flash('error', 'Jedes Fremd-ID-System darf pro Einheit nur einmal vorkommen.');
            return;
        }

        try {
            $this->entity->update($this->form);

            // Fremd-IDs synchronisieren: entfernte Systeme löschen, vorhandene upserten.
            $keep = array_column($identifiers, 'system');
            $this->entity->externalIds()
                ->when($keep, fn ($q) => $q->whereNotIn('system', $keep), fn ($q) => $q)
                ->delete();

            foreach ($identifiers as $row) {
                $this->entity->setExternalId($row['system'], $row['value'], $row['label']);
            }

            $this->loadForm();
            session()->flash('message', 'Organisationseinheit erfolgreich aktualisiert.');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Speichern: ' . $e->getMessage());
        }
    }

    #[Computed]
    public function isDirty()
    {
        $entityIdentifiers = $this->entity->externalIds
            ->sortBy('system')
            ->map(fn ($e) => ['system' => $e->system, 'value' => $e->value, 'label' => $e->label ?: null])
            ->values()
            ->all();

        return $this->form['name'] !== $this->entity->name ||
               $this->form['code'] !== $this->entity->code ||
               $this->form['description'] !== $this->entity->description ||
               $this->form['entity_type_id'] != $this->entity->entity_type_id ||
               $this->form['parent_entity_id'] != $this->entity->parent_entity_id ||
               $this->form['linked_user_id'] != $this->entity->linked_user_id ||
               $this->form['is_active'] !== $this->entity->is_active ||
               $this->normalizedIdentifiers() != $entityIdentifiers;
    }

    public function getEntityTypesProperty()
    {
        return OrganizationEntityType::active()
            ->ordered()
            ->with('group')
            ->get()
            ->groupBy('group.name');
    }

    public function getParentEntitiesProperty()
    {
        return OrganizationEntity::active()
            ->forTeam(auth()->user()->currentTeam->id)
            ->where('id', '!=', $this->entity->id) // Exclude self
            ->with('type')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function hasLinkedUser(): bool
    {
        return !is_null($this->entity->linked_user_id);
    }

    // ── Modul-Zugang (an/aus pro Person) ────────────────────────
    // Schreibt/entfernt authz_grant(scope=module, capability='use') für dieses
    // Person-Entity. Content-Rechte (read/write/owner) gehören bewusst NICHT
    // hierher, sondern auf die Knoten-Ebene der Organisationsstruktur.

    #[Computed]
    public function moduleAccessRows()
    {
        // Defensiv: solange der Authz-Kernel nicht migriert ist, darf die
        // Person-Seite nicht crashen — leere Liste statt Fehler.
        if (! \Illuminate\Support\Facades\Schema::hasTable('authz_grant')) {
            return collect();
        }

        $granted = \Platform\Core\Models\AuthzGrant::query()
            ->where('subject_type', 'entity')
            ->where('subject_id', $this->entity->id)
            ->where('scope_type', 'module')
            ->where('capability', 'use')
            ->pluck('scope_key')
            ->all();

        return \Platform\Core\Models\Module::query()
            ->orderBy('title')
            ->get(['id', 'key', 'title'])
            ->map(fn ($m) => [
                'key'     => $m->key,
                'title'   => $m->title ?: $m->key,
                'enabled' => in_array($m->key, $granted, true),
            ]);
    }

    #[Computed]
    public function canManageModuleAccess(): bool
    {
        $user = auth()->user();
        $team = $user?->currentTeam;
        $role = $team
            ? $user->teams()->where('teams.id', $team->id)->first()?->pivot?->role
            : null;

        return in_array($role, [TeamRole::OWNER->value, TeamRole::ADMIN->value], true);
    }

    public function toggleModule(string $moduleKey): void
    {
        if (! $this->canManageModuleAccess) {
            abort(403, 'Nur Team-Admins dürfen Modul-Zugänge vergeben.');
        }

        $existing = \Platform\Core\Models\AuthzGrant::query()
            ->where('subject_type', 'entity')
            ->where('subject_id', $this->entity->id)
            ->where('scope_type', 'module')
            ->where('scope_key', $moduleKey)
            ->where('capability', 'use')
            ->first();

        if ($existing) {
            $existing->delete();
            $message = 'Modul deaktiviert';
        } else {
            \Platform\Core\Models\AuthzGrant::create([
                'subject_type' => 'entity',
                'subject_id'   => $this->entity->id,
                'capability'   => 'use',
                'scope_type'   => 'module',
                'scope_id'     => null,
                'scope_key'    => $moduleKey,
                'source'       => 'ui:person-module',
                'team_id'      => $this->entity->team_id,
            ]);
            $message = 'Modul aktiviert';
        }

        unset($this->moduleAccessRows);
        $this->dispatch('toast', message: $message);
    }

    public function getTeamUsersProperty()
    {
        $team = auth()->user()->currentTeam;
        if (!$team) {
            return collect();
        }

        return $team->users()->orderBy('name')->get(['users.id', 'users.name', 'users.email']);
    }

    // ── Relation & Interlink Management ─────────────────────────

    #[Computed]
    public function relationsFrom()
    {
        return $this->entity->relationsFrom()
            ->with(['toEntity.type', 'relationType', 'interlinks.interlink.category', 'interlinks.interlink.type'])
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function relationsTo()
    {
        return $this->entity->relationsTo()
            ->with(['fromEntity.type', 'relationType', 'interlinks.interlink.category', 'interlinks.interlink.type'])
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function availableRelationTypes()
    {
        return OrganizationEntityRelationType::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableRelationEntities()
    {
        return OrganizationEntity::where('team_id', auth()->user()->currentTeam->id)
            ->where('id', '!=', $this->entity->id)
            ->where('is_active', true)
            ->with('type')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableInterlinks()
    {
        return OrganizationInterlink::where('team_id', auth()->user()->currentTeam->id)
            ->active()
            ->with(['category', 'type'])
            ->orderBy('name')
            ->get();
    }

    public function createRelation(): void
    {
        $this->validate([
            'relationForm.to_entity_id' => 'required|integer|exists:organization_entities,id',
            'relationForm.relation_type_id' => 'required|integer|exists:organization_entity_relation_types,id',
            'relationForm.valid_from' => 'nullable|date',
            'relationForm.valid_to' => 'nullable|date|after_or_equal:relationForm.valid_from',
        ]);

        $exists = OrganizationEntityRelationship::where('from_entity_id', $this->entity->id)
            ->where('to_entity_id', (int) $this->relationForm['to_entity_id'])
            ->where('relation_type_id', (int) $this->relationForm['relation_type_id'])
            ->exists();

        if ($exists) {
            $this->addError('relationForm.to_entity_id', 'Diese Beziehung existiert bereits.');
            return;
        }

        OrganizationEntityRelationship::create([
            'from_entity_id' => $this->entity->id,
            'to_entity_id' => (int) $this->relationForm['to_entity_id'],
            'relation_type_id' => (int) $this->relationForm['relation_type_id'],
            'valid_from' => $this->relationForm['valid_from'] !== '' ? $this->relationForm['valid_from'] : null,
            'valid_to' => $this->relationForm['valid_to'] !== '' ? $this->relationForm['valid_to'] : null,
        ]);

        $this->relationForm = ['to_entity_id' => '', 'relation_type_id' => '', 'valid_from' => '', 'valid_to' => ''];
        $this->relationFormShow = false;
        unset($this->relationsFrom, $this->relationsTo);
        $this->dispatch('toast', message: 'Beziehung erstellt');
    }

    public function deleteRelation(int $id): void
    {
        $relation = OrganizationEntityRelationship::find($id);
        if (! $relation) return;

        if ((int) $relation->team_id !== (int) auth()->user()->currentTeam->id) {
            $this->dispatch('toast', message: 'Keine Berechtigung', variant: 'danger');
            return;
        }

        $relation->delete();
        unset($this->relationsFrom, $this->relationsTo);
        $this->dispatch('toast', message: 'Beziehung gelöscht');
    }

    public function toggleRelationInterlinks(int $relationId): void
    {
        if ($this->expandedRelationId === $relationId) {
            $this->expandedRelationId = null;
        } else {
            $this->expandedRelationId = $relationId;
        }
        $this->interlinkForm = ['interlink_id' => '', 'note' => ''];
    }

    public function linkInterlink(int $relationId): void
    {
        $this->validate([
            'interlinkForm.interlink_id' => 'required|integer|exists:organization_interlinks,id',
            'interlinkForm.note' => 'nullable|string|max:500',
        ]);

        $exists = OrganizationEntityRelationshipInterlink::where('entity_relationship_id', $relationId)
            ->where('interlink_id', (int) $this->interlinkForm['interlink_id'])
            ->exists();

        if ($exists) {
            $this->addError('interlinkForm.interlink_id', 'Diese Schnittstelle ist bereits verknüpft.');
            return;
        }

        OrganizationEntityRelationshipInterlink::create([
            'entity_relationship_id' => $relationId,
            'interlink_id' => (int) $this->interlinkForm['interlink_id'],
            'note' => $this->interlinkForm['note'] !== '' ? $this->interlinkForm['note'] : null,
            'is_active' => true,
        ]);

        $this->interlinkForm = ['interlink_id' => '', 'note' => ''];
        unset($this->relationsFrom, $this->relationsTo);
        $this->dispatch('toast', message: 'Schnittstelle verknüpft');
    }

    public function unlinkInterlink(int $pivotId): void
    {
        $pivot = OrganizationEntityRelationshipInterlink::find($pivotId);
        if (! $pivot) return;

        $pivot->delete();
        unset($this->relationsFrom, $this->relationsTo);
        $this->dispatch('toast', message: 'Schnittstelle entfernt');
    }

    public function openCreateTeamModal()
    {
        $this->showCreateTeamModal = true;
        $this->newTeam = [
            'name' => $this->entity->code ?? $this->entity->name,
            'parent_team_id' => null,
        ];
    }

    public function closeCreateTeamModal()
    {
        $this->showCreateTeamModal = false;
        $this->newTeam = [
            'name' => '',
            'parent_team_id' => null,
        ];
    }

    public function createTeam()
    {
        $this->validate([
            'newTeam.name' => 'required|string|max:255',
            'newTeam.parent_team_id' => 'nullable|exists:teams,id',
        ]);

        try {
            $user = auth()->user();
            
            // Prüfe ob parent_team_id gesetzt ist und ob der User Zugriff darauf hat
            if ($this->newTeam['parent_team_id']) {
                $parentTeam = Team::find($this->newTeam['parent_team_id']);
                if (!$parentTeam || !$user->teams()->where('teams.id', $parentTeam->id)->exists()) {
                    session()->flash('error', 'Sie haben keinen Zugriff auf das ausgewählte Parent-Team.');
                    return;
                }
            }

            $team = Team::create([
                'name' => $this->newTeam['name'],
                'user_id' => $user->id,
                'parent_team_id' => $this->newTeam['parent_team_id'] ?: null,
                'personal_team' => false,
            ]);

            // Füge den User als Owner zum Team hinzu
            $user->teams()->attach($team->id, ['role' => TeamRole::OWNER->value]);

            // Verlinke das Team direkt mit der Entität via DimensionLink
            EntityDimensionBridge::createLink($this->entity->id, Team::class, $team->id);

            $this->closeCreateTeamModal();
            $this->entity->refresh();
            session()->flash('message', 'Team erfolgreich erstellt und mit der Entität verlinkt.');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Erstellen des Teams: ' . $e->getMessage());
        }
    }

    #[Computed]
    public function availableTeams()
    {
        $user = auth()->user();
        if (!$user) {
            return collect();
        }

        // Nur Root-Teams können als Parent-Teams verwendet werden
        return Team::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->whereNull('parent_team_id')
        ->orderBy('name')
        ->get();
    }

    #[Computed]
    public function displayRules(): array
    {
        return resolve(EntityLinkRegistry::class)->allMetadataDisplayRules();
    }

    #[Computed]
    public function linkTypeIconSvgs(): array
    {
        $iconMap = [
            'user-check' => 'user',
            'user-voice' => 'user',
            'folder-kanban' => 'folder',
            'briefcase-globe' => 'briefcase',
            'server-cog' => 'server',
            'package-check' => 'archive-box',
            'badge-check' => 'check-badge',
            'target' => 'viewfinder-circle',
            'arrow-right-left' => 'arrows-right-left',
        ];

        $svgs = [];
        foreach ($this->linkTypeConfig as $type => $config) {
            $icon = $iconMap[$config['icon']] ?? $config['icon'];
            try {
                $svgs[$type] = svg('heroicon-o-' . $icon, 'w-4 h-4 text-[var(--ui-muted)]')->toHtml();
            } catch (\Throwable $e) {
                $svgs[$type] = svg('heroicon-o-link', 'w-4 h-4 text-[var(--ui-muted)]')->toHtml();
            }
        }
        return $svgs;
    }

    #[Computed]
    public function treeNodes(): array
    {
        $children = $this->entity->children()
                ->with('type.group')
                ->get();

        if ($children->isEmpty()) {
            return [];
        }

        // Group children by EntityTypeGroup. Within group: type.sort_order then name.
        // Outer group order: EntityTypeGroup.sort_order.
        $sortSiblings = fn ($c) => $c->sortBy([
            ['type.sort_order', 'asc'],
            ['name', 'asc'],
        ])->values();

        $childrenByGroup = $children
            ->groupBy(fn ($e) => $e->type->group->id ?? 0)
            ->map($sortSiblings)
            ->sortBy(fn ($group) => $group->first()->type?->group?->sort_order ?? PHP_INT_MAX);

        // Build node data for ALL children in one pass, then redistribute by group.
        $flatChildren = $childrenByGroup->flatten();
        $allNodes = $this->buildNodesForEntities($flatChildren);
        $nodesById = collect($allNodes)->keyBy('id');

        $sections = [];
        foreach ($childrenByGroup as $groupId => $groupChildren) {
            $first = $groupChildren->first();
            $sectionNodes = [];
            foreach ($groupChildren as $child) {
                $node = $nodesById->get($child->id);
                if ($node !== null) {
                    $sectionNodes[] = $node;
                }
            }
            if (empty($sectionNodes)) {
                continue;
            }
            $sections[] = [
                'group_id' => $groupId,
                'group_name' => $first->type?->group?->name ?? 'Sonstige',
                'nodes' => $sectionNodes,
            ];
        }

        return $sections;
    }

    #[Computed]
    public function entityTimeSummary(): array
    {
        $resolver = new EntityTimeResolver();
        return $this->getTimeSummaryForEntity($this->entity, $resolver);
    }

    #[Computed]
    public function cascadedTimeSummary(): array
    {
        $resolver = new EntityTimeResolver();
        return $this->getTimeSummaryForEntity($this->entity, $resolver, includeChildren: true);
    }

    #[Computed]
    public function monthlyTimeData(): array
    {
        try {
            $resolver = new EntityTimeResolver();
            $query = $resolver->buildTimeEntryQuery($this->entity, includeChildEntities: true);

            $dbRows = $query
                ->selectRaw("DATE_FORMAT(work_date, '%Y-%m') as month")
                ->selectRaw('COALESCE(SUM(minutes), 0) as total_minutes')
                ->selectRaw('COALESCE(SUM(CASE WHEN is_billed = 1 THEN minutes ELSE 0 END), 0) as billed_minutes')
                ->groupByRaw("DATE_FORMAT(work_date, '%Y-%m')")
                ->get()
                ->keyBy('month');

            $months = [];
            $maxMinutes = 0;
            $now = Carbon::now();
            $germanMonths = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

            for ($i = 11; $i >= 0; $i--) {
                $date = $now->copy()->subMonths($i);
                $key = $date->format('Y-m');
                $row = $dbRows[$key] ?? null;

                $totalMin = (int) ($row?->total_minutes ?? 0);
                $billedMin = (int) ($row?->billed_minutes ?? 0);
                $openMin = $totalMin - $billedMin;

                if ($totalMin > $maxMinutes) {
                    $maxMinutes = $totalMin;
                }

                $months[] = [
                    'month' => $key,
                    'label' => $germanMonths[$date->month - 1],
                    'year' => $date->format('Y'),
                    'total_minutes' => $totalMin,
                    'billed_minutes' => $billedMin,
                    'open_minutes' => $openMin,
                ];
            }

            return [
                'months' => $months,
                'max_minutes' => $maxMinutes,
            ];
        } catch (\Exception $e) {
            return [
                'months' => [],
                'max_minutes' => 0,
            ];
        }
    }

    #[Computed]
    public function dimensionRadar(): array
    {
        return resolve(DimensionRadarService::class)
            ->computeRadar($this->entity->id, $this->entity->team_id);
    }

    #[Computed]
    public function movement(): array
    {
        $service = resolve(SnapshotMovementService::class);
        $result = $service->forEntity($this->entity->id, 7, $this->movementStream);

        return $result->toArray();
    }

    #[Computed]
    public function availableStreams(): array
    {
        $service = resolve(SnapshotMovementService::class);
        $all = $service->forEntity($this->entity->id, 7);

        return array_keys(array_filter($all->byGroup(), fn ($deltas) => collect($deltas)->contains(fn ($d) => $d->current > 0 || $d->previous > 0)));
    }

    #[Computed]
    public function snapshotAnalysis(): array
    {
        $snapshots = OrganizationEntitySnapshot::where('entity_id', $this->entity->id)
            ->forDateRange(now()->subDays(30), now())
            ->orderBy('snapshot_date')
            ->get();

        if ($snapshots->isEmpty()) {
            return [];
        }

        $latest = $snapshots->last();
        $latestMetrics = $latest->metrics;

        // Find snapshot closest to 7 days ago
        $sevenDaysAgo = now()->subDays(7)->toDateString();
        $ago7d = $snapshots->filter(fn($s) => $s->snapshot_date->toDateString() <= $sevenDaysAgo)->last();
        $ago7dMetrics = $ago7d ? $ago7d->metrics : null;

        $itemsTotal = $latestMetrics['items_total'] ?? 0;
        $itemsDone = $latestMetrics['items_done'] ?? 0;
        $completionRate = $itemsTotal > 0 ? round(($itemsDone / $itemsTotal) * 100, 1) : 0;

        $agoItemsDone = $ago7dMetrics ? ($ago7dMetrics['items_done'] ?? 0) : 0;
        $agoItemsTotal = $ago7dMetrics ? ($ago7dMetrics['items_total'] ?? 0) : 0;
        $agoCompletionRate = $agoItemsTotal > 0 ? round(($agoItemsDone / $agoItemsTotal) * 100, 1) : 0;

        $itemsCompleted7d = max(0, $itemsDone - $agoItemsDone);
        $itemsAdded7d = max(0, $itemsTotal - $agoItemsTotal);
        $netProgress = $itemsCompleted7d - $itemsAdded7d;

        // Velocity: items completed per day over 30 days
        $oldest = $snapshots->first();
        $daysDiff = max(1, $oldest->snapshot_date->diffInDays($latest->snapshot_date));
        $totalCompleted30d = max(0, $itemsDone - ($oldest->metrics['items_done'] ?? 0));
        $velocityDailyAvg = round($totalCompleted30d / $daysDiff, 1);

        // Estimated days remaining
        $openItems = max(0, $itemsTotal - $itemsDone);
        $estimatedDaysRemaining = ($velocityDailyAvg > 0 && $openItems > 0) ? (int) ceil($openItems / $velocityDailyAvg) : null;

        // Billing
        $timeTotalMin = $latestMetrics['time_total_minutes'] ?? 0;
        $timeBilledMin = $latestMetrics['time_billed_minutes'] ?? 0;
        $billingRate = $timeTotalMin > 0 ? round(($timeBilledMin / $timeTotalMin) * 100, 1) : 0;

        $agoTimeTotalMin = $ago7dMetrics ? ($ago7dMetrics['time_total_minutes'] ?? 0) : 0;
        $agoTimeBilledMin = $ago7dMetrics ? ($ago7dMetrics['time_billed_minutes'] ?? 0) : 0;
        $agoBillingRate = $agoTimeTotalMin > 0 ? round(($agoTimeBilledMin / $agoTimeTotalMin) * 100, 1) : 0;

        // Health status
        $healthStatus = $this->classifyHealth($itemsTotal, $itemsDone, $agoItemsTotal, $agoItemsDone);

        // Insight statements
        $insights = $this->buildSnapshotInsights(
            $completionRate, $agoCompletionRate, $itemsCompleted7d, $itemsAdded7d,
            $velocityDailyAvg, $estimatedDaysRemaining, $billingRate, $agoBillingRate, $healthStatus
        );

        return [
            'completion_rate' => $completionRate,
            'trend_completion' => round($completionRate - $agoCompletionRate, 1),
            'items_completed_7d' => $itemsCompleted7d,
            'items_added_7d' => $itemsAdded7d,
            'net_progress' => $netProgress,
            'velocity_daily_avg' => $velocityDailyAvg,
            'estimated_days_remaining' => $estimatedDaysRemaining,
            'billing_rate' => $billingRate,
            'trend_billing' => round($billingRate - $agoBillingRate, 1),
            'health_status' => $healthStatus,
            'insights' => $insights,
            'items_total' => $itemsTotal,
            'items_done' => $itemsDone,
        ];
    }

    #[Computed]
    public function childrenHealthSummary(): ?array
    {
        $children = $this->entity->children;
        if ($children->isEmpty()) {
            return null;
        }

        $childIds = $children->pluck('id')->toArray();
        $service = resolve(SnapshotMovementService::class);
        $batch = $service->forEntitiesBatch($childIds, 7);

        $counts = ['progressing' => 0, 'completed' => 0, 'stalled' => 0, 'at_risk' => 0];
        foreach ($childIds as $id) {
            $data = $batch[$id] ?? null;
            if (!$data) {
                $counts['progressing']++;
                continue;
            }
            if ($data['score'] > 0) {
                $counts['progressing']++;
            } elseif ($data['score'] == 0 && $data['delta_count'] == 0) {
                $counts['stalled']++;
            } elseif ($data['score'] < 0) {
                $counts['at_risk']++;
            } else {
                $counts['progressing']++;
            }
        }

        return $counts;
    }

    protected function classifyHealth(int $itemsTotal, int $itemsDone, int $agoItemsTotal, int $agoItemsDone): string
    {
        if ($itemsDone >= $itemsTotal && $itemsTotal > 0) {
            return 'completed';
        }
        if ($itemsTotal > $agoItemsTotal && $itemsDone <= $agoItemsDone && $itemsTotal > 0) {
            return 'at_risk';
        }
        if ($itemsDone <= $agoItemsDone && ($itemsTotal - $itemsDone) > 0) {
            return 'stalled';
        }
        return 'progressing';
    }

    protected function buildSnapshotInsights(
        float $completionRate, float $agoCompletionRate,
        int $itemsCompleted7d, int $itemsAdded7d,
        float $velocityDailyAvg, ?int $estimatedDaysRemaining,
        float $billingRate, float $agoBillingRate,
        string $healthStatus
    ): array {
        $insights = [];

        // Completion trend
        $diff = round($completionRate - $agoCompletionRate, 1);
        if ($completionRate > 0) {
            if ($diff > 0) {
                $insights[] = ['text' => "Fortschritt bei {$completionRate}% — +{$diff}% in 7 Tagen.", 'type' => 'success'];
            } elseif ($diff < 0) {
                $insights[] = ['text' => "Fortschritt bei {$completionRate}% — " . abs($diff) . "% weniger als vor 7 Tagen.", 'type' => 'warning'];
            } else {
                $insights[] = ['text' => "Fortschritt bei {$completionRate}%.", 'type' => 'info'];
            }
        }

        // Items completed vs added
        if ($itemsCompleted7d > 0 && $itemsAdded7d > 0) {
            $insights[] = [
                'text' => "{$itemsCompleted7d} Items erledigt, {$itemsAdded7d} neue hinzugefügt (7d).",
                'type' => $itemsCompleted7d >= $itemsAdded7d ? 'success' : 'warning',
            ];
        } elseif ($itemsCompleted7d > 0) {
            $insights[] = ['text' => "{$itemsCompleted7d} Items in 7 Tagen erledigt.", 'type' => 'success'];
        }

        // Estimated remaining
        if ($estimatedDaysRemaining !== null && $healthStatus !== 'completed') {
            $insights[] = [
                'text' => "Geschätzte Restlaufzeit: {$estimatedDaysRemaining} Tage (bei Ø {$velocityDailyAvg} Items/Tag).",
                'type' => 'info',
            ];
        }

        // Billing trend
        $billingDiff = round($billingRate - $agoBillingRate, 1);
        if ($billingRate > 0) {
            if ($billingDiff < 0) {
                $insights[] = ['text' => "Abrechnungsquote bei {$billingRate}% — " . abs($billingDiff) . "% unter Vorwoche.", 'type' => 'warning'];
            } elseif ($billingDiff > 0) {
                $insights[] = ['text' => "Abrechnungsquote bei {$billingRate}% — +{$billingDiff}% gegenüber Vorwoche.", 'type' => 'success'];
            }
        }

        return array_slice($insights, 0, 4);
    }

    #[Computed]
    public function snapshotTrend(): array
    {
        $snapshots = OrganizationEntitySnapshot::where('entity_id', $this->entity->id)
            ->forDateRange(now()->subDays(14), now())
            ->orderBy('snapshot_date')
            ->orderBy('snapshot_period')
            ->get();

        if ($snapshots->isEmpty()) {
            return [];
        }

        $maxItemsTotal = 0;
        $maxMinutes = 0;
        $data = [];

        foreach ($snapshots as $snap) {
            $metrics = $snap->metrics;
            $itemsTotal = $metrics['items_total'] ?? 0;
            $totalMin = $metrics['time_total_minutes'] ?? 0;

            if ($itemsTotal > $maxItemsTotal) $maxItemsTotal = $itemsTotal;
            if ($totalMin > $maxMinutes) $maxMinutes = $totalMin;

            $data[] = [
                'date' => $snap->snapshot_date->format('d.m.'),
                'period' => $snap->snapshot_period,
                'items_total' => $itemsTotal,
                'items_done' => $metrics['items_done'] ?? 0,
                'links_count' => $metrics['links_count'] ?? 0,
                'time_total_minutes' => $totalMin,
                'time_billed_minutes' => $metrics['time_billed_minutes'] ?? 0,
            ];
        }

        return [
            'snapshots' => $data,
            'max_items_total' => $maxItemsTotal,
            'max_minutes' => $maxMinutes,
        ];
    }

    #[Computed]
    public function totalDescendantCount(): int
    {
        return count($this->getDescendantEntityIds($this->entity->id));
    }

    #[Computed]
    public function totalLinkCount(): int
    {
        $ids = array_merge([$this->entity->id], $this->getDescendantEntityIds($this->entity->id));
        return EntityDimensionBridge::totalLinkCount($ids);
    }

    public function loadChildNodes(int $entityId): array
    {
        $entity = OrganizationEntity::findOrFail($entityId);
        $children = $entity->children()
            ->with('type')
            ->get();

        if ($children->isEmpty()) {
            return [];
        }

        // Same sibling sort as the top level: EntityType.sort_order, then name.
        $children = $children->sortBy([
            ['type.sort_order', 'asc'],
            ['name', 'asc'],
        ])->values();

        return $this->buildNodesForEntities($children);
    }

    protected function buildNodesForEntities($entities): array
    {
        $entityIds = $entities->pluck('id')->toArray();

        // 1. Single CTE for all descendants
        $descendantMap = $this->getAllDescendantMap($entityIds);

        // 2. Collect ALL IDs (entities + all descendants) for batch queries
        $allIds = $entityIds;
        foreach ($descendantMap as $descIds) {
            $allIds = array_merge($allIds, $descIds);
        }
        $allIds = array_values(array_unique($allIds));

        // 3. Batch link counts for all IDs in one query
        $allLinkCounts = $this->getEntityLinkCountsForIds($allIds);

        // 4. Resolved links for the entities themselves (not descendants)
        $ownLinksResolved = $this->getEntityLinksForIds($entityIds);

        // 5. Children counts
        $childrenCounts = $this->getChildrenCountsForIds($entityIds);

        // 6. Batch time summaries via EntityTimeResolver
        $resolver = new EntityTimeResolver();
        $cascadedPairs = $resolver->resolveContextPairsBatch($entityIds, $descendantMap);
        $ownPairs = $resolver->resolveContextPairsBatch($entityIds, []); // no descendants
        $cascadedTimeSummaries = $resolver->batchTimeSummaries($cascadedPairs);
        $ownTimeSummaries = $resolver->batchTimeSummaries($ownPairs);

        $nodes = [];
        foreach ($entities as $entity) {
            $descendantIds = $descendantMap[$entity->id] ?? [];

            // Cascaded link counts: own + all descendants (from pre-fetched data)
            $cascadedLinkCounts = $allLinkCounts[$entity->id] ?? [];
            foreach ($descendantIds as $descId) {
                foreach ($allLinkCounts[$descId] ?? [] as $type => $count) {
                    $cascadedLinkCounts[$type] = ($cascadedLinkCounts[$type] ?? 0) + $count;
                }
            }

            $nodes[] = $this->buildNodeData(
                $entity,
                ownLinkCounts: $allLinkCounts[$entity->id] ?? [],
                cascadedLinkCounts: $cascadedLinkCounts,
                ownTime: $ownTimeSummaries[$entity->id] ?? ['total_minutes' => 0, 'billed_minutes' => 0],
                cascadedTime: $cascadedTimeSummaries[$entity->id] ?? ['total_minutes' => 0, 'billed_minutes' => 0],
                childrenCount: $childrenCounts[$entity->id] ?? 0,
                descendantCount: count($descendantIds),
                ownLinks: $ownLinksResolved[$entity->id] ?? [],
            );
        }

        return $nodes;
    }

    protected function buildNodeData(
        OrganizationEntity $entity,
        array $ownLinkCounts,
        array $cascadedLinkCounts,
        array $ownTime,
        array $cascadedTime,
        int $childrenCount,
        int $descendantCount,
        array $ownLinks = [],
    ): array {
        $iconName = null;
        if ($entity->type->icon) {
            $icon = str_replace('heroicons.', '', $entity->type->icon);
            $iconMap = [
                'user-check' => 'user',
                'folder-kanban' => 'folder',
                'briefcase-globe' => 'briefcase',
                'server-cog' => 'server',
                'package-check' => 'archive-box',
                'badge-check' => 'check-badge',
            ];
            $iconName = $iconMap[$icon] ?? $icon;
        }

        $totalLinks = array_sum($cascadedLinkCounts);

        $typeIconSvg = null;
        if ($iconName) {
            try {
                $typeIconSvg = svg('heroicon-o-' . $iconName, 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')->toHtml();
            } catch (\Exception $e) {
                $typeIconSvg = null;
            }
        }

        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'code' => $entity->code,
            'type_name' => $entity->type->name,
            'type_icon' => $iconName,
            'type_icon_svg' => $typeIconSvg,
            'is_active' => $entity->is_active,
            'children_count' => $childrenCount,
            'descendant_count' => $descendantCount,
            'has_children' => $childrenCount > 0,
            'own_link_counts' => $ownLinkCounts,
            'cascaded_link_counts' => $cascadedLinkCounts,
            'total_links' => $totalLinks,
            'own_time' => $ownTime,
            'cascaded_time' => $cascadedTime,
            'own_links_grouped' => $ownLinks,
        ];
    }

    /**
     * Collect all descendant entity IDs recursively (breadth-first).
     */
    protected function getDescendantEntityIds(int $entityId): array
    {
        return $this->getAllDescendantMap([$entityId])[$entityId] ?? [];
    }

    /**
     * Batch-collect descendant entity IDs for multiple roots using a single recursive CTE.
     * Returns: [rootId => [descendantId, ...]]
     */
    protected function getAllDescendantMap(array $rootIds): array
    {
        return resolve(EntityHierarchyService::class)->getAllDescendantMap($rootIds);
    }

    protected function getEntityLinkCountsForIds(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        return EntityDimensionBridge::linkCountsByEntityAndType($entityIds);
    }

    protected function getChildrenCountsForIds(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        return OrganizationEntity::query()
            ->whereIn('parent_entity_id', $entityIds)
            ->select('parent_entity_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('parent_entity_id')
            ->pluck('cnt', 'parent_entity_id')
            ->toArray();
    }

    protected function getTimeSummaryForEntity(OrganizationEntity $entity, EntityTimeResolver $resolver, bool $includeChildren = false): array
    {
        try {
            $query = $resolver->buildTimeEntryQuery($entity, $includeChildren);
            $result = $query->selectRaw('COALESCE(SUM(minutes), 0) as total_minutes, COALESCE(SUM(CASE WHEN is_billed = 1 THEN minutes ELSE 0 END), 0) as billed_minutes')->first();

            return [
                'total_minutes' => (int) ($result?->total_minutes ?? 0),
                'billed_minutes' => (int) ($result?->billed_minutes ?? 0),
            ];
        } catch (\Exception $e) {
            return ['total_minutes' => 0, 'billed_minutes' => 0];
        }
    }

    #[Computed]
    public function rootEntityLinks(): array
    {
        $linksMap = $this->getEntityLinksForIds([$this->entity->id]);
        return $linksMap[$this->entity->id] ?? [];
    }

    /**
     * Resolve entity links for given entity IDs into grouped arrays.
     * Returns: [entity_id => [{type, label, icon, items: [{id, name, status, url}, ...]}, ...]]
     * Groups are sorted by label.
     */
    protected function getEntityLinksForIds(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $morphMap = Relation::morphMap();
        // Reverse map: FQCN -> morph alias (for normalizing DB entries stored as FQCN)
        $reverseMorphMap = array_flip($morphMap);

        $links = EntityDimensionBridge::linksForEntities($entityIds);

        // Normalize linkable_type: convert FQCNs to morph aliases where possible
        $links->each(function ($link) use ($reverseMorphMap) {
            if (isset($reverseMorphMap[$link->linkable_type])) {
                $link->linkable_type = $reverseMorphMap[$link->linkable_type];
            }
        });

        // Filter to resolvable morph types
        $resolvable = $links->filter(function ($link) use ($morphMap) {
            $type = $link->linkable_type;
            return isset($morphMap[$type]) || class_exists($type);
        });

        // Group by linkable_type for batch loading with eager relations
        $linksByType = $resolvable->groupBy('linkable_type');
        $modelsById = [];

        foreach ($linksByType as $morphAlias => $typeLinks) {
            $fqcn = $morphMap[$morphAlias] ?? $morphAlias;
            if (!class_exists($fqcn)) {
                continue;
            }
            $ids = $typeLinks->pluck('linkable_id')->unique()->toArray();
            $query = $fqcn::whereIn('id', $ids);

            // Eager load relations, counts and time sums per type
            // Note: withSum('timeEntries') won't work because context_type stores
            // the FQCN while morphMap uses aliases. Use raw subquery instead.
            $this->applyTypeEagerLoading($query, $morphAlias, $fqcn);

            $models = $query->get()->keyBy('id');
            foreach ($models as $id => $model) {
                $modelsById[$morphAlias . ':' . $id] = $model;
            }
        }

        // Build grouped links per entity
        $byEntityAndType = [];
        foreach ($resolvable as $link) {
            $type = $link->linkable_type;
            $modelKey = $type . ':' . $link->linkable_id;
            $linkable = $modelsById[$modelKey] ?? null;
            if (!$linkable) {
                continue;
            }

            $config = $this->linkTypeConfig[$type] ?? [
                'label' => $type,
                'icon' => 'link',
                'route' => null,
            ];

            $url = null;
            if ($config['route']) {
                try {
                    $url = route($config['route'], $linkable);
                } catch (\Exception $e) {
                    $url = null;
                }
            }

            $metadata = $this->extractLinkMetadata($type, $linkable);

            // Cost-driver links: apply percentage to amount
            $pct = $link->percentage ? (float) $link->percentage : null;
            if ($pct && isset($metadata['amount'])) {
                $metadata['amount'] = round((float) $metadata['amount'] * $pct / 100, 2);
                $metadata['percentage'] = $pct;
            }

            $linkableName = $linkable instanceof \Platform\Core\Contracts\HasDisplayName
                ? ($linkable->getDisplayName() ?? $linkable->name ?? $linkable->title ?? '—')
                : ($linkable->name ?? $linkable->title ?? '—');

            $byEntityAndType[$link->entity_id][$type]['items'][] = array_merge([
                'id' => $link->id,
                'name' => $linkableName,
                'status' => $linkable->status ?? null,
                'url' => $url,
            ], $metadata);
            $byEntityAndType[$link->entity_id][$type]['label'] = $config['label'];
            $byEntityAndType[$link->entity_id][$type]['icon'] = $config['icon'];
            $byEntityAndType[$link->entity_id][$type]['type'] = $type;
        }

        // Convert to sorted array of groups per entity, with aggregated time
        $result = [];
        foreach ($byEntityAndType as $entityId => $types) {
            $groups = array_values($types);
            foreach ($groups as &$group) {
                $group['group_logged_minutes'] = array_sum(array_column($group['items'], 'logged_minutes'));
            }
            unset($group);
            usort($groups, fn($a, $b) => strcmp($a['label'], $b['label']));
            $result[$entityId] = $groups;
        }

        return $result;
    }

    protected function applyTypeEagerLoading($query, string $morphAlias, string $fqcn): void
    {
        $provider = resolve(EntityLinkRegistry::class)->getProvider($morphAlias);
        $provider?->applyEagerLoading($query, $morphAlias, $fqcn);
    }

    protected function extractLinkMetadata(string $type, $linkable): array
    {
        $provider = resolve(EntityLinkRegistry::class)->getProvider($type);
        return $provider?->extractMetadata($type, $linkable) ?? [];
    }

    public function loadEntireTree(): array
    {
        // Load all descendants of the current entity
        $allDescendantIds = $this->getDescendantEntityIds($this->entity->id);
        if (empty($allDescendantIds)) {
            return [];
        }

        // Load all descendant entities with their types
        $allEntities = OrganizationEntity::whereIn('id', $allDescendantIds)
            ->with('type')
            ->orderBy('name')
            ->get();

        $byParent = $allEntities->groupBy('parent_entity_id');

        // Build nodes for each parent group using batch approach
        $result = [];
        foreach ($byParent as $parentId => $children) {
            $result[$parentId] = $this->buildNodesForEntities($children);
        }

        return $result;
    }

    // ── Skills Tab ─────────────────────────────────────────────

    #[Computed]
    public function isPersonEntity(): bool
    {
        return $this->entity->type?->code === 'person';
    }

    #[Computed]
    public function entitySkills()
    {
        return $this->entity->skills()->get();
    }

    #[Computed]
    public function entitySoftSkills()
    {
        return $this->entity->softSkills()->get();
    }

    #[Computed]
    public function availablePersonSkills()
    {
        if (strlen($this->personSkillSearch) < 1) {
            return collect();
        }

        $existingIds = $this->entity->skills()->pluck('organization_skills.id')->toArray();

        return OrganizationSkill::forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->where('name', 'like', '%' . $this->personSkillSearch . '%')
            ->whereNotIn('id', $existingIds)
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function availablePersonSoftSkills()
    {
        if (strlen($this->personSoftSkillSearch) < 1) {
            return collect();
        }

        $existingIds = $this->entity->softSkills()->pluck('organization_soft_skills.id')->toArray();

        return OrganizationSoftSkill::forTeam(auth()->user()->currentTeam->id)
            ->active()
            ->where('name', 'like', '%' . $this->personSoftSkillSearch . '%')
            ->whereNotIn('id', $existingIds)
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    public function assignPersonSkill(int $skillId, string $level = 'basic'): void
    {
        $this->entity->skills()->syncWithoutDetaching([
            $skillId => ['level' => $level],
        ]);

        $this->personSkillSearch = '';
        unset($this->entitySkills, $this->availablePersonSkills);
        $this->dispatch('toast', message: 'Skill zugeordnet');
    }

    public function removePersonSkill(int $skillId): void
    {
        $this->entity->skills()->detach($skillId);
        unset($this->entitySkills);
        $this->dispatch('toast', message: 'Skill entfernt');
    }

    public function updatePersonSkillLevel(int $skillId, string $level): void
    {
        $this->entity->skills()->updateExistingPivot($skillId, ['level' => $level]);
        unset($this->entitySkills);
        $this->dispatch('toast', message: 'Level aktualisiert');
    }

    public function assignPersonSoftSkill(int $softSkillId, string $level = 'basic'): void
    {
        $this->entity->softSkills()->syncWithoutDetaching([
            $softSkillId => ['level' => $level],
        ]);

        $this->personSoftSkillSearch = '';
        unset($this->entitySoftSkills, $this->availablePersonSoftSkills);
        $this->dispatch('toast', message: 'Soft Skill zugeordnet');
    }

    public function removePersonSoftSkill(int $softSkillId): void
    {
        $this->entity->softSkills()->detach($softSkillId);
        unset($this->entitySoftSkills);
        $this->dispatch('toast', message: 'Soft Skill entfernt');
    }

    public function updatePersonSoftSkillLevel(int $softSkillId, string $level): void
    {
        $this->entity->softSkills()->updateExistingPivot($softSkillId, ['level' => $level]);
        unset($this->entitySoftSkills);
        $this->dispatch('toast', message: 'Level aktualisiert');
    }

    // ── Signals Tab ────────────────────────────────────────────

    #[Computed]
    public function entitySignals(): \Illuminate\Support\Collection
    {
        $teamId = auth()->user()->currentTeam->id;
        $activeEntity = \Platform\Organization\Services\PerspectiveService::getActiveEntity($teamId, auth()->id());

        $query = OrganizationSignal::query()
            ->where('entity_id', $this->entity->id)
            ->with([
                'definition:id,name,pattern_type',
                'resolvedByUser:id,name',
                'perspectiveEntity:id,name',
                'currentOwner:id,name',
                'createdByAgent:id,name',
            ])
            ->orderByRaw("FIELD(status, 'open', 'acknowledged', 'resolved', 'dismissed')")
            ->orderByDesc('created_at');

        // Perspektive-sensitive Anzeige: nur Signale aus aktiver Sicht (oder NULL = legacy / global).
        if ($activeEntity) {
            $query->forPerspective($activeEntity->id);
        }

        if ($this->signalStatusFilter) {
            $query->where('status', $this->signalStatusFilter);
        }

        return $query->get();
    }

    public function acknowledgeSignal(int $signalId): void
    {
        $signal = OrganizationSignal::where('id', $signalId)
            ->where('entity_id', $this->entity->id)
            ->firstOrFail();
        $signal->update([
            'status' => 'acknowledged',
            'acknowledged_at' => $signal->acknowledged_at ?? now(),
        ]);
        unset($this->entitySignals);
        session()->flash('message', 'Signal bestaetigt.');
    }

    public function resolveSignal(int $signalId): void
    {
        $signal = OrganizationSignal::where('id', $signalId)
            ->where('entity_id', $this->entity->id)
            ->firstOrFail();
        $signal->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'acknowledged_at' => $signal->acknowledged_at ?? now(),
        ]);
        unset($this->entitySignals);
        session()->flash('message', 'Signal geloest.');
    }

    public function dismissSignal(int $signalId): void
    {
        $signal = OrganizationSignal::where('id', $signalId)
            ->where('entity_id', $this->entity->id)
            ->firstOrFail();
        $signal->update([
            'status' => 'dismissed',
            'acknowledged_at' => $signal->acknowledged_at ?? now(),
        ]);
        unset($this->entitySignals);
        session()->flash('message', 'Signal verworfen.');
    }

    /**
     * Live-Puls fuer den Berichte-Tab: ruft den entity_pulse-Collector mit
     * pulse_full-Recipe (oder Ad-hoc) auf und gruppiert die Facts nach Nature.
     * Rendering geschieht als Ampel + Aufmerksamkeit + Bewegung + Zustand
     * direkt in Blade — kein LLM. Fix-Regeln fuer Ampel; wenn's mal
     * kalibrierbar werden soll, wandert es in eine Recipe.
     */
    #[Computed]
    public function entityPulseSnapshot(): ?array
    {
        try {
            $registry = app(\Platform\Core\Verbalization\SubjectCollector\SubjectCollectorRegistry::class);
            $collector = $registry->resolve('entity_pulse');
            if (! $collector) {
                return null;
            }

            $teamId = auth()->user()?->currentTeam?->id;
            $recipeModel = VerbalizationRecipe::query()
                ->where('key', 'pulse_full')
                ->where(function ($q) use ($teamId) {
                    $q->whereNull('team_id');
                    if ($teamId) {
                        $q->orWhere('team_id', $teamId);
                    }
                })
                ->orderByDesc('team_id') // team-spezifisch schlaegt global
                ->first();

            $recipe = $recipeModel
                ? \Platform\Core\Verbalization\Recipe\CollectionRecipe::fromModel($recipeModel)
                : null;

            $windowDays = $recipe?->sinceWindowDays() ?: 7;
            $since = new \DateTimeImmutable('-' . $windowDays . ' days');

            $subject = $collector->collectState((int) $this->entity->id, $recipe, $since);

            $bucketDeriv = [];
            $bucketMove = [];
            $bucketState = [];
            foreach ($subject->facts as $f) {
                match ($f->nature) {
                    \Platform\Core\Verbalization\Enums\FactNature::DERIVATION => $bucketDeriv[] = $f,
                    \Platform\Core\Verbalization\Enums\FactNature::MOVEMENT => $bucketMove[] = $f,
                    default => $bucketState[] = $f,
                };
            }

            $prioSort = fn ($a, $b) => $a->priority->value <=> $b->priority->value;
            usort($bucketDeriv, $prioSort);
            usort($bucketMove, $prioSort);
            usort($bucketState, $prioSort);

            // Ampel: fix, heuristisch
            $signal = 'green';
            $signalReason = 'Alles im gruenen Bereich.';
            foreach ($bucketDeriv as $f) {
                if (str_contains(strtolower($f->text), 'algedonic')) {
                    $signal = 'red';
                    $signalReason = 'Algedonic-Signal(e) aktiv — sofortige Aufmerksamkeit.';
                    break;
                }
            }
            if ($signal === 'green' && ! empty($bucketDeriv)) {
                $signal = 'yellow';
                $signalReason = 'Aufmerksamkeit erforderlich.';
            }

            // Nur CORE + QUALIFYING States zeigen — Context waere zu viel Rauschen
            $coreStates = array_values(array_filter(
                $bucketState,
                fn ($f) => $f->priority !== \Platform\Core\Verbalization\Enums\FactPriority::CONTEXT,
            ));

            return [
                'signal' => $signal,
                'signal_reason' => $signalReason,
                'window_days' => $windowDays,
                'computed_at' => now()->format('H:i'),
                'derivations' => array_map(fn ($f) => ['text' => $f->text, 'priority' => $f->priority->value], array_slice($bucketDeriv, 0, 8)),
                'movements' => array_map(fn ($f) => ['text' => $f->text, 'priority' => $f->priority->value], array_slice($bucketMove, 0, 12)),
                'states' => array_map(fn ($f) => ['text' => $f->text, 'priority' => $f->priority->value], array_slice($coreStates, 0, 12)),
                'total_facts' => count($subject->facts),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Toggle im UI: ob der Berichte-Tab nur Feeds am direkten Knoten zeigt oder
     * auch Feeds aus dem gesamten Sub-Baum. Default: inkl. Sub-Baum, weil an
     * Root-Entities (z.B. BHG.DIGITAL) sonst fast nichts sichtbar waere — die
     * meisten Feeds zeigen typischerweise auf Sub-Ebenen (Engagements, Projekte).
     */
    public bool $includeDescendantsInReports = true;

    public function toggleReportsScope(): void
    {
        $this->includeDescendantsInReports = ! $this->includeDescendantsInReports;
        unset($this->verbalizationFeeds);
    }

    /**
     * Verbalization-Feeds, die diesen Knoten betreffen. Drei Faelle:
     *   (a) mode=single mit id ∈ Scope UND subject_type ∈ {entity_pulse, organization_signals}
     *   (b) mode=entity mit entity_id ∈ Scope
     *   (c) mode=single auf ein Objekt (Projekt, Board, ...), das per DimensionLink
     *       an einer Entity im Scope haengt
     *
     * "Scope" = $entityId (nur direkt) oder $entityId + alle Descendants (rekursiv).
     */
    #[Computed]
    public function verbalizationFeeds(): array
    {
        $entityId = (int) $this->entity->id;
        $scope = $this->includeDescendantsInReports
            ? $this->collectEntityScopeForReports($entityId)
            : [$entityId];

        // Fall (c): pro linkable_type die IDs von Objekten, die per DimensionLink
        // an einer Scope-Entity haengen. Feed-Query matcht subject_type +
        // subject_selector.id gegen diese Mengen.
        $linkableIdsByType = $this->linkableIdsByTypeForScope($scope);

        try {
            $feeds = VerbalizationFeed::query()
                ->where(function ($q) use ($scope, $linkableIdsByType) {
                    // (a) mode=single + entity-basierte Types + ID im Scope
                    $q->where(function ($qq) use ($scope) {
                        $qq->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(subject_selector, '$.mode')) = 'single'")
                            ->whereIn(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(subject_selector, '$.id')) AS UNSIGNED)"), $scope)
                            ->whereIn('subject_type', ['entity_pulse', 'organization_signals']);
                    });
                    // (b) mode=entity mit entity_id im Scope
                    $q->orWhere(function ($qq) use ($scope) {
                        $qq->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(subject_selector, '$.mode')) = 'entity'")
                            ->whereIn(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(subject_selector, '$.entity_id')) AS UNSIGNED)"), $scope);
                    });
                    // (c) mode=single auf ein Objekt aus dem Sub-Baum
                    foreach ($linkableIdsByType as $type => $ids) {
                        if (empty($ids)) {
                            continue;
                        }
                        // Aliase 'project' und 'planner_project' beide akzeptieren.
                        $candidateTypes = ($type === 'project' || $type === 'planner_project')
                            ? ['project', 'planner_project']
                            : [$type];
                        $q->orWhere(function ($qq) use ($candidateTypes, $ids) {
                            $qq->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(subject_selector, '$.mode')) = 'single'")
                                ->whereIn('subject_type', $candidateTypes)
                                ->whereIn(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(subject_selector, '$.id')) AS UNSIGNED)"), $ids);
                        });
                    }
                })
                ->orderByDesc('last_refreshed_at')
                ->orderByDesc('id')
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $availableChannelTypes = $this->availableChannelTypes();

        return $feeds->map(function ($feed) use ($baseUrl, $availableChannelTypes) {
            $latest = VerbalizationOutput::query()
                ->where('feed_id', $feed->id)
                ->orderByDesc('created_at')
                ->first();

            $outputsCount = VerbalizationOutput::where('feed_id', $feed->id)->count();
            $recipesMap = $feed->recipes ?? [];
            $recipeDetails = $this->hydrateRecipes($recipesMap, $feed->team_id);
            $selector = $feed->subject_selector ?? [];

            // Aktive Kanaele des Feeds.
            $channels = VerbalizationChannel::where('verbalization_feed_id', $feed->id)
                ->orderByDesc('is_active')
                ->orderBy('type')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'type' => $c->type,
                    'is_active' => (bool) $c->is_active,
                    'config' => $c->config,
                    'last_delivered_at' => $c->last_delivered_at?->format('d.m.Y H:i'),
                    'summary' => $this->summarizeChannelConfig($c->type, $c->config ?? []),
                ])->all();
            $activeChannelTypes = collect($channels)->pluck('type')->unique()->all();

            return [
                'id' => $feed->id,
                'uuid' => $feed->uuid,
                'title' => $feed->title,
                'description' => $feed->description,
                'subject_type' => $feed->subject_type,
                'subject_selector' => $selector,
                'subject_selector_mode' => $selector['mode'] ?? null,
                'subject_selector_descend' => $selector['descend'] ?? false,
                'refresh_strategy' => $feed->refresh_strategy,
                'item_strategy' => $feed->item_strategy ?? 'history',
                'retention_items' => (int) ($feed->retention_items ?? 0),
                'access' => $feed->access ?? 'team',
                'is_active' => (bool) $feed->is_active,
                'last_refreshed_at' => $feed->last_refreshed_at?->format('d.m.Y H:i'),
                'outputs_count' => $outputsCount,
                'recipes' => array_values(array_map(fn ($v) => $v, $recipesMap)),
                'recipe_details' => $recipeDetails,
                'channels' => $channels,
                'active_channel_types' => $activeChannelTypes,
                'available_channel_types' => $availableChannelTypes,
                'feed_url' => $baseUrl . '/feed/' . $feed->uuid . '.xml',
                'latest' => $latest ? [
                    'id' => $latest->id,
                    'created_at' => $latest->created_at?->format('d.m.Y H:i'),
                    'model' => $latest->llm_model,
                    'provider' => $latest->llm_provider,
                    'recipe_key' => $latest->recipe_key,
                    'prose' => $latest->prose,
                    'preview' => $this->prosePreview((string) ($latest->prose ?? ''), 240),
                ] : null,
            ];
        })->all();
    }

    /**
     * @param  array<string,string>  $recipesMap  subject_type → recipe_key
     * @return array<string,array>  recipe_key → aufbereitete Metadaten
     */
    protected function hydrateRecipes(array $recipesMap, ?int $teamId): array
    {
        if (empty($recipesMap)) {
            return [];
        }
        $keys = array_values($recipesMap);
        $recipes = VerbalizationRecipe::query()
            ->whereIn('key', $keys)
            ->where(function ($q) use ($teamId) {
                $q->whereNull('team_id');
                if ($teamId) {
                    $q->orWhere('team_id', $teamId);
                }
            })
            ->get()
            ->keyBy('key');

        $out = [];
        foreach ($recipes as $key => $r) {
            $sources = $r->sources ?? [];
            $out[$key] = [
                'key' => $r->key,
                'name' => $r->name,
                'description' => $r->description,
                'include_natures' => $r->include_natures,
                'llm_provider' => $r->llm['provider'] ?? null,
                'llm_model' => $r->llm['model'] ?? null,
                'style' => $r->style ?? [],
                'guards' => $r->guards,
                'freshness_requirement' => $r->freshness_requirement,
                'descend' => $sources['descend'] ?? false,
                'sources' => $sources,
                'source_flags' => $this->summarizeRecipeSources($sources),
            ];
        }
        return $out;
    }

    /**
     * Verdichtet die Sources-JSON auf Flag/Anzeige-Struktur — pro bekannter Source:
     *   [key => ['on' => bool, 'label' => string, 'detail' => string|null]]
     */
    protected function summarizeRecipeSources(array $sources): array
    {
        $flags = [];
        $known = [
            'signals' => 'Signale',
            'planner_projects' => 'Planner-Projekte',
            'entity_link_providers' => 'Registry-Metriken',
            'verbose' => 'Verbose',
            // Signal-Sources (organization_signals recipe):
            'signal_load' => 'Signal-Last',
            'vsm_distribution' => 'VSM-Verteilung',
            'signal_headlines' => 'Headlines',
            'new_signals' => 'Neue Signale',
            'resolved_signals' => 'Resolved',
            'aggregation_flow' => 'Eskaliert',
            'algedonic_alert' => 'Algedonic',
            'vsm_focus' => 'S4/S5-Fokus',
            // Planner-Sources:
            'description' => 'Beschreibung',
            'lifetime' => 'Alter',
            'core_health' => 'Health',
            'canvas' => 'Canvas',
            'movement_summary' => 'Movement',
            'scope_fulfillment' => 'Scope',
            'ball_position' => 'Ball-Position',
            'open_by_owner' => 'Open per Owner',
            'edges_owner' => 'Owner',
            'edges_org_anchors' => 'Org-Anker',
            'edges_team' => 'Team',
            'termine' => 'Termine',
            'confidence' => 'Konfidenz',
            'frogs' => 'Froesche',
            'slots' => 'Slots',
            'people' => 'People',
            'budget' => 'Budget',
        ];
        foreach ($known as $k => $label) {
            if (! array_key_exists($k, $sources)) {
                $flags[$k] = ['on' => false, 'label' => $label, 'detail' => null];
                continue;
            }
            $v = $sources[$k];
            if (is_bool($v)) {
                $flags[$k] = ['on' => $v, 'label' => $label, 'detail' => null];
                continue;
            }
            if (is_array($v)) {
                $on = (bool) ($v['enabled'] ?? true);
                $detailParts = [];
                if (isset($v['top_n'])) $detailParts[] = 'top ' . $v['top_n'];
                if (! empty($v['skip_if_no_movement'])) $detailParts[] = 'skip idle';
                if (isset($v['include']) && ! empty($v['include'])) {
                    $detailParts[] = 'nur ' . implode('/', (array) $v['include']);
                }
                if (isset($v['exclude']) && ! empty($v['exclude'])) {
                    $detailParts[] = 'ohne ' . implode('/', (array) $v['exclude']);
                }
                if (! empty($v['skip_zero'])) $detailParts[] = 'skip 0';
                $flags[$k] = [
                    'on' => $on,
                    'label' => $label,
                    'detail' => $detailParts ? implode(', ', $detailParts) : null,
                ];
                continue;
            }
            $flags[$k] = ['on' => (bool) $v, 'label' => $label, 'detail' => null];
        }
        return $flags;
    }

    protected function summarizeChannelConfig(string $type, array $config): ?string
    {
        return match ($type) {
            'obsidian' => (function () use ($config) {
                $parts = [];
                if (isset($config['vault_id'])) $parts[] = 'Vault #' . $config['vault_id'];
                if (! empty($config['folder'])) $parts[] = $config['folder'];
                return $parts ? implode(' · ', $parts) : null;
            })(),
            'email' => ! empty($config['to']) ? implode(', ', (array) $config['to']) : null,
            'slack' => $config['channel'] ?? null,
            'webhook' => $config['url'] ?? null,
            'rss', 'web' => null,
            default => null,
        };
    }

    /**
     * @return array<string,array{label:string, icon:string}>
     */
    protected function availableChannelTypes(): array
    {
        // Kompakter Katalog. Registrierte Renderer werden hier gespiegelt — falls
        // die Registry existiert wird gefiltert, sonst Fallback auf statischen Katalog.
        $catalog = [
            'rss'      => ['label' => 'RSS',      'icon' => 'rss'],
            'web'      => ['label' => 'Web',      'icon' => 'globe-alt'],
            'obsidian' => ['label' => 'Obsidian', 'icon' => 'document-text'],
            'email'    => ['label' => 'Email',    'icon' => 'envelope'],
            'pdf'      => ['label' => 'PDF',      'icon' => 'document'],
            'slack'    => ['label' => 'Slack',    'icon' => 'chat-bubble-left'],
            'webhook'  => ['label' => 'Webhook',  'icon' => 'link'],
        ];
        try {
            $registry = app(\Platform\Core\Verbalization\Channel\ChannelRendererRegistry::class);
            $registered = array_keys($registry->all());
            $out = [];
            foreach ($catalog as $type => $meta) {
                $out[$type] = $meta + ['registered' => in_array($type, $registered, true)];
            }
            return $out;
        } catch (\Throwable $e) {
            return array_map(fn ($m) => $m + ['registered' => false], $catalog);
        }
    }

    /**
     * Vollstaendige Prosa-Historie eines Feeds (letzte N Outputs) — fuer das Modal.
     */
    public function verbalizationOutputsFor(int $feedId, int $limit = 5): array
    {
        return VerbalizationOutput::query()
            ->where('feed_id', $feedId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'created_at' => $o->created_at?->format('d.m.Y H:i'),
                'model' => $o->llm_model,
                'recipe_key' => $o->recipe_key,
                'prose' => $o->prose,
            ])
            ->all();
    }

    protected function prosePreview(string $prose, int $chars = 240): string
    {
        // Markdown-Rauschen fuer Preview grob entfernen.
        $plain = preg_replace('/[*_#`>]+/', '', $prose) ?? $prose;
        $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;
        $plain = trim($plain);
        if (mb_strlen($plain) <= $chars) {
            return $plain;
        }
        return mb_substr($plain, 0, $chars - 1) . '…';
    }

    /**
     * Root + alle Descendants der Entity ueber parent_entity_id, breadth-first.
     *
     * @return int[]
     */
    protected function collectEntityScopeForReports(int $rootId): array
    {
        try {
            $visited = [$rootId => true];
            $result = [$rootId];
            $queue = [$rootId];
            while (! empty($queue)) {
                $id = array_shift($queue);
                $children = DB::table('organization_entities')
                    ->where('parent_entity_id', $id)
                    ->pluck('id')
                    ->all();
                foreach ($children as $cid) {
                    $cid = (int) $cid;
                    if (isset($visited[$cid])) {
                        continue;
                    }
                    $visited[$cid] = true;
                    $result[] = $cid;
                    $queue[] = $cid;
                }
            }
            return $result;
        } catch (\Throwable $e) {
            return [$rootId];
        }
    }

    /**
     * @param  int[] $entityIds
     * @return array<string, int[]>  linkable_type → [linkable_ids]
     */
    protected function linkableIdsByTypeForScope(array $entityIds): array
    {
        if (empty($entityIds)
            || ! \Schema::hasTable('organization_dimension_links')
            || ! \Schema::hasTable('organization_dimension_values')) {
            return [];
        }
        try {
            $rows = DB::table('organization_dimension_links as l')
                ->join('organization_dimension_values as v', 'v.id', '=', 'l.dimension_value_id')
                ->whereIn(DB::raw("CAST(JSON_UNQUOTE(JSON_EXTRACT(v.metadata, '$.source_entity_id')) AS UNSIGNED)"), $entityIds)
                ->select('l.linkable_type', 'l.linkable_id')
                ->distinct()
                ->get();
            $out = [];
            foreach ($rows as $row) {
                $out[$row->linkable_type][] = (int) $row->linkable_id;
            }
            foreach ($out as $type => $ids) {
                $out[$type] = array_values(array_unique($ids));
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function render()
    {
        return view('organization::livewire.entity.show')
            ->layout('platform::layouts.app');
    }
}
