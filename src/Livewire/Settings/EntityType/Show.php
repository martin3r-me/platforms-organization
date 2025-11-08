<?php

namespace Platform\Organization\Livewire\Settings\EntityType;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeModelMapping;
use Platform\Core\PlatformCore;
use Platform\Core\Models\Module;

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
        $user = Auth::user();
        $baseTeam = $user->currentTeamRelation;
        if (!$baseTeam) {
            return [];
        }

        $rootTeam = $baseTeam->getRootTeam();
        $rootTeamId = $rootTeam->id;
        $baseTeamId = $baseTeam->id;

        $allModules = PlatformCore::getVisibleModules();

        // Filtere Module nach Berechtigung (nur Module, auf die der User Zugriff hat)
        $accessibleModules = collect($allModules)->filter(function($module) use ($user, $baseTeam, $baseTeamId, $rootTeam, $rootTeamId) {
            $moduleModel = Module::where('key', $module['key'])->first();
            if (!$moduleModel) return false;

            if ($moduleModel->isRootScoped()) {
                $userAllowed = $user->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('team_id', $rootTeamId)
                    ->wherePivot('enabled', true)
                    ->exists();
                $teamAllowed = $rootTeam->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('enabled', true)
                    ->exists();
            } else {
                $userAllowed = $user->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('team_id', $baseTeamId)
                    ->wherePivot('enabled', true)
                    ->exists();
                $teamAllowed = $baseTeam->modules()
                    ->where('module_id', $moduleModel->id)
                    ->wherePivot('enabled', true)
                    ->exists();
            }

            return $userAllowed || $teamAllowed;
        });

        $modulesArray = $accessibleModules->mapWithKeys(function ($module) {
            return [$module['key'] => $module['title'] ?? ucfirst($module['key'])];
        })->toArray();

        // Core-Modul explizit hinzufügen (für User und Team Models)
        if (!isset($modulesArray['core'])) {
            $modulesArray['core'] = 'Core';
        }

        return $modulesArray;
    }

    #[Computed]
    public function availableModels()
    {
        $models = [];
        $accessibleModuleKeys = array_keys($this->modules);

        // Nutze Laravel's Class Discovery: Alle geladenen Klassen durchgehen
        $allClasses = get_declared_classes();
        
        foreach ($allClasses as $className) {
            // Prüfe ob es ein Eloquent Model ist
            if (!is_subclass_of($className, \Illuminate\Database\Eloquent\Model::class)) {
                continue;
            }

            // Prüfe ob die Klasse zu einem der verfügbaren Module gehört
            $moduleKey = null;
            foreach ($accessibleModuleKeys as $key) {
                // Standard-Namespace: Platform\{Module}\Models
                $moduleNamespace = 'Platform\\' . ucfirst($key) . '\\Models';
                
                // Spezialfall: core module
                if ($key === 'core') {
                    $moduleNamespace = 'Platform\\Core\\Models';
                }
                
                if (str_starts_with($className, $moduleNamespace . '\\')) {
                    $moduleKey = $key;
                    break;
                }
            }

            if (!$moduleKey) {
                continue;
            }

            // Prüfe ob die Klasse abstrakt oder ein Interface ist
            try {
                $reflection = new \ReflectionClass($className);
                if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                    continue;
                }

                $models[] = [
                    'module_key' => $moduleKey,
                    'class' => $className,
                    'name' => class_basename($className),
                ];
            } catch (\Throwable $e) {
                // Überspringe Models, die nicht geladen werden können
                continue;
            }
        }

        // Sortiere nach Modul und dann nach Model-Name
        usort($models, function($a, $b) {
            if ($a['module_key'] !== $b['module_key']) {
                return strcmp($a['module_key'], $b['module_key']);
            }
            return strcmp($a['name'], $b['name']);
        });

        return $models;
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

    public function updatedModelMappingFormModuleKey()
    {
        // Reset model_class wenn Modul geändert wird
        $this->modelMappingForm['model_class'] = '';
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

