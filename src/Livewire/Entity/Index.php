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

        // Build hierarchical tree: roots with nested children
        if (!$resolver->isDefaultHierarchy($perspective)) {
            $parentMap = $resolver->getParentMap($perspective, $teamId);
            $rootEntities = $entities->filter(fn($e) => ($parentMap[$e->id] ?? null) === null);
        } else {
            // Root = no parent OR parent not in the current filtered result set
            $rootEntities = $entities->filter(fn($e) => $e->parent_entity_id === null || !in_array($e->parent_entity_id, $entityIds));
        }

        // Group roots by type group. Group order: EntityTypeGroup.sort_order.
        // Within group: EntityType.sort_order, then Name. Roots inside group already
        // form a hierarchy via $childrenByParent — siblings get the same sort.
        $sortSiblings = fn ($collection) => $collection->sortBy([
            ['type.sort_order', 'asc'],
            ['name', 'asc'],
        ]);

        $rootsByGroup = $rootEntities
            ->groupBy(fn($e) => $e->type->group->id ?? 0)
            ->sortBy(fn($group) => $group->first()->type?->group?->sort_order ?? PHP_INT_MAX)
            ->map($sortSiblings);

        // Group children by parent_id and apply the same sibling sort
        $childrenByParent = $entities
            ->filter(fn($e) => $e->parent_entity_id !== null && in_array($e->parent_entity_id, $entityIds))
            ->groupBy('parent_entity_id')
            ->map($sortSiblings);

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
