<?php

namespace Platform\Organization\Livewire\JobProfile;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationJobProfile;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = 'active';
    public ?string $levelFilter = null;

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
            'form.effective_from'   => ['nullable', 'date'],
            'form.effective_to'     => ['nullable', 'date'],
        ];
    }

    #[Computed]
    public function jobProfiles()
    {
        $q = OrganizationJobProfile::query()
            ->withCount('assignments')
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
