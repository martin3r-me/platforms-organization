<?php

namespace Platform\Organization\Livewire\CostCenter;

use Livewire\Component;
use Platform\Organization\Models\OrganizationCostCenter;

class Show extends Component
{
    public OrganizationCostCenter $costCenter;
    public array $form = [];
    public bool $isEditing = false;

    public function mount(OrganizationCostCenter $costCenter)
    {
        $this->costCenter = $costCenter;
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'code' => $this->costCenter->code,
            'name' => $this->costCenter->name,
            'description' => $this->costCenter->description,
            'is_active' => $this->costCenter->is_active,
        ];
    }

    public function edit()
    {
        $this->isEditing = true;
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.code' => 'nullable|string|max:50',
            'form.description' => 'nullable|string',
            'form.is_active' => 'boolean',
        ]);

        $this->costCenter->update($this->form);
        $this->isEditing = false;
        
        session()->flash('message', 'Kostenstelle erfolgreich aktualisiert.');
    }

    public function render()
    {
        return view('organization::livewire.cost-center.show')
            ->layout('platform::layouts.app');
    }
}
