<?php

namespace Platform\Organization\Livewire\VsmSystem;

use Livewire\Component;
use Platform\Organization\Models\OrganizationVsmSystem;

class Show extends Component
{
    public OrganizationVsmSystem $vsmSystem;
    public array $form = [];
    public bool $isEditing = false;

    public function mount(OrganizationVsmSystem $vsmSystem)
    {
        $this->vsmSystem = $vsmSystem->load('entities.type');
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'code' => $this->vsmSystem->code,
            'name' => $this->vsmSystem->name,
            'description' => $this->vsmSystem->description,
            'is_active' => $this->vsmSystem->is_active,
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

        $this->vsmSystem->update($this->form);
        $this->isEditing = false;
        
        session()->flash('message', 'VSM System erfolgreich aktualisiert.');
    }

    public function render()
    {
        return view('organization::livewire.vsm-system.show')
            ->layout('platform::layouts.app');
    }
}
