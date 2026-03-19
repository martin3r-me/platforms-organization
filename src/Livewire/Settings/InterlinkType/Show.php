<?php

namespace Platform\Organization\Livewire\Settings\InterlinkType;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationInterlinkType;

class Show extends Component
{
    public OrganizationInterlinkType $interlinkType;

    public array $form = [];

    public function mount(OrganizationInterlinkType $interlinkType)
    {
        $this->interlinkType = $interlinkType;
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name' => $this->interlinkType->name,
            'code' => $this->interlinkType->code,
            'description' => $this->interlinkType->description,
            'icon' => $this->interlinkType->icon,
            'sort_order' => $this->interlinkType->sort_order,
            'is_active' => $this->interlinkType->is_active,
        ];
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->interlinkType->name ||
               $this->form['code'] !== $this->interlinkType->code ||
               $this->form['description'] !== $this->interlinkType->description ||
               $this->form['icon'] !== $this->interlinkType->icon ||
               $this->form['sort_order'] != $this->interlinkType->sort_order ||
               $this->form['is_active'] !== $this->interlinkType->is_active;
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.code' => 'required|string|max:255|unique:organization_interlink_types,code,' . $this->interlinkType->id,
            'form.description' => 'nullable|string',
            'form.icon' => 'nullable|string|max:255',
            'form.sort_order' => 'integer|min:0',
            'form.is_active' => 'boolean',
        ]);

        try {
            $this->interlinkType->update($this->form);
            $this->loadForm();
            $this->dispatch('toast', message: 'Interlink-Typ gespeichert');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Speichern: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function delete()
    {
        try {
            $this->interlinkType->delete();
            $this->dispatch('toast', message: 'Interlink-Typ gelöscht');
            return redirect()->route('organization.settings.interlink-types.index');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Löschen: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function render()
    {
        return view('organization::livewire.settings.interlink-type.show')
            ->layout('platform::layouts.app');
    }
}
