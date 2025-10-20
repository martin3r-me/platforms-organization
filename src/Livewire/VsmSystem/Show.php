<?php

namespace Platform\Organization\Livewire\VsmSystem;

use Livewire\Component;
use Platform\Organization\Models\OrganizationVsmSystem;

class Show extends Component
{
    public OrganizationVsmSystem $system;

    public $form = [
        'code' => '',
        'name' => '',
        'description' => '',
        'sort_order' => 0,
        'is_active' => true,
    ];

    public function mount(OrganizationVsmSystem $system)
    {
        $this->system = $system;
        $this->form = [
            'code' => $system->code,
            'name' => $system->name,
            'description' => $system->description,
            'sort_order' => (int) $system->sort_order,
            'is_active' => (bool) $system->is_active,
        ];
    }

    public function save()
    {
        $data = $this->validate([
            'form.code' => ['required', 'string', 'max:10'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.sort_order' => ['integer'],
            'form.is_active' => ['boolean'],
        ])['form'];

        $this->system->update($data);
        $this->dispatch('toast', message: 'Gespeichert');
    }

    public function render()
    {
        return view('organization::livewire.vsm-system.show')
            ->layout('platform::layouts.app');
    }
}


