<?php

namespace Platform\Organization\Livewire\Settings\EntityType;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeGroup;

class Show extends Component
{
    public OrganizationEntityType $entityType;

    public array $form = [];

    public function mount(OrganizationEntityType $entityType)
    {
        $this->entityType = $entityType->load('group');
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name' => $this->entityType->name,
            'code' => $this->entityType->code,
            'description' => $this->entityType->description,
            'icon' => $this->entityType->icon,
            'sort_order' => $this->entityType->sort_order,
            'is_active' => $this->entityType->is_active,
            'entity_type_group_id' => $this->entityType->entity_type_group_id,
        ];
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->entityType->name ||
               $this->form['code'] !== $this->entityType->code ||
               $this->form['description'] !== $this->entityType->description ||
               $this->form['icon'] !== $this->entityType->icon ||
               $this->form['sort_order'] != $this->entityType->sort_order ||
               $this->form['is_active'] !== $this->entityType->is_active ||
               $this->form['entity_type_group_id'] != $this->entityType->entity_type_group_id;
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.code' => 'required|string|max:255|unique:organization_entity_types,code,' . $this->entityType->id,
            'form.description' => 'nullable|string',
            'form.icon' => 'nullable|string|max:255',
            'form.sort_order' => 'integer|min:0',
            'form.is_active' => 'boolean',
            'form.entity_type_group_id' => 'nullable|exists:organization_entity_type_groups,id',
        ]);

        try {
            $this->entityType->update($this->form);
            $this->loadForm();
            $this->dispatch('toast', message: 'Entity Type gespeichert');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Speichern: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function delete()
    {
        // Block if active entities still use this type
        $hasActiveEntities = OrganizationEntity::query()
            ->where('entity_type_id', $this->entityType->id)
            ->exists();
        if ($hasActiveEntities) {
            $this->dispatch('toast', message: 'Entity Type wird noch von aktiven Entities verwendet und kann nicht gelöscht werden.', variant: 'danger');
            return;
        }

        try {
            // Force-delete soft-deleted entities to clear the DB constraint
            OrganizationEntity::onlyTrashed()
                ->where('entity_type_id', $this->entityType->id)
                ->forceDelete();

            $this->entityType->delete();
            $this->dispatch('toast', message: 'Entity Type gelöscht');
            return redirect()->route('organization.settings.entity-types.index');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Löschen: ' . $e->getMessage(), variant: 'danger');
        }
    }

    #[Computed]
    public function entityTypeGroups()
    {
        return OrganizationEntityTypeGroup::active()
            ->ordered()
            ->get();
    }

    public function render()
    {
        return view('organization::livewire.settings.entity-type.show')
            ->layout('platform::layouts.app');
    }
}

