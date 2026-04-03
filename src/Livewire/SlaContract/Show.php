<?php

namespace Platform\Organization\Livewire\SlaContract;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSlaContract;

class Show extends Component
{
    public OrganizationSlaContract $slaContract;
    public array $form = [];
    public string $activeTab = 'details';

    public function mount(OrganizationSlaContract $slaContract)
    {
        $this->slaContract = $slaContract->load(['user']);
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name' => $this->slaContract->name,
            'description' => $this->slaContract->description,
            'response_time_hours' => $this->slaContract->response_time_hours,
            'resolution_time_hours' => $this->slaContract->resolution_time_hours,
            'error_tolerance_percent' => $this->slaContract->error_tolerance_percent,
            'is_active' => $this->slaContract->is_active,
        ];
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->slaContract->name ||
               $this->form['description'] !== $this->slaContract->description ||
               $this->form['response_time_hours'] != $this->slaContract->response_time_hours ||
               $this->form['resolution_time_hours'] != $this->slaContract->resolution_time_hours ||
               $this->form['error_tolerance_percent'] != $this->slaContract->error_tolerance_percent ||
               $this->form['is_active'] !== $this->slaContract->is_active;
    }

    #[Computed]
    public function linkedInterlinks()
    {
        return $this->slaContract->relationshipInterlinks()
            ->with([
                'entityRelationship.fromEntity.type',
                'entityRelationship.toEntity.type',
                'entityRelationship.relationType',
                'interlink',
            ])
            ->get();
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.response_time_hours' => 'nullable|integer|min:1',
            'form.resolution_time_hours' => 'nullable|integer|min:1',
            'form.error_tolerance_percent' => 'nullable|integer|min:0|max:100',
            'form.is_active' => 'boolean',
        ]);

        try {
            $this->slaContract->update([
                'name' => $this->form['name'],
                'description' => $this->form['description'] ?: null,
                'response_time_hours' => $this->form['response_time_hours'] ?: null,
                'resolution_time_hours' => $this->form['resolution_time_hours'] ?: null,
                'error_tolerance_percent' => $this->form['error_tolerance_percent'],
                'is_active' => $this->form['is_active'],
            ]);
            $this->slaContract->refresh();
            $this->loadForm();
            $this->dispatch('toast', message: 'SLA-Vertrag gespeichert');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Speichern: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function delete()
    {
        try {
            $this->slaContract->delete();
            $this->dispatch('toast', message: 'SLA-Vertrag gelöscht');
            return redirect()->route('organization.sla-contracts.index');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim Löschen: ' . $e->getMessage(), variant: 'danger');
        }
    }

    public function render()
    {
        return view('organization::livewire.sla-contract.show')
            ->layout('platform::layouts.app');
    }
}
