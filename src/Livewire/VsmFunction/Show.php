<?php

namespace Platform\Organization\Livewire\VsmFunction;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationVsmFunction;
use Platform\Organization\Models\OrganizationEntity;

class Show extends Component
{
    public OrganizationVsmFunction $vsmFunction;
    public array $form = [];
    public bool $isEditing = false;

    public function mount(OrganizationVsmFunction $vsmFunction)
    {
        $this->vsmFunction = $vsmFunction;
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'code' => $this->vsmFunction->code,
            'name' => $this->vsmFunction->name,
            'description' => $this->vsmFunction->description,
            'root_entity_id' => $this->vsmFunction->root_entity_id,
            'is_active' => $this->vsmFunction->is_active,
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

        $this->vsmFunction->update($this->form);
        $this->isEditing = false;
        
        session()->flash('message', 'VSM Funktion erfolgreich aktualisiert.');
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
        return view('organization::livewire.vsm-function.show')
            ->layout('platform::layouts.app');
    }
}
