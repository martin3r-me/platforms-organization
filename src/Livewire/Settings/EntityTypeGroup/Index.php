<?php

namespace Platform\Organization\Livewire\Settings\EntityTypeGroup;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntityTypeGroup;

class Index extends Component
{
    public $search = '';
    public $showInactive = false;
    public $modalShow = false;

    public $form = [
        'name' => '',
        'description' => '',
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    #[Computed]
    public function entityTypeGroups()
    {
        $query = OrganizationEntityTypeGroup::query()
            ->withCount('entityTypes');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if (!$this->showInactive) {
            $query->active();
        }

        return $query->ordered()->get();
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
            'form.name' => ['required', 'string', 'max:255', 'unique:organization_entity_type_groups,name'],
            'form.description' => ['nullable', 'string'],
            'form.sort_order' => ['integer', 'min:0'],
            'form.is_active' => ['boolean'],
        ])['form'];

        OrganizationEntityTypeGroup::create($data);

        $this->modalShow = false;
        $this->dispatch('toast', message: 'Entity Type Group erstellt');
    }

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    public function render()
    {
        return view('organization::livewire.settings.entity-type-group.index')
            ->layout('platform::layouts.app');
    }
}
