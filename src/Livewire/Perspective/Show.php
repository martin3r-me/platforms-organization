<?php

namespace Platform\Organization\Livewire\Perspective;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationPerspective;

class Show extends Component
{
    public OrganizationPerspective $perspective;
    public array $form = [];
    public bool $isEditing = false;

    public function mount(OrganizationPerspective $perspective)
    {
        $this->perspective = $perspective;
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name' => $this->perspective->name,
            'description' => $this->perspective->description,
            'is_default' => $this->perspective->is_default,
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
            'form.description' => 'nullable|string',
            'form.is_default' => 'boolean',
        ]);

        $this->perspective->update($this->form);
        $this->perspective->refresh();
        $this->loadForm();

        session()->flash('message', 'Perspektive erfolgreich aktualisiert.');
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->perspective->name ||
               $this->form['description'] !== $this->perspective->description ||
               $this->form['is_default'] !== $this->perspective->is_default;
    }

    #[Computed]
    public function dimensionLinksCount()
    {
        return $this->perspective->dimensionLinks()->count();
    }

    public function render()
    {
        return view('organization::livewire.perspective.show')
            ->layout('platform::layouts.app');
    }
}
