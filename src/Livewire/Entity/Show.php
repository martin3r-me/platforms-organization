<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationVsmSystem;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationVsmFunction;
use Platform\Organization\Models\OrganizationEntityTypeModelMapping;
use Platform\Organization\Models\OrganizationContext;
use Platform\Core\Models\Team;
use Platform\Core\Enums\TeamRole;

class Show extends Component
{
    public OrganizationEntity $entity;
    public array $form = [];
    public bool $showCreateTeamModal = false;
    public array $newTeam = [
        'name' => '',
        'parent_team_id' => null,
    ];

    public function mount(OrganizationEntity $entity)
    {
        $this->entity = $entity->load([
            'type.group', 
            'vsmSystem', 
            'costCenter', 
            'parent', 
            'children.type', 
            'team', 
            'user',
            'relationsFrom.toEntity.type',
            'relationsFrom.relationType',
            'relationsTo.fromEntity.type',
            'relationsTo.relationType',
            'contexts.contextable'
        ]);
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name' => $this->entity->name,
            'code' => $this->entity->code,
            'description' => $this->entity->description,
            'entity_type_id' => $this->entity->entity_type_id,
            'vsm_system_id' => $this->entity->vsm_system_id,
            'cost_center_id' => $this->entity->cost_center_id,
            'parent_entity_id' => $this->entity->parent_entity_id,
            'is_active' => $this->entity->is_active,
        ];
    }

    public function save()
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.code' => 'nullable|string|max:255',
            'form.description' => 'nullable|string',
            'form.entity_type_id' => 'required|exists:organization_entity_types,id',
            'form.vsm_system_id' => 'nullable|exists:organization_vsm_systems,id',
            'form.cost_center_id' => 'nullable|exists:organization_cost_centers,id',
            'form.parent_entity_id' => 'nullable|exists:organization_entities,id',
            'form.is_active' => 'boolean',
        ]);

        try {
            $this->entity->update($this->form);
            $this->loadForm(); // Reload form to reset dirty state
            
            session()->flash('message', 'Organisationseinheit erfolgreich aktualisiert.');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Speichern: ' . $e->getMessage());
        }
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== $this->entity->name ||
               $this->form['code'] !== $this->entity->code ||
               $this->form['description'] !== $this->entity->description ||
               $this->form['entity_type_id'] != $this->entity->entity_type_id ||
               $this->form['vsm_system_id'] != $this->entity->vsm_system_id ||
               $this->form['cost_center_id'] != $this->entity->cost_center_id ||
               $this->form['parent_entity_id'] != $this->entity->parent_entity_id ||
               $this->form['is_active'] !== $this->entity->is_active;
    }

    public function getEntityTypesProperty()
    {
        return OrganizationEntityType::active()
            ->ordered()
            ->with('group')
            ->get()
            ->groupBy('group.name');
    }

    public function getVsmSystemsProperty()
    {
        return OrganizationVsmSystem::active()
            ->ordered()
            ->get();
    }

    public function getCostCentersProperty()
    {
        return OrganizationCostCenter::active()
            ->where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('name')
            ->get();
    }

    public function getAvailableCostCentersProperty()
    {
        return OrganizationCostCenter::getForEntityWithHierarchy(
            auth()->user()->currentTeam->id,
            $this->entity->id
        );
    }

    public function getAvailableVsmFunctionsProperty()
    {
        return OrganizationVsmFunction::getForEntityWithHierarchy(
            auth()->user()->currentTeam->id,
            $this->entity->id
        );
    }

    public function getParentEntitiesProperty()
    {
        return OrganizationEntity::active()
            ->forTeam(auth()->user()->currentTeam->id)
            ->where('id', '!=', $this->entity->id) // Exclude self
            ->with('type')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function allowedModelMappings()
    {
        return OrganizationEntityTypeModelMapping::where('entity_type_id', $this->entity->entity_type_id)
            ->active()
            ->where('is_bidirectional', true) // Nur bidirektionale Mappings
            ->ordered()
            ->get()
            ->groupBy('module_key');
    }

    #[Computed]
    public function availableModuleEntities()
    {
        $entities = [];
        $team = auth()->user()->currentTeamRelation;
        if (!$team) {
            return [];
        }

        // Hole alle bereits verlinkten Module-Entities
        $linkedContexts = OrganizationContext::where('organization_entity_id', $this->entity->id)
            ->where('is_active', true)
            ->get()
            ->map(function($context) {
                return $context->contextable_type . ':' . $context->contextable_id;
            })
            ->toArray();

        foreach ($this->allowedModelMappings as $moduleKey => $mappings) {
            foreach ($mappings as $mapping) {
                $modelClass = $mapping->model_class;
                
                if (!class_exists($modelClass)) {
                    continue;
                }

                try {
                    // Lade alle Instanzen dieses Models
                    $query = $modelClass::query();
                    
                    // Prüfe ob das Model team-basiert ist
                    if (method_exists($modelClass, 'scopeForTeam')) {
                        $query->forTeam($team->id);
                    } elseif (method_exists($query->getModel(), 'getTable') && 
                              \Illuminate\Support\Facades\Schema::hasColumn($query->getModel()->getTable(), 'team_id')) {
                        $query->where('team_id', $team->id);
                    }

                    $instances = $query->get();
                    $user = auth()->user();

                    foreach ($instances as $instance) {
                        // Prüfe ob der User Zugriff auf diese Entity hat
                        if (!$this->userCanAccessEntity($user, $instance)) {
                            continue;
                        }

                        $contextKey = $modelClass . ':' . $instance->id;
                        
                        // Überspringe bereits verlinkte Entities
                        if (in_array($contextKey, $linkedContexts)) {
                            continue;
                        }

                        // Versuche einen Namen zu extrahieren
                        $name = $instance->name ?? $instance->title ?? $instance->email ?? 'Unbenannt';
                        if (method_exists($instance, 'getName')) {
                            $name = $instance->getName();
                        }

                        $entities[] = [
                            'model_class' => $modelClass,
                            'model_name' => class_basename($modelClass),
                            'module_key' => $moduleKey,
                            'id' => $instance->id,
                            'name' => $name,
                            'instance' => $instance,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Überspringe Models, die nicht geladen werden können
                    continue;
                }
            }
        }

        // Sortiere nach Modul, dann nach Model, dann nach Name
        usort($entities, function($a, $b) {
            if ($a['module_key'] !== $b['module_key']) {
                return strcmp($a['module_key'], $b['module_key']);
            }
            if ($a['model_name'] !== $b['model_name']) {
                return strcmp($a['model_name'], $b['model_name']);
            }
            return strcmp($a['name'], $b['name']);
        });

        return collect($entities)->groupBy('model_class');
    }

    #[Computed]
    public function linkedModuleEntities()
    {
        $user = auth()->user();
        
        return OrganizationContext::where('organization_entity_id', $this->entity->id)
            ->where('is_active', true)
            ->with('contextable')
            ->get()
            ->map(function($context) use ($user) {
                $contextable = $context->contextable;
                if (!$contextable) {
                    return null;
                }

                // Prüfe ob der User Zugriff auf diese Entity hat
                if (!$this->userCanAccessEntity($user, $contextable)) {
                    return null;
                }

                $name = $contextable->name ?? $contextable->title ?? $contextable->email ?? 'Unbenannt';
                if (method_exists($contextable, 'getName')) {
                    $name = $contextable->getName();
                }

                return [
                    'context_id' => $context->id,
                    'model_class' => $context->contextable_type,
                    'model_name' => class_basename($context->contextable_type),
                    'id' => $contextable->id,
                    'name' => $name,
                ];
            })
            ->filter()
            ->groupBy('model_class');
    }

    public function linkModuleEntity($modelClass, $moduleEntityId)
    {
        if (!$modelClass || !$moduleEntityId) {
            return;
        }

        if (!class_exists($modelClass)) {
            session()->flash('error', 'Model-Klasse nicht gefunden.');
            return;
        }

        try {
            $moduleEntity = $modelClass::findOrFail($moduleEntityId);
            $user = auth()->user();
            
            // Prüfe ob der User Zugriff auf diese Entity hat
            if (!$this->userCanAccessEntity($user, $moduleEntity)) {
                session()->flash('error', 'Sie haben keinen Zugriff auf diese Entity.');
                return;
            }
            
            // Prüfe ob das Model das HasOrganizationContexts Trait verwendet
            if (!in_array(\Platform\Organization\Traits\HasOrganizationContexts::class, class_uses_recursive($moduleEntity))) {
                session()->flash('error', 'Dieses Model unterstützt keine Organization Contexts.');
                return;
            }

            // Verlinke die Module Entity mit dieser Organization Entity
            $moduleEntity->attachOrganizationContext($this->entity);

            $this->entity->refresh();
            session()->flash('message', 'Module Entity erfolgreich verlinkt.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Fehler beim Verlinken: ' . $e->getMessage());
        }
    }

    public function unlinkModuleEntity($contextId)
    {
        try {
            $context = OrganizationContext::findOrFail($contextId);
            $contextable = $context->contextable;
            
            if ($contextable && in_array(\Platform\Organization\Traits\HasOrganizationContexts::class, class_uses_recursive($contextable))) {
                $contextable->detachOrganizationContext();
            } else {
                $context->delete();
            }

            $this->entity->refresh();
            session()->flash('message', 'Verlinkung erfolgreich entfernt.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Fehler beim Entfernen: ' . $e->getMessage());
        }
    }

    /**
     * Prüft ob der User Zugriff auf eine Module Entity hat
     */
    protected function userCanAccessEntity($user, $instance): bool
    {
        // 1. Prüfe über Policy (falls vorhanden)
        $policyClass = $this->getPolicyClass(get_class($instance));
        if ($policyClass && class_exists($policyClass)) {
            try {
                $policy = app($policyClass);
                if (method_exists($policy, 'view')) {
                    return $policy->view($user, $instance);
                }
            } catch (\Throwable $e) {
                // Policy-Check fehlgeschlagen, weiter mit anderen Checks
            }
        }

        // 2. Owner-Check: Prüfe ob user_id existiert und der User der Owner ist
        if (isset($instance->user_id) && $instance->user_id === $user->id) {
            return true;
        }

        // 3. Team-Mitgliedschaft: Prüfe ob das Model team-basiert ist und User im Team ist
        if (isset($instance->team_id)) {
            $userTeams = $user->teams()->pluck('teams.id')->toArray();
            if (in_array($instance->team_id, $userTeams)) {
                return true;
            }
        }

        // 4. Spezifische Checks für bekannte Models
        $modelClass = get_class($instance);
        
        // PlannerProject: Prüfe Projekt-Mitgliedschaft
        if ($modelClass === \Platform\Planner\Models\PlannerProject::class) {
            return $instance->projectUsers()
                ->where('user_id', $user->id)
                ->exists();
        }

        // PlannerTask: Prüfe über Projekt oder Owner
        if ($modelClass === \Platform\Planner\Models\PlannerTask::class) {
            // Owner hat Zugriff
            if (isset($instance->user_id) && $instance->user_id === $user->id) {
                return true;
            }
            // Zugewiesener User hat Zugriff
            if (isset($instance->user_in_charge_id) && $instance->user_in_charge_id === $user->id) {
                return true;
            }
            // Prüfe Projekt-Mitgliedschaft
            if ($instance->project_id) {
                $project = $instance->project;
                if ($project) {
                    return $project->projectUsers()
                        ->where('user_id', $user->id)
                        ->exists();
                }
            }
        }

        // User Model: Nur der User selbst
        if ($modelClass === \Platform\Core\Models\User::class) {
            return $instance->id === $user->id;
        }

        // Team Model: Prüfe Team-Mitgliedschaft
        if ($modelClass === \Platform\Core\Models\Team::class) {
            return $user->teams()->where('teams.id', $instance->id)->exists();
        }

        // Standard: Wenn team-basiert und User im Team, dann Zugriff
        // Ansonsten: Kein Zugriff (sicherer Default)
        return false;
    }

    /**
     * Versucht die Policy-Klasse für ein Model zu finden
     */
    protected function getPolicyClass(string $modelClass): ?string
    {
        // Standard Laravel Policy-Naming: ModelPolicy
        $policyClass = $modelClass . 'Policy';
        if (class_exists($policyClass)) {
            return $policyClass;
        }

        // Alternative: Namespace-basiert
        $namespace = substr($modelClass, 0, strrpos($modelClass, '\\'));
        $modelName = class_basename($modelClass);
        $policyClass = $namespace . '\\Policies\\' . $modelName . 'Policy';
        
        if (class_exists($policyClass)) {
            return $policyClass;
        }

        return null;
    }

    public function openCreateTeamModal()
    {
        $this->showCreateTeamModal = true;
        $this->newTeam = [
            'name' => $this->entity->code ?? $this->entity->name,
            'parent_team_id' => null,
        ];
    }

    public function closeCreateTeamModal()
    {
        $this->showCreateTeamModal = false;
        $this->newTeam = [
            'name' => '',
            'parent_team_id' => null,
        ];
    }

    public function createTeam()
    {
        $this->validate([
            'newTeam.name' => 'required|string|max:255',
            'newTeam.parent_team_id' => 'nullable|exists:teams,id',
        ]);

        try {
            $user = auth()->user();
            
            // Prüfe ob parent_team_id gesetzt ist und ob der User Zugriff darauf hat
            if ($this->newTeam['parent_team_id']) {
                $parentTeam = Team::find($this->newTeam['parent_team_id']);
                if (!$parentTeam || !$user->teams()->where('teams.id', $parentTeam->id)->exists()) {
                    session()->flash('error', 'Sie haben keinen Zugriff auf das ausgewählte Parent-Team.');
                    return;
                }
            }

            $team = Team::create([
                'name' => $this->newTeam['name'],
                'user_id' => $user->id,
                'parent_team_id' => $this->newTeam['parent_team_id'] ?: null,
                'personal_team' => false,
            ]);

            // Füge den User als Owner zum Team hinzu
            $user->teams()->attach($team->id, ['role' => TeamRole::OWNER->value]);

            // Verlinke das Team direkt mit der Entität
            OrganizationContext::updateOrCreate(
                [
                    'contextable_type' => Team::class,
                    'contextable_id' => $team->id,
                    'organization_entity_id' => $this->entity->id,
                ],
                [
                    'team_id' => $user->currentTeamRelation?->id,
                    'is_active' => true,
                ]
            );

            $this->closeCreateTeamModal();
            $this->entity->refresh();
            session()->flash('message', 'Team erfolgreich erstellt und mit der Entität verlinkt.');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Erstellen des Teams: ' . $e->getMessage());
        }
    }

    #[Computed]
    public function availableTeams()
    {
        $user = auth()->user();
        if (!$user) {
            return collect();
        }

        // Nur Root-Teams können als Parent-Teams verwendet werden
        return Team::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->whereNull('parent_team_id')
        ->orderBy('name')
        ->get();
    }

    public function render()
    {
        return view('organization::livewire.entity.show')
            ->layout('platform::layouts.app');
    }
}
