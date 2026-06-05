<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeGroup;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Services\EntityHierarchyResolver;
use Platform\Organization\Services\PerspectiveService;
use Platform\Organization\Services\SnapshotMovementService;

class Index extends Component
{
    public $search = '';
    public $selectedType = '';
    public $selectedGroup = '';
    public $showInactive = false;
    public $onlyWithSignals = false;
    public $modalShow = false;
    public $newEntity = [
        'name' => '',
        'code' => '',
        'description' => '',
        'entity_type_id' => '',
        'parent_entity_id' => '',
        'is_active' => true,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedType' => ['except' => ''],
        'selectedGroup' => ['except' => ''],
        'showInactive' => ['except' => false],
        'onlyWithSignals' => ['except' => false],
    ];

    public function updatingSearch()
    {
        // Reset search without pagination
    }

    public function updatingSelectedType()
    {
        // Reset type filter without pagination
    }

    public function updatingSelectedGroup()
    {
        // Reset group filter without pagination
    }

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    #[On('perspective-switched')]
    public function onPerspectiveSwitched(): void
    {
        unset($this->entities, $this->stats, $this->parentEntities, $this->entityMovements, $this->vsmSystemMap, $this->signalCounts);
    }

    protected function getActivePerspective()
    {
        $user = auth()->user();
        return PerspectiveService::getActive($user->currentTeam->id, $user->id);
    }

    #[Computed]
    public function entities()
    {
        $teamId = auth()->user()->currentTeam->id;
        $perspective = $this->getActivePerspective();
        $resolver = resolve(EntityHierarchyResolver::class);

        $query = OrganizationEntity::query()
            ->with([
                'type.group',
                'parent',
                'children.type.group',
                'team',
                'user',
                'relationsFrom.relationType',
                'relationsFrom.toEntity.type',
                'relationsTo.relationType',
                'relationsTo.fromEntity.type'
            ])
            ->forTeam($teamId);

        // Non-default perspective: restrict to entities in this perspective
        if (!$resolver->isDefaultHierarchy($perspective)) {
            $perspectiveEntityIds = $resolver->entityIdsInPerspective($perspective, $teamId);
            $query->whereIn('id', $perspectiveEntityIds);
        }

        // Suche
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        // Entity Type Filter
        if ($this->selectedType) {
            $query->where('entity_type_id', $this->selectedType);
        }

        // Entity Type Group Filter
        if ($this->selectedGroup) {
            $query->whereHas('type.group', function ($q) {
                $q->where('id', $this->selectedGroup);
            });
        }

        // Active/Inactive Filter
        if (!$this->showInactive) {
            $query->active();
        }

        // Only with signals filter
        if ($this->onlyWithSignals) {
            $query->whereHas('signals', fn($q) => $q->whereIn('status', ['open', 'acknowledged']));
        }

        $entities = $query->orderBy('name')->get();
        $entityIds = $entities->pluck('id')->toArray();

        $sortSiblings = fn ($collection) => $collection->sortBy([
            ['type.sort_order', 'asc'],
            ['name', 'asc'],
        ]);

        // Each EntityTypeGroup gets its own section with its own roots.
        // An entity is a "root" inside its group when its parent is not also in
        // that group — this prevents Persons/Externals from rendering under a
        // Business-Unit root just because they're its children in the global tree.
        $entitiesByGroup = $entities->groupBy(fn($e) => $e->type->group->id ?? 0);

        // Build a per-parent children map that ONLY contains same-group children.
        $childrenByParent = collect();
        foreach ($entitiesByGroup as $groupEntities) {
            $groupIds = $groupEntities->pluck('id')->all();
            $groupChildren = $groupEntities
                ->filter(fn ($e) => $e->parent_entity_id !== null && in_array($e->parent_entity_id, $groupIds))
                ->groupBy('parent_entity_id')
                ->map($sortSiblings);
            foreach ($groupChildren as $parentId => $children) {
                $childrenByParent[$parentId] = $children;
            }
        }

        // For non-default perspectives, use the perspective's parent map for root detection.
        $usePerspectiveRoots = !$resolver->isDefaultHierarchy($perspective);
        $perspectiveParentMap = $usePerspectiveRoots
            ? $resolver->getParentMap($perspective, $teamId)
            : [];

        $rootsByGroup = $entitiesByGroup
            ->map(function ($groupEntities) use ($sortSiblings, $usePerspectiveRoots, $perspectiveParentMap) {
                $groupIds = $groupEntities->pluck('id')->all();

                $roots = $groupEntities->filter(function ($e) use ($groupIds, $usePerspectiveRoots, $perspectiveParentMap) {
                    if ($usePerspectiveRoots) {
                        return ($perspectiveParentMap[$e->id] ?? null) === null;
                    }
                    return $e->parent_entity_id === null || !in_array($e->parent_entity_id, $groupIds);
                });

                return $sortSiblings($roots);
            })
            ->filter(fn ($group) => $group->isNotEmpty())
            ->sortBy(fn ($group) => $group->first()->type?->group?->sort_order ?? PHP_INT_MAX);

        return [
            'rootsByGroup' => $rootsByGroup,
            'childrenByParent' => $childrenByParent,
            'all' => $entities,
        ];
    }

