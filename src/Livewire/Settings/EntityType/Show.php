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

        return $accessibleModules->mapWithKeys(function ($module) {
            return [$module['key'] => $module['title'] ?? ucfirst($module['key'])];
        })->toArray();
    }

    #[Computed]
    public function availableModels()
    {
        $models = [];
        $accessibleModuleKeys = array_keys($this->modules);

        // Basis-Pfad: Von der aktuellen Datei aus zum platform-Verzeichnis
        // __DIR__ ist: .../platform/modules/organization/src/Livewire/Settings/EntityType
        // Wir wollen: .../platform/modules/{moduleKey}/src/Models
        // 5x dirname: organization/src/Livewire/Settings/EntityType -> organization/src/Livewire/Settings -> organization/src/Livewire -> organization/src -> organization -> modules -> platform
        $baseDir = dirname(dirname(dirname(dirname(dirname(__DIR__))))); // platform/
        
        // Fallback: Versuche auch über base_path
        if (!is_dir($baseDir . '/modules')) {
            $baseDir = base_path('platform');
            if (!is_dir($baseDir . '/modules')) {
                $baseDir = base_path();
            }
        }
        
        foreach ($accessibleModuleKeys as $moduleKey) {
            // Versuche Models-Verzeichnis zu finden
            $moduleConfig = PlatformCore::getModule($moduleKey);
            if (!$moduleConfig) continue;

            // Standard-Namespace für Models: Platform\{Module}\Models
            $moduleNamespace = 'Platform\\' . ucfirst($moduleKey) . '\\Models';
            
            // Versuche verschiedene Pfade
            $possiblePaths = [
                $baseDir . "/modules/{$moduleKey}/src/Models",
                base_path("platform/modules/{$moduleKey}/src/Models"),
                base_path("modules/{$moduleKey}/src/Models"),
            ];

            // Spezialfall: core module
            if ($moduleKey === 'core') {
                $moduleNamespace = 'Platform\\Core\\Models';
                $possiblePaths = [
                    $baseDir . "/core/src/Models",
                    base_path("platform/core/src/Models"),
                    base_path("core/src/Models"),
                ];
            }

            $modulePath = null;
            foreach ($possiblePaths as $path) {
                $realPath = realpath($path);
                if ($realPath && is_dir($realPath)) {
                    $modulePath = $realPath;
                    break;
                }
            }

            if (!$modulePath || !is_dir($modulePath)) {
                \Log::debug("EntityType Show: Kein Models-Verzeichnis gefunden für Modul '{$moduleKey}'. Geprüfte Pfade: " . implode(', ', $possiblePaths));
                continue;
            }

            // Scanne Models-Verzeichnis
            \Log::debug("EntityType Show: Scanne Models-Verzeichnis: {$modulePath}");
            $foundFiles = 0;
            foreach (scandir($modulePath) as $file) {
                if (!str_ends_with($file, '.php') || $file === '.' || $file === '..') continue;
                $foundFiles++;

                $className = $moduleNamespace . '\\' . pathinfo($file, PATHINFO_FILENAME);
                if (!class_exists($className)) {
                    \Log::debug("EntityType Show: Klasse existiert nicht: {$className}");
                    continue;
                }

                // Prüfe ob es ein Eloquent Model ist
                try {
                    if (!is_subclass_of($className, \Illuminate\Database\Eloquent\Model::class)) {
                        \Log::debug("EntityType Show: {$className} ist kein Eloquent Model");
                        continue;
                    }

                    // Versuche Instanz zu erstellen (ohne DB-Zugriff)
                    $reflection = new \ReflectionClass($className);
                    if ($reflection->isAbstract() || $reflection->isInterface()) {
                        \Log::debug("EntityType Show: {$className} ist abstrakt oder Interface");
                        continue;
                    }

                    $models[] = [
                        'module_key' => $moduleKey,
                        'class' => $className,
                        'name' => class_basename($className),
                    ];
                    \Log::debug("EntityType Show: Model gefunden: {$className}");
                } catch (\Throwable $e) {
                    // Überspringe Models, die nicht geladen werden können
                    \Log::debug("EntityType Show: Fehler beim Laden von {$className}: " . $e->getMessage());
                    continue;
                }
            }
            \Log::debug("EntityType Show: Modul '{$moduleKey}': {$foundFiles} PHP-Dateien gefunden, " . count(array_filter($models, fn($m) => $m['module_key'] === $moduleKey)) . " Models hinzugefügt");
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

