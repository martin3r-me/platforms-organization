<?php

namespace Platform\Organization\Livewire\Settings\EntityType;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeModelMapping;
use Platform\Core\PlatformCore;

class Show extends Component
{
    public OrganizationEntityType $entityType;
    public $activeTab = 'details';

    // Model Mapping Form
    public $modelMappingForm = [
        'module_key' => '',
        'model_class' => '',
        'is_bidirectional' => true,
        'is_active' => true,
        'sort_order' => 0,
    ];
    public $modelMappingModalOpen = false;
    public $editingMappingId = null;

    public function mount(OrganizationEntityType $entityType)
    {
        $this->entityType = $entityType->load('group', 'modelMappings');
    }

    #[Computed]
    public function modules()
    {
        return collect(PlatformCore::getModules())
            ->mapWithKeys(function ($module) {
                return [$module['key'] => $module['title'] ?? ucfirst($module['key'])];
            })
            ->toArray();
    }

    #[Computed]
    public function modelMappings()
    {
        return $this->entityType->modelMappings()
            ->active()
            ->ordered()
            ->get()
            ->groupBy('module_key');
    }

    public function openModelMappingModal($mappingId = null)
    {
        if ($mappingId) {
            $mapping = OrganizationEntityTypeModelMapping::findOrFail($mappingId);
            $this->editingMappingId = $mappingId;
            $this->modelMappingForm = [
                'module_key' => $mapping->module_key,
                'model_class' => $mapping->model_class,
                'is_bidirectional' => $mapping->is_bidirectional,
                'is_active' => $mapping->is_active,
                'sort_order' => $mapping->sort_order,
            ];
        } else {
            $this->reset('modelMappingForm', 'editingMappingId');
            $this->modelMappingForm['is_bidirectional'] = true;
            $this->modelMappingForm['is_active'] = true;
        }
        $this->modelMappingModalOpen = true;
    }

    public function closeModelMappingModal()
    {
        $this->modelMappingModalOpen = false;
        $this->reset('modelMappingForm', 'editingMappingId');
    }

    public function saveModelMapping()
    {
        $this->validate([
            'modelMappingForm.module_key' => 'required|string',
            'modelMappingForm.model_class' => 'required|string',
            'modelMappingForm.is_bidirectional' => 'boolean',
            'modelMappingForm.is_active' => 'boolean',
            'modelMappingForm.sort_order' => 'integer|min:0',
        ]);

        if ($this->editingMappingId) {
            $mapping = OrganizationEntityTypeModelMapping::findOrFail($this->editingMappingId);
            $mapping->update($this->modelMappingForm);
        } else {
            OrganizationEntityTypeModelMapping::create([
                'entity_type_id' => $this->entityType->id,
                ...$this->modelMappingForm,
            ]);
        }

        $this->closeModelMappingModal();
        $this->entityType->refresh();
        $this->dispatch('toast', message: 'Model Mapping gespeichert');
    }

    public function deleteModelMapping($mappingId)
    {
        $mapping = OrganizationEntityTypeModelMapping::findOrFail($mappingId);
        $mapping->delete();
        $this->entityType->refresh();
        $this->dispatch('toast', message: 'Model Mapping gelöscht');
    }

    public function render()
    {
        return view('organization::livewire.settings.entity-type.show')
            ->layout('platform::layouts.app');
    }
}

