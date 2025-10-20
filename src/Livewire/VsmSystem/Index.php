<?php

namespace Platform\Organization\Livewire\VsmSystem;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationVsmSystem;

class Index extends Component
{
    public $search = '';
    public $showInactive = false;
    public $modalShow = false;

    public $form = [
        'code' => '',
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
    public function systems()
    {
        $query = OrganizationVsmSystem::query();

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

        return $query->orderBy('sort_order')->orderBy('name')->get();
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
            'form.code' => ['required', 'string', 'max:10'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.sort_order' => ['integer'],
            'form.is_active' => ['boolean'],
        ])['form'];

        OrganizationVsmSystem::create($data);

        $this->modalShow = false;
        $this->dispatch('toast', message: 'VSM System erstellt');
    }

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    public function render()
    {
        return view('organization::livewire.vsm-system.index')
            ->layout('platform::layouts.app');
    }
}


