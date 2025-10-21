<?php

namespace Platform\Organization\Livewire\CostCenter;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationEntity;

class Show extends Component
{
    public OrganizationCostCenter $costCenter;
    public array $form = [];
    public bool $isEditing = false;

    public function mount(OrganizationCostCenter $costCenter)
    {
        $this->costCenter = $costCenter->load('entities.type');
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'code' => $this->costCenter->code,
            'name' => $this->costCenter->name,
            'description' => $this->costCenter->description,
            'root_entity_id' => $this->costCenter->root_entity_id,
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
            'form.root_entity_id' => 'nullable|exists:organization_entities,id',
            'form.is_active' => 'boolean',
        ]);

        $this->costCenter->update($this->form);
        $this->loadForm(); // Reload form to reset dirty state
        
        session()->flash('message', 'Kostenstelle erfolgreich aktualisiert.');
    }

    public function isDirty()
    {
        return $this->form['name'] !== $this->costCenter->name ||
               $this->form['code'] !== $this->costCenter->code ||
               $this->form['description'] !== $this->costCenter->description ||
               $this->form['root_entity_id'] != $this->costCenter->root_entity_id ||
               $this->form['is_active'] !== $this->costCenter->is_active;
    }

    #[Computed]
    public function entities()
    {
        return OrganizationEntity::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('organization::livewire.cost-center.show')
            ->layout('platform::layouts.app');
    }
}
