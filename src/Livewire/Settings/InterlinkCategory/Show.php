<?php

namespace Platform\Organization\Livewire\Settings\InterlinkCategory;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationInterlinkCategory;

class Show extends Component
{
    public OrganizationInterlinkCategory $category;

    public array $form = [];

    public function mount(OrganizationInterlinkCategory $interlinkCategory)
    {
        $this->category = $interlinkCategory;
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name' => $this->category->name,
            'code' => $this->category->code,
            'description' => $this->category->description,
            'icon' => $this->category->icon,
            'sort_order' => $this->category->sort_order,
            'is_active' => $this->category->is_active,
        ];
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->category->name ||
               $this->form['code'] !== $this->category->code ||
               $this->form['description'] !== $this->category->description ||
               $this->form['icon'] !== $this->category->icon ||
               $this->form['sort_order'] != $this->category->sort_order ||
               $this->form['is_active'] !== $this->category->is_active;
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.code' => 'required|string|max:255|unique:organization_interlink_categories,code,' . $this->category->id,
            'form.description' => 'nullable|string',
            'form.icon' => 'nullable|string|max:255',
            'form.sort_order' => 'integer|min:0',
            'form.is_active' => 'boolean',
        ]);

        try {
            $this->category->update($this->form);
            $this->loadForm();
            $this->dispatch('toast', message: 'Interlink-Kategorie gespeichert');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Speichern: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function delete()
    {
        try {
            $this->category->delete();
            $this->dispatch('toast', message: 'Interlink-Kategorie gelöscht');
            return redirect()->route('organization.settings.interlink-categories.index');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Löschen: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function render()
    {
        return view('organization::livewire.settings.interlink-category.show')
            ->layout('platform::layouts.app');
    }
}
