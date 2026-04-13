<?php

namespace Platform\Organization\Livewire\Role;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationRole;
use Platform\Organization\Models\OrganizationRoleAssignment;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = 'active';

    public ?int $expandedRoleId = null;
    public array $roleAssignForm = [
        'person_entity_id' => '',
        'context_entity_id' => '',
        'percentage' => '',
        'valid_from' => '',
        'valid_to' => '',
        'note' => '',
    ];

    public bool $modalShow = false;
    public ?int $editingId = null;

    public array $form = [
        'name' => '',
        'slug' => '',
        'description' => '',
        'status' => 'active',
    ];

    protected $queryString = [
        'search'       => ['except' => ''],
        'statusFilter' => ['except' => 'active'],
    ];

    protected function rules(): array
    {
        return [
            'form.name'        => ['required', 'string', 'max:255'],
            'form.slug'        => ['nullable', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.status'      => ['required', 'in:active,archived'],
        ];
    }

    #[Computed]
    public function roles()
    {
        $q = OrganizationRole::query()
            ->withCount('assignments')
            ->with('assignments.person', 'assignments.context')
            ->where('team_id', Auth::user()->currentTeam->id);

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if ($this->statusFilter !== '') {
            $q->where('status', $this->statusFilter);
        }

        return $q->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('form');
        $this->form['status'] = 'active';
        $this->editingId = null;
        $this->modalShow = true;
    }

    public function edit(int $id): void
    {
        $role = OrganizationRole::where('team_id', Auth::user()->currentTeam->id)->find($id);
        if (! $role) {
            return;
        }

        $this->resetValidation();
        $this->editingId = $role->id;
        $this->form = [
            'name'        => (string) $role->name,
            'slug'        => (string) ($role->slug ?? ''),
            'description' => (string) ($role->description ?? ''),
            'status'      => (string) ($role->status ?? 'active'),
        ];
        $this->modalShow = true;
    }

    public function store(): void
    {
        $data = $this->validate()['form'];

        $payload = [
            'name'        => trim($data['name']),
            'slug'        => $data['slug'] !== '' ? $data['slug'] : null,
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'status'      => $data['status'],
        ];

        if ($this->editingId) {
            $role = OrganizationRole::where('team_id', Auth::user()->currentTeam->id)->find($this->editingId);
            if ($role) {
                $role->update($payload);
                $this->dispatch('toast', message: 'Rolle aktualisiert');
            }
        } else {
            OrganizationRole::create(array_merge($payload, [
                'team_id' => Auth::user()->currentTeam->id,
                'user_id' => Auth::id(),
            ]));
            $this->dispatch('toast', message: 'Rolle erstellt');
        }

        $this->modalShow = false;
        $this->editingId = null;
    }

    public function archive(int $id): void
    {
        $role = OrganizationRole::where('team_id', Auth::user()->currentTeam->id)->find($id);
        if ($role) {
            $role->update(['status' => 'archived']);
            $this->dispatch('toast', message: 'Rolle archiviert');
        }
    }

    public function unarchive(int $id): void
    {
        $role = OrganizationRole::where('team_id', Auth::user()->currentTeam->id)->find($id);
        if ($role) {
            $role->update(['status' => 'active']);
            $this->dispatch('toast', message: 'Rolle reaktiviert');
        }
    }

    public function delete(int $id): void
    {
        $role = OrganizationRole::where('team_id', Auth::user()->currentTeam->id)
            ->withCount('assignments')
            ->find($id);

        if (! $role) {
            return;
        }

        if ($role->assignments_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Rolle ist zugewiesen. Bitte archivieren statt löschen.');

            return;
        }

        $role->delete();
        $this->dispatch('toast', message: 'Rolle gelöscht');
    }

    #[Computed]
    public function groupedPersonOptions(): array
    {
        $entities = OrganizationEntity::where('team_id', Auth::user()->currentTeam->id)
            ->with(['type'])
            ->where('is_active', true)
            ->whereHas('type', fn ($q) => $q->where('code', 'person'))
            ->orderBy('name')
            ->get();

        $result = [];
        foreach ($entities as $entity) {
            $result[] = [
                'value' => (string) $entity->id,
                'label' => $entity->name,
            ];
        }

        return $result;
    }

    #[Computed]
    public function groupedEntityOptions(): array
    {
        $entities = OrganizationEntity::where('team_id', Auth::user()->currentTeam->id)
            ->with(['type.group', 'parent'])
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $result = [];
        $byType = $entities->groupBy(fn ($e) => $e->type?->group?->sort_order ?? 999);
        $sorted = $byType->sortKeys();

        foreach ($sorted as $entitiesInGroup) {
            $typed = $entitiesInGroup->sortBy([
                fn ($a, $b) => ($a->type?->sort_order ?? 999) <=> ($b->type?->sort_order ?? 999),
                fn ($a, $b) => $a->name <=> $b->name,
            ]);

            $roots = $typed->whereNull('parent_entity_id');
            foreach ($roots as $root) {
                $typeName = $root->type?->name ?? '';
                $result[] = [
                    'value' => (string) $root->id,
                    'label' => ($typeName ? $typeName.' / ' : '').$root->name,
                ];
                $this->addChildOptions($result, $root->id, $entities, 1);
            }
        }

        $usedIds = collect($result)->pluck('value')->toArray();
        foreach ($entities as $e) {
            if (! in_array((string) $e->id, $usedIds, true)) {
                $typeName = $e->type?->name ?? '';
                $result[] = [
                    'value' => (string) $e->id,
                    'label' => ($typeName ? $typeName.' / ' : '').$e->name,
                ];
            }
        }

        return $result;
    }

    private function addChildOptions(array &$result, int $parentId, $entities, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $children = $entities->where('parent_entity_id', $parentId)->sortBy('name');

        foreach ($children as $child) {
            $result[] = [
                'value' => (string) $child->id,
                'label' => $indent.'└ '.$child->name,
            ];
            $this->addChildOptions($result, $child->id, $entities, $depth + 1);
        }
    }

    public function toggleAssignments(int $id): void
    {
        $this->expandedRoleId = $this->expandedRoleId === $id ? null : $id;
        $this->reset('roleAssignForm');
    }

    public function storeRoleAssignment(): void
    {
        if (! $this->expandedRoleId || empty($this->roleAssignForm['person_entity_id'])) {
            return;
        }

        $teamId = Auth::user()->currentTeam->id;

        $role = OrganizationRole::where('team_id', $teamId)->find($this->expandedRoleId);
        if (! $role) {
            return;
        }

        OrganizationRoleAssignment::create([
            'team_id' => $teamId,
            'user_id' => Auth::id(),
            'role_id' => $role->id,
            'person_entity_id' => (int) $this->roleAssignForm['person_entity_id'],
            'context_entity_id' => $this->roleAssignForm['context_entity_id'] !== '' ? (int) $this->roleAssignForm['context_entity_id'] : null,
            'percentage' => $this->roleAssignForm['percentage'] !== '' ? (int) $this->roleAssignForm['percentage'] : null,
            'valid_from' => $this->roleAssignForm['valid_from'] ?: null,
            'valid_to' => $this->roleAssignForm['valid_to'] ?: null,
            'note' => $this->roleAssignForm['note'] !== '' ? $this->roleAssignForm['note'] : null,
        ]);

        $this->reset('roleAssignForm');
        unset($this->roles);
        $this->dispatch('toast', message: 'Rollenzuweisung erstellt');
    }

    public function deleteRoleAssignment(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;

        $assignment = OrganizationRoleAssignment::where('team_id', $teamId)->find($id);
        if ($assignment) {
            $assignment->delete();
            unset($this->roles);
            $this->dispatch('toast', message: 'Rollenzuweisung entfernt');
        }
    }

    public function render()
    {
        return view('organization::livewire.role.index')
            ->layout('platform::layouts.app');
    }
}
