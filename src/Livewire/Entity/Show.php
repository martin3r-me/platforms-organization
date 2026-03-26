<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationEntityType;
use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Organization\Models\OrganizationVsmSystem;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationVsmFunction;
use Platform\Organization\Models\OrganizationContext;
use Platform\Core\Models\Team;
use Platform\Core\Enums\TeamRole;
use Platform\Organization\Services\EntityTimeResolver;
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

    public array $linkTypeConfig = [
        'project' => ['label' => 'Projekte', 'icon' => 'folder', 'route' => 'planner.projects.show'],
        'planner_task' => ['label' => 'Aufgaben', 'icon' => 'clipboard-document-check', 'route' => null],
        'helpdesk_ticket' => ['label' => 'Tickets', 'icon' => 'ticket', 'route' => null],
        'hcm_employee' => ['label' => 'Mitarbeiter', 'icon' => 'user', 'route' => null],
        'rec_applicant' => ['label' => 'Bewerber', 'icon' => 'user-plus', 'route' => null],
        'rec_position' => ['label' => 'Positionen', 'icon' => 'briefcase', 'route' => null],
        'sheets_spreadsheet' => ['label' => 'Spreadsheets', 'icon' => 'table-cells', 'route' => null],
        'canvas' => ['label' => 'Canvas', 'icon' => 'squares-2x2', 'route' => null],
        'bmc_canvas' => ['label' => 'BMC', 'icon' => 'squares-2x2', 'route' => null],
        'pc_canvas' => ['label' => 'Project Canvas', 'icon' => 'clipboard-document-list', 'route' => null],
        'notes_note' => ['label' => 'Notizen', 'icon' => 'document-text', 'route' => null],
        'slides_presentation' => ['label' => 'Präsentationen', 'icon' => 'presentation-chart-bar', 'route' => null],
        'okr' => ['label' => 'OKR', 'icon' => 'chart-bar', 'route' => null],
    ];

    public function mount(OrganizationEntity $entity)
    {
        $this->entity = $entity->load([
            'type.group',
            'vsmSystem',
            'costCenter',
            'parent',
            'children.type',
            'children.children',
            'team',
            'user',
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
            'vsm_system_id' => $this->entity->vsm_system_id,
            'cost_center_id' => $this->entity->cost_center_id,
            'parent_entity_id' => $this->entity->parent_entity_id,
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
            'form.vsm_system_id' => 'nullable|exists:organization_vsm_systems,id',
            'form.cost_center_id' => 'nullable|exists:organization_cost_centers,id',
            'form.parent_entity_id' => 'nullable|exists:organization_entities,id',
            'form.is_active' => 'boolean',
        ]);

        try {
            $this->entity->update($this->form);
            $this->loadForm(); // Reload form to reset dirty state
            
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
               $this->form['vsm_system_id'] != $this->entity->vsm_system_id ||
               $this->form['cost_center_id'] != $this->entity->cost_center_id ||
               $this->form['parent_entity_id'] != $this->entity->parent_entity_id ||
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

    public function getVsmSystemsProperty()
    {
        return OrganizationVsmSystem::active()
            ->ordered()
            ->get();
    }

    public function getCostCentersProperty()
    {
        return OrganizationCostCenter::active()
            ->where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('name')
            ->get();
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

            // Verlinke das Team direkt mit der Entität
            OrganizationContext::updateOrCreate(
                [
                    'contextable_type' => Team::class,
                    'contextable_id' => $team->id,
                    'organization_entity_id' => $this->entity->id,
                ],
                [
                    'team_id' => $user->currentTeamRelation?->id,
                    'is_active' => true,
                ]
            );

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
    public function linkTypeIconSvgs(): array
    {
        $svgs = [];
        foreach ($this->linkTypeConfig as $type => $config) {
            $svgs[$type] = svg('heroicon-o-' . $config['icon'], 'w-4 h-4 text-[var(--ui-muted)]')->toHtml();
        }
        return $svgs;
    }

    #[Computed]
    public function treeNodes(): array
    {
        $children = $this->entity->children()
            ->with('type')
            ->orderBy('name')
            ->get();

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
    public function totalDescendantCount(): int
    {
        return count($this->getDescendantEntityIds($this->entity->id));
    }

    #[Computed]
    public function totalLinkCount(): int
    {
        $ids = array_merge([$this->entity->id], $this->getDescendantEntityIds($this->entity->id));
        return OrganizationEntityLink::whereIn('entity_id', $ids)->count();
    }

    public function loadChildNodes(int $entityId): array
    {
        $entity = OrganizationEntity::findOrFail($entityId);
        $children = $entity->children()
            ->with('type')
            ->orderBy('name')
            ->get();

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
        if (empty($rootIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($rootIds), '?'));
        $rows = DB::select("
            WITH RECURSIVE entity_tree AS (
                SELECT id, parent_entity_id, parent_entity_id as root_id
                FROM organization_entities
                WHERE parent_entity_id IN ({$placeholders})
                UNION ALL
                SELECT e.id, e.parent_entity_id, et.root_id
                FROM organization_entities e
                INNER JOIN entity_tree et ON e.parent_entity_id = et.id
            )
            SELECT root_id, id FROM entity_tree
        ", $rootIds);

        $result = array_fill_keys($rootIds, []);
        foreach ($rows as $row) {
            $result[$row->root_id][] = $row->id;
        }
        return $result;
    }

    protected function getEntityLinkCountsForIds(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $rows = OrganizationEntityLink::query()
            ->whereIn('entity_id', $entityIds)
            ->select('entity_id', 'linkable_type', DB::raw('COUNT(*) as cnt'))
            ->groupBy('entity_id', 'linkable_type')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->entity_id][$row->linkable_type] = $row->cnt;
        }

        return $result;
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

        $links = OrganizationEntityLink::whereIn('entity_id', $entityIds)->get();

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

            // Eager load counts per type
            if ($morphAlias === 'project') {
                $query->withCount([
                    'tasks',
                    'tasks as done_tasks_count' => fn($q) => $q->where('is_done', true),
                ]);
            }

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

        // Convert to sorted array of groups per entity
        $result = [];
        foreach ($byEntityAndType as $entityId => $types) {
            $groups = array_values($types);
            usort($groups, fn($a, $b) => strcmp($a['label'], $b['label']));
            $result[$entityId] = $groups;
        }

        return $result;
    }

    protected function extractLinkMetadata(string $type, $linkable): array
    {
        return match ($type) {
            'project' => [
                'done' => $linkable->done ?? false,
                'task_count' => $linkable->tasks_count ?? 0,
                'done_task_count' => $linkable->done_tasks_count ?? 0,
                'logged_minutes' => method_exists($linkable, 'getLoggedMinutesAttribute') ? ($linkable->logged_minutes ?? 0) : 0,
                'budget_amount' => $linkable->budget_amount,
            ],
            'planner_task' => [
                'is_done' => $linkable->is_done ?? false,
                'priority' => $linkable->priority?->value ?? null,
                'due_date' => $linkable->due_date?->format('d.m.Y'),
                'story_points' => $linkable->story_points?->value ?? null,
            ],
            'helpdesk_ticket' => [
                'is_done' => $linkable->is_done ?? false,
                'priority' => $linkable->priority?->value ?? null,
                'escalation_level' => $linkable->escalation_level?->value ?? null,
            ],
            default => [],
        };
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

        // Group by parent_entity_id
        $byParent = $allEntities->groupBy('parent_entity_id');

        // Build nodes for each parent group using batch approach
        $result = [];
        foreach ($byParent as $parentId => $children) {
            $result[$parentId] = $this->buildNodesForEntities($children);
        }

        return $result;
    }

    public function render()
    {
        return view('organization::livewire.entity.show')
            ->layout('platform::layouts.app');
    }
}