    #[Computed]
    public function entityTypes()
    {
        return OrganizationEntityType::active()
            ->ordered()
            ->with('group')
            ->get()
            ->groupBy('group.name');
    }

    #[Computed]
    public function entityTypeGroups()
    {
        return OrganizationEntityTypeGroup::active()
            ->ordered()
            ->get();
    }

    #[Computed]
    public function parentEntities()
    {
        $teamId = auth()->user()->currentTeam->id;
        $perspective = $this->getActivePerspective();
        $resolver = resolve(EntityHierarchyResolver::class);

        $query = OrganizationEntity::active()
            ->forTeam($teamId)
            ->with('type')
            ->orderBy('name');

        if (!$resolver->isDefaultHierarchy($perspective)) {
            $ids = $resolver->entityIdsInPerspective($perspective, $teamId);
            $query->whereIn('id', $ids);
        }

        return $query->get();
    }

    #[Computed]
    public function entityMovements(): array
    {
        $teamId = auth()->user()->currentTeam->id;
        $entityIds = OrganizationEntity::forTeam($teamId)->pluck('id')->toArray();
        if (empty($entityIds)) {
            return [];
        }

        return resolve(SnapshotMovementService::class)->forEntitiesBatch($entityIds, 7);
    }

    /**
     * VSM-System dimension values per entity: [entity_id => Collection of value objects]
     */
    #[Computed]
    public function vsmSystemMap(): array
    {
        $teamId = auth()->user()->currentTeam->id;
        $definition = OrganizationDimensionDefinition::findByKey('vsm-system');

        if (!$definition) {
            return [];
        }

        return OrganizationDimensionLink::where('dimension_definition_id', $definition->id)
            ->where('linkable_type', 'organization_entity')
            ->whereIn('linkable_id', OrganizationEntity::forTeam($teamId)->pluck('id'))
            ->with('value')
            ->get()
            ->groupBy('linkable_id')
            ->map(fn ($links) => $links->pluck('value')->filter()->sortBy('sort_order')->values())
            ->toArray();
    }

    #[Computed]
    public function signalCounts(): array
    {
        $teamId = auth()->user()->currentTeam->id;

        return OrganizationSignal::where('team_id', $teamId)
            ->whereIn('status', ['open', 'acknowledged'])
            ->selectRaw('entity_id, COUNT(*) as total,
                SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) as critical_count,
                SUM(CASE WHEN severity = "algedonic" THEN 1 ELSE 0 END) as algedonic_count,
                SUM(CASE WHEN severity = "warning" THEN 1 ELSE 0 END) as warning_count')
            ->groupBy('entity_id')
            ->get()
            ->keyBy('entity_id')
            ->toArray();
    }

    #[Computed]
    public function stats()
    {
        $teamId = auth()->user()->currentTeam->id;
        $perspective = $this->getActivePerspective();
        $resolver = resolve(EntityHierarchyResolver::class);

        $baseQuery = OrganizationEntity::forTeam($teamId);
        if (!$resolver->isDefaultHierarchy($perspective)) {
            $ids = $resolver->entityIdsInPerspective($perspective, $teamId);
            $baseQuery->whereIn('id', $ids);
        }

        $all = (clone $baseQuery)->get();

        return [
            'total' => $all->count(),
            'active' => $all->where('is_active', true)->count(),
            'inactive' => $all->where('is_active', false)->count(),
            'by_type' => $all->where('is_active', true)
                ->load('type')
                ->groupBy('type.name')
                ->map->count(),
        ];
    }

    public function openCreateModal()
    {
        $this->modalShow = true;
    }

    public function closeCreateModal()
    {
        $this->modalShow = false;
        $this->reset('newEntity');
    }

    public function createEntity()
    {
        $this->validate([
            'newEntity.name' => 'required|string|max:255',
            'newEntity.code' => 'nullable|string|max:255',
            'newEntity.description' => 'nullable|string',
            'newEntity.entity_type_id' => 'required|exists:organization_entity_types,id',
            'newEntity.parent_entity_id' => 'nullable|exists:organization_entities,id',
            'newEntity.is_active' => 'boolean',
        ]);

        $entity = OrganizationEntity::create([
            'name' => $this->newEntity['name'],
            'code' => $this->newEntity['code'] ?: null,
            'description' => $this->newEntity['description'],
            'entity_type_id' => $this->newEntity['entity_type_id'],
            'parent_entity_id' => $this->newEntity['parent_entity_id'] ?: null,
            'is_active' => $this->newEntity['is_active'],
            'team_id' => auth()->user()->currentTeam->id,
            'user_id' => auth()->id(),
        ]);

        $this->closeCreateModal();
        session()->flash('message', 'Organisationseinheit erfolgreich erstellt.');
    }

    // Edit modal wird später implementiert

    public function render()
    {
        return view('organization::livewire.entity.index')
            ->layout('platform::layouts.app');
    }
}
