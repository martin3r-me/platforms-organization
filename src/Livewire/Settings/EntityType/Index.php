<?php

namespace Platform\Organization\Livewire\Settings\EntityType;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeGroup;
use Platform\Core\PlatformCore;

class Index extends Component
{
    public $search = '';
    public $selectedGroup = '';
    public $showInactive = false;
    public $modalShow = false;

    public $form = [
        'name' => '',
        'code' => '',
        'description' => '',
        'icon' => '',
        'sort_order' => 0,
        'is_active' => true,
        'entity_type_group_id' => null,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedGroup' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    #[Computed]
    public function entityTypes()
    {
        $query = OrganizationEntityType::query()
            ->with('group');

        // Suche
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        // Group Filter
        if ($this->selectedGroup) {
            $query->where('entity_type_group_id', $this->selectedGroup);
        }

        // Active/Inactive Filter
        if (!$this->showInactive) {
            $query->active();
        }

        return $query->ordered()->get();
    }

    #[Computed]
    public function entityTypeGroups()
    {
        return OrganizationEntityTypeGroup::active()
            ->ordered()
            ->get();
    }

    #[Computed]
    public function modules()
    {
        return collect(PlatformCore::getModules())
            ->mapWithKeys(function ($module) {
                return [$module['key'] => $module['title'] ?? ucfirst($module['key'])];
            })
            ->toArray();
    }

    public function create()
    {
        $this->reset('form');
        $this->form['is_active'] = true;
        $this->modalShow = true;
    }

    public function store()
    {
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:255', 'unique:organization_entity_types,code'],
            'form.description' => ['nullable', 'string'],
            'form.icon' => ['nullable', 'string', 'max:255'],
            'form.sort_order' => ['integer', 'min:0'],
            'form.is_active' => ['boolean'],
            'form.entity_type_group_id' => ['nullable', 'exists:organization_entity_type_groups,id'],
        ])['form'];

        OrganizationEntityType::create($data);

        $this->modalShow = false;
        $this->dispatch('toast', message: 'Entity Type erstellt');
    }

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    public function render()
    {
        return view('organization::livewire.settings.entity-type.index')
            ->layout('platform::layouts.app');
    }
}

