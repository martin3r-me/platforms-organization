<?php

namespace Platform\Organization\Livewire\CostCenter;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationCostCenter;

class Index extends Component
{
    public $search = '';
    public $showInactive = false;
    public $modalShow = false;

    public $form = [
        'code' => '',
        'name' => '',
        'description' => '',
        'is_active' => true,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    #[Computed]
    public function costCenters()
    {
        $query = OrganizationCostCenter::query()
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
            'form.is_active' => ['boolean'],
        ])['form'];

        OrganizationCostCenter::create([
            'code' => $data['code'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'team_id' => auth()->user()->currentTeam->id,
            'user_id' => auth()->id(),
        ]);

        $this->modalShow = false;
        $this->dispatch('toast', message: 'Kostenstelle erstellt');
    }

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    public function render()
    {
        return view('organization::livewire.cost-center.index')
            ->layout('platform::layouts.app');
    }
}


