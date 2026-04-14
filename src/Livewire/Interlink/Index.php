<?php

namespace Platform\Organization\Livewire\Interlink;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationInterlink;
use Platform\Organization\Models\OrganizationInterlinkCategory;
use Platform\Organization\Models\OrganizationInterlinkType;

class Index extends Component
{
    public $search = '';
    public $selectedCategoryId = '';
    public $selectedTypeId = '';
    public $showInactive = false;
    public $modalShow = false;
    public $newInterlink = [
        'name' => '',
        'description' => '',
        'category_id' => '',
        'type_id' => '',
        'is_bidirectional' => false,
        'is_active' => true,
        'owner_entity_id' => '',
        'valid_from' => null,
        'valid_to' => null,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedCategoryId' => ['except' => ''],
        'selectedTypeId' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    #[Computed]
    public function interlinks()
    {
        $query = OrganizationInterlink::query()
            ->with(['category', 'type', 'user', 'ownerEntity'])
            ->where('team_id', auth()->user()->currentTeam->id);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->selectedCategoryId) {
            $query->where('category_id', $this->selectedCategoryId);
        }

        if ($this->selectedTypeId) {
            $query->where('type_id', $this->selectedTypeId);
        }

        if (!$this->showInactive) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function availableCategories()
    {
        return OrganizationInterlinkCategory::active()->ordered()->get();
    }

    #[Computed]
    public function availableTypes()
    {
        return OrganizationInterlinkType::active()->ordered()->get();
    }

    #[Computed]
    public function stats()
    {
        $teamId = auth()->user()->currentTeam->id;

        return [
            'total' => OrganizationInterlink::where('team_id', $teamId)->count(),
            'active' => OrganizationInterlink::where('team_id', $teamId)->active()->count(),
            'inactive' => OrganizationInterlink::where('team_id', $teamId)->where('is_active', false)->count(),
            'bidirectional' => OrganizationInterlink::where('team_id', $teamId)->active()->where('is_bidirectional', true)->count(),
        ];
    }

    #[Computed]
    public function itemTree(): array
    {
        $items = $this->interlinks;
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
        $this->reset('newInterlink');
    }

    public function createInterlink()
    {
        $this->validate([
            'newInterlink.name' => 'required|string|max:255',
            'newInterlink.description' => 'nullable|string',
            'newInterlink.category_id' => 'required|exists:organization_interlink_categories,id',
            'newInterlink.type_id' => 'required|exists:organization_interlink_types,id',
            'newInterlink.is_bidirectional' => 'boolean',
            'newInterlink.is_active' => 'boolean',
            'newInterlink.owner_entity_id' => 'nullable|integer|exists:organization_entities,id',
            'newInterlink.valid_from' => 'nullable|date',
            'newInterlink.valid_to' => 'nullable|date|after_or_equal:newInterlink.valid_from',
        ]);

        OrganizationInterlink::create([
            'name' => $this->newInterlink['name'],
            'description' => $this->newInterlink['description'] ?: null,
            'category_id' => $this->newInterlink['category_id'],
            'type_id' => $this->newInterlink['type_id'],
            'is_bidirectional' => $this->newInterlink['is_bidirectional'],
            'is_active' => $this->newInterlink['is_active'],
            'owner_entity_id' => $this->newInterlink['owner_entity_id'] !== '' ? (int) $this->newInterlink['owner_entity_id'] : null,
            'valid_from' => $this->newInterlink['valid_from'] ?: null,
            'valid_to' => $this->newInterlink['valid_to'] ?: null,
            'team_id' => auth()->user()->currentTeam->id,
            'user_id' => auth()->id(),
        ]);

        $this->closeCreateModal();
        session()->flash('message', 'Interlink erfolgreich erstellt.');
    }

    public function deleteInterlink(int $interlinkId)
    {
        $interlink = OrganizationInterlink::findOrFail($interlinkId);

        if ($interlink->team_id !== auth()->user()->currentTeam->id) {
            session()->flash('error', 'Keine Berechtigung.');
            return;
        }

        $interlink->delete();
        session()->flash('message', 'Interlink erfolgreich gelöscht.');
    }

    public function render()
    {
        return view('organization::livewire.interlink.index')
            ->layout('platform::layouts.app');
    }
}
