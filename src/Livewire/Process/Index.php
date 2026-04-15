<?php

namespace Platform\Organization\Livewire\Process;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Enums\ProcessCategory;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationVsmSystem;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = '';
    public string $categoryFilter = '';
    public string $vsmFilter = '';
    public bool $focusFilter = false;

    public bool $modalShow = false;
    public ?int $editingId = null;

    public array $form = [
        'name' => '',
        'code' => '',
        'description' => '',
        'status' => 'draft',
        'process_category' => '',
        'is_focus' => false,
        'focus_reason' => '',
        'focus_until' => null,
        'owner_entity_id' => '',
        'vsm_system_id' => '',
    ];

    protected $queryString = [
        'search'         => ['except' => ''],
        'statusFilter'   => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'vsmFilter'      => ['except' => ''],
        'focusFilter'    => ['except' => false],
    ];

    protected function rules(): array
    {
        return [
            'form.name'            => ['required', 'string', 'max:255'],
            'form.code'            => ['nullable', 'string', 'max:100'],
            'form.description'     => ['nullable', 'string'],
            'form.status'           => ['required', 'in:draft,active,deprecated'],
            'form.process_category' => ['nullable', 'in:core,support,management'],
            'form.is_focus'         => ['boolean'],
            'form.focus_reason'     => ['nullable', 'string'],
            'form.focus_until'      => ['nullable', 'date'],
            'form.owner_entity_id'  => ['nullable', 'integer', 'exists:organization_entities,id'],
            'form.vsm_system_id'    => ['nullable', 'integer', 'exists:organization_vsm_systems,id'],
        ];
    }

    #[Computed]
    public function processes()
    {
        $q = OrganizationProcess::query()
            ->withCount('steps')
            ->where('team_id', Auth::user()->currentTeam->id);

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if ($this->statusFilter !== '') {
            $q->where('status', $this->statusFilter);
        }

        if ($this->categoryFilter !== '') {
            $q->where('process_category', $this->categoryFilter);
        }

        if ($this->vsmFilter !== '') {
            $q->where('vsm_system_id', (int) $this->vsmFilter);
        }

        if ($this->focusFilter) {
            $q->where('is_focus', true);
        }

        return $q->with(['ownerEntity', 'vsmSystem', 'steps:id,process_id,automation_level'])->orderBy('name')->get();
    }

    #[Computed]
    public function processTree(): array
    {
        $processes = $this->processes;
        $teamId = Auth::user()->currentTeam->id;

        // Load all entities with type for the team (keyed by id)
        $entities = OrganizationEntity::where('team_id', $teamId)
            ->with('type')
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        // Group processes by owner_entity_id
        $byOwner = $processes->groupBy('owner_entity_id');

        // Build tree: find root entities (no parent) that have processes (directly or via children)
        $tree = [];

        // Collect all entity IDs that own processes
        $ownerIds = $byOwner->keys()->filter()->toArray();

        // For each owner, walk up to find the root ancestor
        $entityAncestorCache = [];
        $relevantEntityIds = collect();

        foreach ($ownerIds as $ownerId) {
            $current = $entities->get($ownerId);
            if (!$current) continue;

            // Collect this entity and all its ancestors
            $chain = [];
            $visited = [];
            while ($current && !in_array($current->id, $visited)) {
                $visited[] = $current->id;
                $chain[] = $current->id;
                $relevantEntityIds->push($current->id);
                $current = $current->parent_entity_id ? $entities->get($current->parent_entity_id) : null;
            }
            $entityAncestorCache[$ownerId] = $chain;
        }

        $relevantEntityIds = $relevantEntityIds->unique();

        // Build nested tree recursively
        $tree = $this->buildEntityNode(null, $entities, $byOwner, $relevantEntityIds, 0);

        // Add "Ohne Owner" group
        $unowned = $byOwner->get('', collect())->merge($byOwner->get(null, collect()));
        if ($unowned->isNotEmpty()) {
            $tree[] = [
                'type' => 'group',
                'label' => 'Ohne Owner',
                'entity' => null,
                'entity_type' => null,
                'depth' => 0,
                'processes' => $unowned->values()->all(),
                'children' => [],
            ];
        }

        return $tree;
    }

    private function buildEntityNode(?int $parentId, $entities, $byOwner, $relevantEntityIds, int $depth): array
    {
        $nodes = [];

        // Get children of this parent that are relevant
        $children = $entities->filter(function ($e) use ($parentId, $relevantEntityIds) {
            return $e->parent_entity_id == $parentId && $relevantEntityIds->contains($e->id);
        })->sortBy(fn ($e) => ($e->type?->sort_order ?? 999) . '_' . $e->name);

        foreach ($children as $entity) {
            $directProcesses = $byOwner->get($entity->id, collect());
            $childNodes = $this->buildEntityNode($entity->id, $entities, $byOwner, $relevantEntityIds, $depth + 1);

            $nodes[] = [
                'type' => 'entity',
                'label' => $entity->name,
                'entity' => $entity,
                'entity_type' => $entity->type?->name,
                'depth' => $depth,
                'processes' => $directProcesses->values()->all(),
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

    #[Computed]
    public function availableVsmSystems()
    {
        return OrganizationVsmSystem::orderBy('name')->get();
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('form');
        $this->form['status'] = 'draft';
        $this->editingId = null;
        $this->modalShow = true;
    }

    public function edit(int $id): void
    {
        $process = OrganizationProcess::where('team_id', Auth::user()->currentTeam->id)->find($id);
        if (! $process) {
            return;
        }

        $this->resetValidation();
        $this->editingId = $process->id;
        $this->form = [
            'name'             => (string) $process->name,
            'code'             => (string) ($process->code ?? ''),
            'description'      => (string) ($process->description ?? ''),
            'status'           => (string) ($process->status ?? 'draft'),
            'process_category' => (string) ($process->process_category?->value ?? ''),
            'is_focus'         => (bool) $process->is_focus,
            'focus_reason'     => (string) ($process->focus_reason ?? ''),
            'focus_until'      => $process->focus_until?->format('Y-m-d'),
            'owner_entity_id'  => (string) ($process->owner_entity_id ?? ''),
            'vsm_system_id'    => (string) ($process->vsm_system_id ?? ''),
        ];
        $this->modalShow = true;
    }

    public function store(): void
    {
        $data = $this->validate()['form'];

        $payload = [
            'name'             => trim($data['name']),
            'code'             => $data['code'] !== '' ? $data['code'] : null,
            'description'      => $data['description'] !== '' ? $data['description'] : null,
            'status'           => $data['status'],
            'process_category' => $data['process_category'] !== '' ? $data['process_category'] : null,
            'is_focus'         => (bool) $data['is_focus'],
            'focus_reason'     => $data['is_focus'] && $data['focus_reason'] !== '' ? $data['focus_reason'] : null,
            'focus_until'      => $data['is_focus'] && $data['focus_until'] ? $data['focus_until'] : null,
            'owner_entity_id'  => $data['owner_entity_id'] !== '' ? (int) $data['owner_entity_id'] : null,
            'vsm_system_id'    => $data['vsm_system_id'] !== '' ? (int) $data['vsm_system_id'] : null,
        ];

        if ($this->editingId) {
            $process = OrganizationProcess::where('team_id', Auth::user()->currentTeam->id)->find($this->editingId);
            if ($process) {
                $process->update($payload);
                $this->dispatch('toast', message: 'Prozess aktualisiert');
            }
        } else {
            OrganizationProcess::create(array_merge($payload, [
                'team_id' => Auth::user()->currentTeam->id,
                'user_id' => Auth::id(),
            ]));
            $this->dispatch('toast', message: 'Prozess erstellt');
        }

        $this->modalShow = false;
        $this->editingId = null;
    }

    public function delete(int $id): void
    {
        $process = OrganizationProcess::where('team_id', Auth::user()->currentTeam->id)
            ->withCount('steps')
            ->find($id);

        if (! $process) {
            return;
        }

        $process->delete();
        $this->dispatch('toast', message: 'Prozess gelöscht');
    }

    public function render()
    {
        return view('organization::livewire.process.index')
            ->layout('platform::layouts.app');
    }
}
