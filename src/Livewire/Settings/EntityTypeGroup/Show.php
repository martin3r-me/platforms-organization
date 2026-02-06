<?php

namespace Platform\Organization\Livewire\Settings\EntityTypeGroup;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntityTypeGroup;

class Show extends Component
{
    public OrganizationEntityTypeGroup $entityTypeGroup;

    public array $form = [];

    public function mount(OrganizationEntityTypeGroup $entityTypeGroup)
    {
        $this->entityTypeGroup = $entityTypeGroup;
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name' => $this->entityTypeGroup->name,
            'description' => $this->entityTypeGroup->description,
            'sort_order' => $this->entityTypeGroup->sort_order,
            'is_active' => $this->entityTypeGroup->is_active,
        ];
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->entityTypeGroup->name ||
               $this->form['description'] !== $this->entityTypeGroup->description ||
               $this->form['sort_order'] != $this->entityTypeGroup->sort_order ||
               $this->form['is_active'] !== $this->entityTypeGroup->is_active;
    }

    #[Computed]
    public function entityTypes()
    {
        return $this->entityTypeGroup->entityTypes()->ordered()->get();
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255|unique:organization_entity_type_groups,name,' . $this->entityTypeGroup->id,
            'form.description' => 'nullable|string',
            'form.sort_order' => 'integer|min:0',
            'form.is_active' => 'boolean',
        ]);

        try {
            $this->entityTypeGroup->update($this->form);
            $this->loadForm();
            $this->dispatch('toast', message: 'Entity Type Group gespeichert');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Speichern: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function delete()
    {
        try {
            $this->entityTypeGroup->delete();
            $this->dispatch('toast', message: 'Entity Type Group gelöscht');
            return redirect()->route('organization.settings.entity-type-groups.index');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Löschen: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function render()
    {
        return view('organization::livewire.settings.entity-type-group.show')
            ->layout('platform::layouts.app');
    }
}
