<?php

namespace Platform\Organization\Livewire\Customer;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationCustomer;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\DimensionLinkService;

class Show extends Component
{
    public OrganizationCustomer $customer;
    public array $form = [];
    public bool $isEditing = false;

    public function mount(OrganizationCustomer $customer)
    {
        $this->customer = $customer;
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'code' => $this->customer->code,
            'name' => $this->customer->name,
            'description' => $this->customer->description,
            'root_entity_id' => $this->customer->root_entity_id,
            'is_active' => $this->customer->is_active,
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

        $this->customer->update($this->form);
        $this->loadForm();

        session()->flash('message', 'Kunde erfolgreich aktualisiert.');
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->customer->name ||
               $this->form['code'] !== $this->customer->code ||
               $this->form['description'] !== $this->customer->description ||
               $this->form['root_entity_id'] != $this->customer->root_entity_id ||
               $this->form['is_active'] !== $this->customer->is_active;
    }

    #[Computed]
    public function linkedContexts()
    {
        $service = new DimensionLinkService();
        return $service->getLinkedContexts('customers', $this->customer->id);
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
        return view('organization::livewire.customer.show')
            ->layout('platform::layouts.app');
    }
}
