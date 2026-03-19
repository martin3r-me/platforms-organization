<?php

namespace Platform\Organization\Livewire\Settings\InterlinkType;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationInterlinkType;

class Index extends Component
{
    public $search = '';
    public $showInactive = false;
    public $modalShow = false;

    public $form = [
        'name' => '',
        'code' => '',
        'description' => '',
        'icon' => '',
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    #[Computed]
    public function interlinkTypes()
    {
        $query = OrganizationInterlinkType::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
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
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => ['required', 'string', 'max:255', 'unique:organization_interlink_types,code'],
            'form.description' => ['nullable', 'string'],
            'form.icon' => ['nullable', 'string', 'max:255'],
            'form.sort_order' => ['integer', 'min:0'],
            'form.is_active' => ['boolean'],
        ])['form'];

        OrganizationInterlinkType::create($data);

        $this->modalShow = false;
        $this->dispatch('toast', message: 'Interlink-Typ erstellt');
    }

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    public function render()
    {
        return view('organization::livewire.settings.interlink-type.index')
            ->layout('platform::layouts.app');
    }
}
