<?php

namespace Platform\Organization\Livewire\Settings\RelationType;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntityRelationType;

class Show extends Component
{
    public OrganizationEntityRelationType $relationType;

    public array $form = [];

    public function mount(OrganizationEntityRelationType $relationType)
    {
        $this->relationType = $relationType;
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name' => $this->relationType->name,
            'code' => $this->relationType->code,
            'description' => $this->relationType->description,
            'icon' => $this->relationType->icon,
            'sort_order' => $this->relationType->sort_order,
            'is_active' => $this->relationType->is_active,
            'is_directional' => $this->relationType->is_directional,
            'is_hierarchical' => $this->relationType->is_hierarchical,
            'is_reciprocal' => $this->relationType->is_reciprocal,
        ];
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->relationType->name ||
               $this->form['code'] !== $this->relationType->code ||
               $this->form['description'] !== $this->relationType->description ||
               $this->form['icon'] !== $this->relationType->icon ||
               $this->form['sort_order'] != $this->relationType->sort_order ||
               $this->form['is_active'] !== $this->relationType->is_active ||
               $this->form['is_directional'] !== $this->relationType->is_directional ||
               $this->form['is_hierarchical'] !== $this->relationType->is_hierarchical ||
               $this->form['is_reciprocal'] !== $this->relationType->is_reciprocal;
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.code' => 'required|string|max:255|unique:organization_entity_relation_types,code,' . $this->relationType->id,
            'form.description' => 'nullable|string',
            'form.icon' => 'nullable|string|max:255',
            'form.sort_order' => 'integer|min:0',
            'form.is_active' => 'boolean',
            'form.is_directional' => 'boolean',
            'form.is_hierarchical' => 'boolean',
            'form.is_reciprocal' => 'boolean',
        ]);

        try {
            $this->relationType->update($this->form);
            $this->loadForm();
            $this->dispatch('toast', message: 'Relation Type gespeichert');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Speichern: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function delete()
    {
        try {
            $this->relationType->delete();
            $this->dispatch('toast', message: 'Relation Type gelöscht');
            return redirect()->route('organization.settings.relation-types.index');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Löschen: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function render()
    {
        return view('organization::livewire.settings.relation-type.show')
            ->layout('platform::layouts.app');
    }
}
