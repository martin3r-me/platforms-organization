<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationEntityType;
use Illuminate\Database\Eloquent\Relations\Relation;
use Platform\Organization\Models\OrganizationVsmSystem;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationVsmFunction;
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

    protected array $linkTypeConfig = [
        'project' => ['label' => 'Projekte', 'icon' => 'folder', 'route' => 'planner.projects.show'],
        'planner_task' => ['label' => 'Aufgaben', 'icon' => 'clipboard-document-check', 'route' => null],
        'helpdesk_ticket' => ['label' => 'Tickets', 'icon' => 'ticket', 'route' => null],
        'hcm_employee' => ['label' => 'Mitarbeiter', 'icon' => 'user', 'route' => null],
        'rec_applicant' => ['label' => 'Bewerber', 'icon' => 'user-plus', 'route' => null],
        'rec_position' => ['label' => 'Positionen', 'icon' => 'briefcase', 'route' => null],
        'sheets_spreadsheet' => ['label' => 'Spreadsheets', 'icon' => 'table-cells', 'route' => null],
    ];

    public function mount(OrganizationEntity $entity)
    {
        $this->entity = $entity->load([
            'type.group',
            'vsmSystem',
            'costCenter',
            'parent',
            'children.type',
            'children.children',
            'team',
            'user',
            'entityLinks',
            'relationsFrom.toEntity.type',
            'relationsFrom.relationType',
            'relationsTo.fromEntity.type',
            'relationsTo.relationType'
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

    #[Computed]
    public function entityLinksGrouped()
    {
        $morphMap = Relation::morphMap();

        $links = OrganizationEntityLink::where('entity_id', $this->entity->id)->get();

        // Nur Links behalten, deren Morph-Typ auflösbar ist
        $resolvable = $links->filter(function ($link) use ($morphMap) {
            $type = $link->linkable_type;
            return isset($morphMap[$type]) || class_exists($type);
        });

        // Linkable nachladen für auflösbare Links
        $resolvable->load('linkable');

        $withLinkable = $resolvable->filter(fn ($link) => $link->linkable !== null);

        $grouped = $withLinkable->groupBy('linkable_type');

        return $grouped->map(function ($items, $type) {
            $config = $this->linkTypeConfig[$type] ?? [
                'label' => $type,
                'icon' => 'link',
                'route' => null,
            ];

            return [
                'label' => $config['label'],
                'icon' => $config['icon'],
                'route' => $config['route'],
                'items' => $items,
            ];
        });
    }

    public function render()
    {
        return view('organization::livewire.entity.show')
            ->layout('platform::layouts.app');
    }
}
