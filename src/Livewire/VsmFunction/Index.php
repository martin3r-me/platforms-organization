<?php

namespace Platform\Organization\Livewire\VsmFunction;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationVsmFunction;
use Platform\Organization\Models\OrganizationEntity;

class Index extends Component
{
    public $search = '';
    public $showInactive = false;
    public $modalShow = false;

    public $form = [
        'code' => '',
        'name' => '',
        'description' => '',
        'root_entity_id' => null,
        'is_active' => true,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    #[Computed]
    public function vsmFunctions()
    {
        $query = OrganizationVsmFunction::query()
            ->where('team_id', auth()->user()->currentTeam->id);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if (!$this->showInactive) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function entities()
    {
        return OrganizationEntity::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
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
            'form.code' => ['nullable', 'string', 'max:255'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.root_entity_id' => ['nullable', 'exists:organization_entities,id'],
            'form.is_active' => ['boolean'],
        ])['form'];

        OrganizationVsmFunction::create([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'root_entity_id' => $data['root_entity_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'team_id' => auth()->user()->currentTeam->id,
            'user_id' => auth()->id(),
        ]);

        $this->modalShow = false;
        $this->dispatch('toast', message: 'VSM Funktion erstellt');
    }

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    public function render()
    {
        return view('organization::livewire.vsm-function.index')
            ->layout('platform::layouts.app');
    }
}
