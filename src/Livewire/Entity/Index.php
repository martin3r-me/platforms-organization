<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeGroup;
use Platform\Organization\Services\EntityHierarchyResolver;
use Platform\Organization\Services\PerspectiveService;
use Platform\Organization\Services\SnapshotMovementService;

class Index extends Component
{
    public $search = '';
    public $selectedType = '';
    public $selectedGroup = '';
    public $showInactive = false;
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
        unset($this->entities, $this->stats, $this->parentEntities, $this->entityMovements);
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

        $entities = $query->orderBy('name')->get();

        // Determine root/child split based on perspective
        if (!$resolver->isDefaultHierarchy($perspective)) {
            $parentMap = $resolver->getParentMap($perspective, $teamId);
            $entityIds = $entities->pluck('id')->toArray();

            $rootEntities = $entities->filter(function ($e) use ($parentMap) {
                return ($parentMap[$e->id] ?? null) === null;
            })->sortBy('name');

            $childEntities = $entities->filter(function ($e) use ($parentMap) {
                return ($parentMap[$e->id] ?? null) !== null;
            })->sortBy('name');
        } else {
            $rootEntities = $entities->whereNull('parent_entity_id')->sortBy('name');
            $childEntities = $entities->whereNotNull('parent_entity_id')->sortBy('name');
        }

        // Gruppiere Child-Entities nach Entity-Typ und sortiere nach Typ-Name
        $groupedByType = $childEntities->groupBy('entity_type_id')->sortBy(function ($group) {
            return $group->first()->type->name ?? '';
        });

        return [
            'root' => $rootEntities,
            'byType' => $groupedByType,
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
