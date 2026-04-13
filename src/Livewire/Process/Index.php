<?php

namespace Platform\Organization\Livewire\Process;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationVsmSystem;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = '';

    public bool $modalShow = false;
    public ?int $editingId = null;

    public array $form = [
        'name' => '',
        'code' => '',
        'description' => '',
        'status' => 'draft',
        'owner_entity_id' => '',
        'vsm_system_id' => '',
    ];

    protected $queryString = [
        'search'       => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    protected function rules(): array
    {
        return [
            'form.name'            => ['required', 'string', 'max:255'],
            'form.code'            => ['nullable', 'string', 'max:100'],
            'form.description'     => ['nullable', 'string'],
            'form.status'          => ['required', 'in:draft,active,deprecated'],
            'form.owner_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'form.vsm_system_id'   => ['nullable', 'integer', 'exists:organization_vsm_systems,id'],
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

        return $q->with(['ownerEntity', 'vsmSystem'])->orderBy('name')->get();
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
            'name'            => (string) $process->name,
            'code'            => (string) ($process->code ?? ''),
            'description'     => (string) ($process->description ?? ''),
            'status'          => (string) ($process->status ?? 'draft'),
            'owner_entity_id' => (string) ($process->owner_entity_id ?? ''),
            'vsm_system_id'   => (string) ($process->vsm_system_id ?? ''),
        ];
        $this->modalShow = true;
    }

    public function store(): void
    {
        $data = $this->validate()['form'];

        $payload = [
            'name'            => trim($data['name']),
            'code'            => $data['code'] !== '' ? $data['code'] : null,
            'description'     => $data['description'] !== '' ? $data['description'] : null,
            'status'          => $data['status'],
            'owner_entity_id' => $data['owner_entity_id'] !== '' ? (int) $data['owner_entity_id'] : null,
            'vsm_system_id'   => $data['vsm_system_id'] !== '' ? (int) $data['vsm_system_id'] : null,
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
