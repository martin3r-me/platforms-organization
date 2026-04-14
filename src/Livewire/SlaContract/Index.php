<?php

namespace Platform\Organization\Livewire\SlaContract;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationSlaContract;

class Index extends Component
{
    public $search = '';
    public $showInactive = false;
    public $modalShow = false;
    public $newSlaContract = [
        'name' => '',
        'description' => '',
        'response_time_hours' => null,
        'resolution_time_hours' => null,
        'error_tolerance_percent' => null,
        'is_active' => true,
        'owner_entity_id' => '',
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    #[Computed]
    public function slaContracts()
    {
        $query = OrganizationSlaContract::query()
            ->with(['user', 'ownerEntity'])
            ->where('team_id', auth()->user()->currentTeam->id);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if (!$this->showInactive) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function stats()
    {
        $teamId = auth()->user()->currentTeam->id;

        return [
            'total' => OrganizationSlaContract::where('team_id', $teamId)->count(),
            'active' => OrganizationSlaContract::where('team_id', $teamId)->active()->count(),
            'inactive' => OrganizationSlaContract::where('team_id', $teamId)->where('is_active', false)->count(),
        ];
    }

    #[Computed]
    public function itemTree(): array
    {
        $items = $this->slaContracts;
        $teamId = Auth::user()->currentTeam->id;

        $entities = OrganizationEntity::where('team_id', $teamId)
            ->with('type')
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $byOwner = $items->groupBy('owner_entity_id');

        $ownerIds = $byOwner->keys()->filter()->toArray();
        $relevantEntityIds = collect();

        foreach ($ownerIds as $ownerId) {
            $current = $entities->get($ownerId);
            if (!$current) continue;

            $visited = [];
            while ($current && !in_array($current->id, $visited)) {
                $visited[] = $current->id;
                $relevantEntityIds->push($current->id);
                $current = $current->parent_entity_id ? $entities->get($current->parent_entity_id) : null;
            }
        }

        $relevantEntityIds = $relevantEntityIds->unique();

        $tree = $this->buildEntityNode(null, $entities, $byOwner, $relevantEntityIds, 0);

        $unowned = $byOwner->get('', collect())->merge($byOwner->get(null, collect()));
        if ($unowned->isNotEmpty()) {
            $tree[] = [
                'type' => 'group',
                'label' => 'Ohne Owner',
                'entity' => null,
                'entity_type' => null,
                'depth' => 0,
                'items' => $unowned->values()->all(),
                'children' => [],
            ];
        }

        return $tree;
    }

    private function buildEntityNode(?int $parentId, $entities, $byOwner, $relevantEntityIds, int $depth): array
    {
        $nodes = [];

        $children = $entities->filter(function ($e) use ($parentId, $relevantEntityIds) {
            return $e->parent_entity_id == $parentId && $relevantEntityIds->contains($e->id);
        })->sortBy(fn ($e) => ($e->type?->sort_order ?? 999) . '_' . $e->name);

        foreach ($children as $entity) {
            $directItems = $byOwner->get($entity->id, collect());
            $childNodes = $this->buildEntityNode($entity->id, $entities, $byOwner, $relevantEntityIds, $depth + 1);

            $nodes[] = [
                'type' => 'entity',
                'label' => $entity->name,
                'entity' => $entity,
                'entity_type' => $entity->type?->name,
                'depth' => $depth,
                'items' => $directItems->values()->all(),
                'children' => $childNodes,
            ];
        }

        return $nodes;
    }

    #[Computed]
    public function availableEntities()
    {
        return OrganizationEntity::where('team_id', Auth::user()->currentTeam->id)
            ->orderBy('name')
            ->get();
    }

    public function openCreateModal()
    {
        $this->modalShow = true;
    }

    public function closeCreateModal()
    {
        $this->modalShow = false;
        $this->reset('newSlaContract');
    }

    public function createSlaContract()
    {
        $this->validate([
            'newSlaContract.name' => 'required|string|max:255',
            'newSlaContract.description' => 'nullable|string',
            'newSlaContract.response_time_hours' => 'nullable|integer|min:1',
            'newSlaContract.resolution_time_hours' => 'nullable|integer|min:1',
            'newSlaContract.error_tolerance_percent' => 'nullable|integer|min:0|max:100',
            'newSlaContract.is_active' => 'boolean',
            'newSlaContract.owner_entity_id' => 'nullable|integer|exists:organization_entities,id',
        ]);

        OrganizationSlaContract::create([
            'name' => $this->newSlaContract['name'],
            'description' => $this->newSlaContract['description'] ?: null,
            'response_time_hours' => $this->newSlaContract['response_time_hours'] ?: null,
            'resolution_time_hours' => $this->newSlaContract['resolution_time_hours'] ?: null,
            'error_tolerance_percent' => $this->newSlaContract['error_tolerance_percent'],
            'is_active' => $this->newSlaContract['is_active'],
            'owner_entity_id' => $this->newSlaContract['owner_entity_id'] !== '' ? (int) $this->newSlaContract['owner_entity_id'] : null,
            'team_id' => auth()->user()->currentTeam->id,
            'user_id' => auth()->id(),
        ]);

        $this->closeCreateModal();
        session()->flash('message', 'SLA-Vertrag erfolgreich erstellt.');
    }

    public function deleteSlaContract(int $slaContractId)
    {
        $slaContract = OrganizationSlaContract::findOrFail($slaContractId);

        if ($slaContract->team_id !== auth()->user()->currentTeam->id) {
            session()->flash('error', 'Keine Berechtigung.');
            return;
        }

        $slaContract->delete();
        session()->flash('message', 'SLA-Vertrag erfolgreich gelöscht.');
    }

    public function render()
    {
        return view('organization::livewire.sla-contract.index')
            ->layout('platform::layouts.app');
    }
}
