<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityHierarchy;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Organization\Models\OrganizationEntityType;
use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Organization\Models\OrganizationVsmFunction;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationType;
use Platform\Organization\Models\OrganizationEntityRelationshipInterlink;
use Platform\Organization\Models\OrganizationInterlink;
use Platform\Core\Models\Team;
use Platform\Core\Enums\TeamRole;
use Platform\Organization\Services\EntityTimeResolver;
use Platform\Organization\Services\EntityLinkRegistry;
use Platform\Organization\Services\EntityHierarchyService;
use Platform\Organization\Services\EntityHierarchyResolver;
use Platform\Organization\Services\PerspectiveService;
use Platform\Organization\Services\SnapshotMovementService;
use Platform\Organization\Services\DimensionRadarService;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Models\OrganizationPerspective;
use Platform\Organization\Models\OrganizationSkill;
use Platform\Organization\Models\OrganizationSoftSkill;
use Platform\Organization\Models\OrganizationSignal;
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

    // Skills tab
    public string $personSkillSearch = '';
    public string $personSoftSkillSearch = '';

    // Signals tab
    public string $signalStatusFilter = '';

    #[On('perspective-switched')]
    public function onPerspectiveSwitched(): void
    {
        unset(
            $this->treeNodes,
            $this->totalDescendantCount,
            $this->totalLinkCount,
        );
    }

    protected function getActivePerspective(): OrganizationPerspective
    {
        $user = auth()->user();
        return PerspectiveService::getActive($user->currentTeam->id, $user->id);
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
            'entityLinks',
            'relationsFrom.toEntity.type',
            'relationsFrom.relationType',
            'relationsTo.fromEntity.type',
            'relationsTo.relationType'
        ]);
        $this->loadForm();
    }

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

        try {
            $perspective = $this->getActivePerspective();
            $resolver = resolve(EntityHierarchyResolver::class);

            // If parent changed in a non-default perspective, update hierarchy table instead of entity column
            $parentChanged = ($this->form['parent_entity_id'] ?? null) != $this->entity->parent_entity_id;
            if ($parentChanged && !$resolver->isDefaultHierarchy($perspective)) {
                $newParentId = $this->form['parent_entity_id'] ?: null;

                if ($newParentId !== null) {
                    $resolver->validateNoCircularHierarchy($this->entity->id, (int) $newParentId, $perspective);
                }

                OrganizationEntityHierarchy::updateOrCreate(
                    [
                        'perspective_id' => $perspective->id,
                        'entity_id' => $this->entity->id,
                    ],
                    [
                        'parent_entity_id' => $newParentId,
                        'team_id' => $this->entity->team_id,
                    ]
                );

                // Remove parent_entity_id from form so entity column stays unchanged
                $formWithoutParent = $this->form;
                unset($formWithoutParent['parent_entity_id']);
                $this->entity->update($formWithoutParent);
            } else {
                $this->entity->update($this->form);
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
        return $this->form['name'] !== $this->entity->name ||
               $this->form['code'] !== $this->entity->code ||
               $this->form['description'] !== $this->entity->description ||
               $this->form['entity_type_id'] != $this->entity->entity_type_id ||
               $this->form['parent_entity_id'] != $this->entity->parent_entity_id ||
               $this->form['linked_user_id'] != $this->entity->linked_user_id ||
               $this->form['is_active'] !== $this->entity->is_active;
    }

    public function getEntityTypesProperty()
    {
        return OrganizationEntityType::active()
            ->ordered()
            ->with('group')
            ->get()
            ->groupBy('group.name');
    }

    public function getAvailableVsmFunctionsProperty()
    {
        return OrganizationVsmFunction::getForEntityWithHierarchy(
            auth()->user()->currentTeam->id,
            $this->entity->id
        );
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
            'package-check' => 'package',
            'badge-check' => 'badge',
            'target' => 'viewfinder-circle',
        ];

        $svgs = [];
        foreach ($this->linkTypeConfig as $type => $config) {
            $icon = $iconMap[$config['icon']] ?? $config['icon'];
            $svgs[$type] = svg('heroicon-o-' . $icon, 'w-4 h-4 text-[var(--ui-muted)]')->toHtml();
        }
        return $svgs;
    }

    #[Computed]
    public function treeNodes(): array
    {
        $perspective = $this->getActivePerspective();
        $resolver = resolve(EntityHierarchyResolver::class);

        if (!$resolver->isDefaultHierarchy($perspective)) {
            $childMap = $resolver->getChildMap($perspective, $this->entity->team_id);
            $childIds = $childMap[$this->entity->id] ?? [];

            if (empty($childIds)) {
                return [];
            }

            $children = OrganizationEntity::whereIn('id', $childIds)
                ->with('type')
                ->orderBy('name')
                ->get();
        } else {
            $children = $this->entity->children()
                ->with('type')
                ->orderBy('name')
                ->get();
        }

        if ($children->isEmpty()) {
            return [];
        }

        return $this->buildNodesForEntities($children);
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
        $perspective = $this->getActivePerspective();
        $resolver = resolve(EntityHierarchyResolver::class);

        if (!$resolver->isDefaultHierarchy($perspective)) {
            $childMap = $resolver->getChildMap($perspective, $this->entity->team_id);
            $childIds = $childMap[$entityId] ?? [];

            if (empty($childIds)) {
                return [];
            }

            $children = OrganizationEntity::whereIn('id', $childIds)
                ->with('type')
                ->orderBy('name')
                ->get();
        } else {
            $entity = OrganizationEntity::findOrFail($entityId);
            $children = $entity->children()
                ->with('type')
                ->orderBy('name')
                ->get();
        }

        if ($children->isEmpty()) {
            return [];
        }

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
        $perspective = $this->getActivePerspective();
        return resolve(EntityHierarchyService::class)->getAllDescendantMap($rootIds, $perspective);
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

        $perspective = $this->getActivePerspective();
        $resolver = resolve(EntityHierarchyResolver::class);

        if (!$resolver->isDefaultHierarchy($perspective)) {
            return OrganizationEntityHierarchy::query()
                ->where('perspective_id', $perspective->id)
                ->whereIn('parent_entity_id', $entityIds)
                ->select('parent_entity_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('parent_entity_id')
                ->pluck('cnt', 'parent_entity_id')
                ->toArray();
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

            $byEntityAndType[$link->entity_id][$type]['items'][] = array_merge([
                'id' => $link->id,
                'name' => $linkable->name ?? $linkable->title ?? '—',
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

        // Group by parent - perspective-aware
        $perspective = $this->getActivePerspective();
        $resolver = resolve(EntityHierarchyResolver::class);

        if (!$resolver->isDefaultHierarchy($perspective)) {
            $parentMap = $resolver->getParentMap($perspective, $this->entity->team_id);
            $byParent = $allEntities->groupBy(fn ($e) => $parentMap[$e->id] ?? 'root');
        } else {
            $byParent = $allEntities->groupBy('parent_entity_id');
        }

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
        $query = OrganizationSignal::query()
            ->where('entity_id', $this->entity->id)
            ->with(['definition:id,name,pattern_type', 'resolvedByUser:id,name'])
            ->orderByRaw("FIELD(status, 'open', 'acknowledged', 'resolved', 'dismissed')")
            ->orderByDesc('created_at');

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
        $signal->update(['status' => 'acknowledged']);
        unset($this->entitySignals);
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
        ]);
        unset($this->entitySignals);
    }

    public function dismissSignal(int $signalId): void
    {
        $signal = OrganizationSignal::where('id', $signalId)
            ->where('entity_id', $this->entity->id)
            ->firstOrFail();
        $signal->update(['status' => 'dismissed']);
        unset($this->entitySignals);
    }

    public function render()
    {
        return view('organization::livewire.entity.show')
            ->layout('platform::layouts.app');
    }
}
