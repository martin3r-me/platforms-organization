<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeGroup;
use Platform\Organization\Models\OrganizationVsmSystem;

class Index extends Component
{
    public $search = '';
    public $selectedType = '';
    public $selectedGroup = '';
    public $showInactive = false;
    public $modalShow = false;
    public $newEntity = [
        'name' => '',
        'description' => '',
        'entity_type_id' => '',
        'vsm_system_id' => '',
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

    #[Computed]
    public function entities()
    {
        $query = OrganizationEntity::query()
            ->with(['type.group', 'vsmSystem', 'parent', 'team', 'user'])
            ->forTeam(auth()->user()->currentTeam->id);

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

        return $query->orderBy('name')->get();
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
    public function vsmSystems()
    {
        return OrganizationVsmSystem::active()
            ->ordered()
            ->get();
    }

    #[Computed]
    public function parentEntities()
    {
        return OrganizationEntity::active()
            ->forTeam(auth()->user()->currentTeam->id)
            ->with('type')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function stats()
    {
        $teamId = auth()->user()->currentTeam->id;
        
        return [
            'total' => OrganizationEntity::forTeam($teamId)->count(),
            'active' => OrganizationEntity::forTeam($teamId)->active()->count(),
            'inactive' => OrganizationEntity::forTeam($teamId)->where('is_active', false)->count(),
            'by_type' => OrganizationEntity::forTeam($teamId)
                ->active()
                ->with('type')
                ->get()
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
            'newEntity.description' => 'nullable|string',
            'newEntity.entity_type_id' => 'required|exists:organization_entity_types,id',
            'newEntity.vsm_system_id' => 'nullable|exists:organization_vsm_systems,id',
            'newEntity.parent_entity_id' => 'nullable|exists:organization_entities,id',
            'newEntity.is_active' => 'boolean',
        ]);

        $entity = OrganizationEntity::create([
            'name' => $this->newEntity['name'],
            'description' => $this->newEntity['description'],
            'entity_type_id' => $this->newEntity['entity_type_id'],
            'vsm_system_id' => $this->newEntity['vsm_system_id'] ?: null,
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
