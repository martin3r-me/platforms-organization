<?php

namespace Platform\Organization\Livewire\CostCenter;

use Livewire\Component;
use Platform\Organization\Models\OrganizationCostCenter;

class Show extends Component
{
    public OrganizationCostCenter $costCenter;

    public $form = [
        'code' => '',
        'name' => '',
        'description' => '',
        'is_active' => true,
    ];

    public function mount(OrganizationCostCenter $costCenter)
    {
        $this->costCenter = $costCenter;
        $this->form = [
            'code' => $costCenter->code,
            'name' => $costCenter->name,
            'description' => $costCenter->description,
            'is_active' => (bool) $costCenter->is_active,
        ];
    }

    public function save()
    {
        $data = $this->validate([
            'form.code' => ['nullable', 'string', 'max:255'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.is_active' => ['boolean'],
        ])['form'];

        $this->costCenter->update($data);
        $this->dispatch('toast', message: 'Gespeichert');
    }

    public function render()
    {
        return view('organization::livewire.cost-center.show')
            ->layout('platform::layouts.app');
    }
}


