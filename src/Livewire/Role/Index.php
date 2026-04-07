<?php

namespace Platform\Organization\Livewire\Role;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationRole;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = 'active';

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

    public function render()
    {
        return view('organization::livewire.role.index')
            ->layout('platform::layouts.app');
    }
}
