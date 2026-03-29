<?php

namespace Platform\Organization\Livewire\Interlink;

use Livewire\Component;
use Livewire\Attributes\Computed;
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
            ->with(['category', 'type', 'user'])
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
