<?php

namespace Platform\Organization\Livewire\JobProfile;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationJobProfile;
use Platform\Organization\Models\OrganizationPersonJobProfile;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = 'active';
    public ?string $levelFilter = null;

    public ?int $expandedProfileId = null;
    public array $assignForm = [
        'person_entity_id' => '',
        'percentage' => '100',
        'is_primary' => false,
        'valid_from' => '',
        'valid_to' => '',
        'note' => '',
    ];

    public bool $modalShow = false;
    public ?int $editingId = null;

    public array $form = [
        'name' => '',
        'description' => '',
        'content' => '',
        'level' => '',
        'skills' => '',           // komma-getrennt im UI
        'responsibilities' => '', // komma-getrennt im UI
        'status' => 'active',
        'owner_entity_id' => '',
        'effective_from' => null,
        'effective_to' => null,
    ];

    protected $queryString = [
        'search'       => ['except' => ''],
        'statusFilter' => ['except' => 'active'],
        'levelFilter'  => ['except' => null],
    ];

    protected function rules(): array
    {
        return [
            'form.name'             => ['required', 'string', 'max:255'],
            'form.description'      => ['nullable', 'string'],
            'form.content'          => ['nullable', 'string'],
            'form.level'            => ['nullable', 'string', 'max:50'],
            'form.skills'           => ['nullable', 'string'],
            'form.responsibilities' => ['nullable', 'string'],
            'form.status'           => ['required', 'in:active,archived,draft'],
            'form.owner_entity_id'  => ['nullable', 'integer', 'exists:organization_entities,id'],
            'form.effective_from'   => ['nullable', 'date'],
            'form.effective_to'     => ['nullable', 'date'],
        ];
    }

    #[Computed]
    public function jobProfiles()
    {
        $q = OrganizationJobProfile::query()
            ->withCount('assignments')
            ->with('ownerEntity', 'assignments.person')
            ->where('team_id', Auth::user()->currentTeam->id);

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('content', 'like', $term);
            });
        }

        if ($this->statusFilter !== '') {
            $q->where('status', $this->statusFilter);
        }

        if (! empty($this->levelFilter)) {
            $q->where('level', $this->levelFilter);
        }

        return $q->orderBy('name')->get();
    }

    #[Computed]
    public function itemTree(): array
    {
        $items = $this->jobProfiles;
        $teamId = Auth::user()->currentTeam->id;

        $entities = OrganizationEntity::where('team_id', $teamId)
            ->with('type')
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $byOwner = $items->groupBy('owner_entity_id');

        $ownerIds = $byOwner->keys()->filter()->toArray();
        $relevantEntityIds = collect();

        foreach ($ownerIds as $ownerId) {
            $current = $entities->get($ownerId);
            if (!$current) continue;

            $visited = [];
            while ($current && !in_array($current->id, $visited)) {
                $visited[] = $current->id;
                $relevantEntityIds->push($current->id);
                $current = $current->parent_entity_id ? $entities->get($current->parent_entity_id) : null;
            }
        }

        $relevantEntityIds = $relevantEntityIds->unique();

        $tree = $this->buildEntityNode(null, $entities, $byOwner, $relevantEntityIds, 0);

        $unowned = $byOwner->get('', collect())->merge($byOwner->get(null, collect()));
        if ($unowned->isNotEmpty()) {
            $tree[] = [
                'type' => 'group',
                'label' => 'Ohne Owner',
                'entity' => null,
                'entity_type' => null,
                'depth' => 0,
                'items' => $unowned->values()->all(),
                'children' => [],
            ];
        }

        return $tree;
    }

    private function buildEntityNode(?int $parentId, $entities, $byOwner, $relevantEntityIds, int $depth): array
    {
        $nodes = [];

        $children = $entities->filter(function ($e) use ($parentId, $relevantEntityIds) {
            return $e->parent_entity_id == $parentId && $relevantEntityIds->contains($e->id);
        })->sortBy(fn ($e) => ($e->type?->sort_order ?? 999) . '_' . $e->name);

        foreach ($children as $entity) {
            $directItems = $byOwner->get($entity->id, collect());
            $childNodes = $this->buildEntityNode($entity->id, $entities, $byOwner, $relevantEntityIds, $depth + 1);

            $nodes[] = [
                'type' => 'entity',
                'label' => $entity->name,
                'entity' => $entity,
                'entity_type' => $entity->type?->name,
                'depth' => $depth,
                'items' => $directItems->values()->all(),
                'children' => $childNodes,
            ];
        }

        return $nodes;
    }

    #[Computed]
    public function availableEntities()
    {
        return OrganizationEntity::where('team_id', Auth::user()->currentTeam->id)
            ->orderBy('name')
            ->get();
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
        $jp = OrganizationJobProfile::where('team_id', Auth::user()->currentTeam->id)->find($id);
        if (! $jp) {
            return;
        }

        $this->resetValidation();
        $this->editingId = $jp->id;
        $this->form = [
            'name'             => (string) $jp->name,
            'description'      => (string) ($jp->description ?? ''),
            'content'          => (string) ($jp->content ?? ''),
            'level'            => (string) ($jp->level ?? ''),
            'skills'           => implode(', ', is_array($jp->skills) ? $jp->skills : []),
            'responsibilities' => implode(', ', is_array($jp->responsibilities) ? $jp->responsibilities : []),
            'status'           => (string) ($jp->status ?? 'active'),
            'owner_entity_id'  => (string) ($jp->owner_entity_id ?? ''),
            'effective_from'   => $jp->effective_from?->toDateString(),
            'effective_to'     => $jp->effective_to?->toDateString(),
        ];
        $this->modalShow = true;
    }

    public function store(): void
    {
        $data = $this->validate()['form'];

        $payload = [
            'name'             => trim($data['name']),
            'description'      => $data['description'] !== '' ? $data['description'] : null,
            'content'          => $data['content'] !== '' ? $data['content'] : null,
            'level'            => $data['level'] !== '' ? $data['level'] : null,
            'skills'           => $this->csvToArray($data['skills'] ?? ''),
            'responsibilities' => $this->csvToArray($data['responsibilities'] ?? ''),
            'status'           => $data['status'],
            'owner_entity_id'  => $data['owner_entity_id'] !== '' ? (int) $data['owner_entity_id'] : null,
            'effective_from'   => $data['effective_from'] ?: null,
            'effective_to'     => $data['effective_to'] ?: null,
        ];

        if ($this->editingId) {
            $jp = OrganizationJobProfile::where('team_id', Auth::user()->currentTeam->id)->find($this->editingId);
            if ($jp) {
                $jp->update($payload);
                $this->dispatch('toast', message: 'JobProfile aktualisiert');
            }
        } else {
            OrganizationJobProfile::create(array_merge($payload, [
                'team_id' => Auth::user()->currentTeam->id,
                'user_id' => Auth::id(),
            ]));
            $this->dispatch('toast', message: 'JobProfile erstellt');
        }

        $this->modalShow = false;
        $this->editingId = null;
    }

    public function archive(int $id): void
    {
        $jp = OrganizationJobProfile::where('team_id', Auth::user()->currentTeam->id)->find($id);
        if ($jp) {
            $jp->update(['status' => 'archived']);
            $this->dispatch('toast', message: 'JobProfile archiviert');
        }
    }

    public function unarchive(int $id): void
    {
        $jp = OrganizationJobProfile::where('team_id', Auth::user()->currentTeam->id)->find($id);
        if ($jp) {
            $jp->update(['status' => 'active']);
            $this->dispatch('toast', message: 'JobProfile reaktiviert');
        }
    }

    public function delete(int $id): void
    {
        $jp = OrganizationJobProfile::where('team_id', Auth::user()->currentTeam->id)
            ->withCount('assignments')
            ->find($id);

        if (! $jp) {
            return;
        }

        if ($jp->assignments_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'JobProfile ist Personen zugewiesen. Bitte archivieren statt löschen.');

            return;
        }

        $jp->delete();
        $this->dispatch('toast', message: 'JobProfile gelöscht');
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
        $byType = $entities->groupBy(fn ($e) => $e->type?->name ?? 'Sonstige');

        foreach ($byType as $typeName => $group) {
            foreach ($group as $entity) {
                $result[] = [
                    'value' => (string) $entity->id,
                    'label' => $entity->name,
                ];
            }
        }

        return $result;
    }

    public function toggleAssignments(int $id): void
    {
        $this->expandedProfileId = $this->expandedProfileId === $id ? null : $id;
        $this->reset('assignForm');
        $this->assignForm['percentage'] = '100';
    }

    public function storeAssignment(): void
    {
        if (! $this->expandedProfileId || empty($this->assignForm['person_entity_id'])) {
            return;
        }

        $teamId = Auth::user()->currentTeam->id;

        $jp = OrganizationJobProfile::where('team_id', $teamId)->find($this->expandedProfileId);
        if (! $jp) {
            return;
        }

        OrganizationPersonJobProfile::create([
            'team_id' => $teamId,
            'job_profile_id' => $jp->id,
            'person_entity_id' => (int) $this->assignForm['person_entity_id'],
            'percentage' => $this->assignForm['percentage'] !== '' ? (int) $this->assignForm['percentage'] : null,
            'is_primary' => (bool) $this->assignForm['is_primary'],
            'valid_from' => $this->assignForm['valid_from'] ?: null,
            'valid_to' => $this->assignForm['valid_to'] ?: null,
            'note' => $this->assignForm['note'] !== '' ? $this->assignForm['note'] : null,
        ]);

        $this->reset('assignForm');
        $this->assignForm['percentage'] = '100';
        unset($this->jobProfiles);
        $this->dispatch('toast', message: 'Zuweisung erstellt');
    }

    public function deleteAssignment(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;

        $assignment = OrganizationPersonJobProfile::where('team_id', $teamId)->find($id);
        if ($assignment) {
            $assignment->delete();
            unset($this->jobProfiles);
            $this->dispatch('toast', message: 'Zuweisung entfernt');
        }
    }

    protected function csvToArray(string $value): ?array
    {
        if (trim($value) === '') {
            return null;
        }

        $items = array_values(array_filter(array_map('trim', explode(',', $value))));

        return $items !== [] ? $items : null;
    }

    public function render()
    {
        return view('organization::livewire.job-profile.index')
            ->layout('platform::layouts.app');
    }
}
