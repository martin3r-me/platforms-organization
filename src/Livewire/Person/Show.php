<?php

namespace Platform\Organization\Livewire\Person;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationPerson;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\DimensionLinkService;

class Show extends Component
{
    public OrganizationPerson $person;
    public array $form = [];
    public bool $isEditing = false;

    public function mount(OrganizationPerson $person)
    {
        $this->person = $person;
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'code' => $this->person->code,
            'name' => $this->person->name,
            'description' => $this->person->description,
            'root_entity_id' => $this->person->root_entity_id,
            'is_active' => $this->person->is_active,
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

        $this->person->update($this->form);
        $this->loadForm();

        session()->flash('message', 'Person erfolgreich aktualisiert.');
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->person->name ||
               $this->form['code'] !== $this->person->code ||
               $this->form['description'] !== $this->person->description ||
               $this->form['root_entity_id'] != $this->person->root_entity_id ||
               $this->form['is_active'] !== $this->person->is_active;
    }

    #[Computed]
    public function linkedContexts()
    {
        $service = new DimensionLinkService();
        return $service->getLinkedContexts('persons', $this->person->id);
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
        return view('organization::livewire.person.show')
            ->layout('platform::layouts.app');
    }
}
