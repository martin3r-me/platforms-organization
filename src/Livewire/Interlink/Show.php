<?php

namespace Platform\Organization\Livewire\Interlink;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationInterlink;
use Platform\Organization\Models\OrganizationInterlinkCategory;
use Platform\Organization\Models\OrganizationInterlinkType;
use Platform\Organization\Models\OrganizationEntityRelationshipInterlink;

class Show extends Component
{
    public OrganizationInterlink $interlink;
    public array $form = [];
    public string $activeTab = 'details';

    public function mount(OrganizationInterlink $interlink)
    {
        $this->interlink = $interlink->load(['category', 'type', 'user']);
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name' => $this->interlink->name,
            'description' => $this->interlink->description,
            'category_id' => $this->interlink->category_id,
            'type_id' => $this->interlink->type_id,
            'is_bidirectional' => $this->interlink->is_bidirectional,
            'is_active' => $this->interlink->is_active,
            'valid_from' => $this->interlink->valid_from?->format('Y-m-d'),
            'valid_to' => $this->interlink->valid_to?->format('Y-m-d'),
        ];
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->interlink->name ||
               $this->form['description'] !== $this->interlink->description ||
               $this->form['category_id'] != $this->interlink->category_id ||
               $this->form['type_id'] != $this->interlink->type_id ||
               $this->form['is_bidirectional'] !== $this->interlink->is_bidirectional ||
               $this->form['is_active'] !== $this->interlink->is_active ||
               $this->form['valid_from'] !== $this->interlink->valid_from?->format('Y-m-d') ||
               $this->form['valid_to'] !== $this->interlink->valid_to?->format('Y-m-d');
    }

    #[Computed]
    public function availableCategories()
    {
        return OrganizationInterlinkCategory::active()->ordered()->get();
    }

    #[Computed]
    public function availableTypes()
    {
        return OrganizationInterlinkType::active()->ordered()->get();
    }

    #[Computed]
    public function linkedRelationships()
    {
        return OrganizationEntityRelationshipInterlink::query()
            ->where('interlink_id', $this->interlink->id)
            ->with([
                'entityRelationship.fromEntity.type',
                'entityRelationship.toEntity.type',
                'entityRelationship.relationType',
                'user',
            ])
            ->get();
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.category_id' => 'required|exists:organization_interlink_categories,id',
            'form.type_id' => 'required|exists:organization_interlink_types,id',
            'form.is_bidirectional' => 'boolean',
            'form.is_active' => 'boolean',
            'form.valid_from' => 'nullable|date',
            'form.valid_to' => 'nullable|date|after_or_equal:form.valid_from',
        ]);

        try {
            $this->interlink->update([
                'name' => $this->form['name'],
                'description' => $this->form['description'] ?: null,
                'category_id' => $this->form['category_id'],
                'type_id' => $this->form['type_id'],
                'is_bidirectional' => $this->form['is_bidirectional'],
                'is_active' => $this->form['is_active'],
                'valid_from' => $this->form['valid_from'] ?: null,
                'valid_to' => $this->form['valid_to'] ?: null,
            ]);
            $this->interlink->refresh();
            $this->loadForm();
            $this->dispatch('toast', message: 'Interlink gespeichert');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Speichern: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function delete()
    {
        try {
            $this->interlink->delete();
            $this->dispatch('toast', message: 'Interlink gelöscht');
            return redirect()->route('organization.interlinks.index');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Löschen: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function render()
    {
        return view('organization::livewire.interlink.show')
            ->layout('platform::layouts.app');
    }
}
