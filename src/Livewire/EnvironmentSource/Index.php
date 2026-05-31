<?php

namespace Platform\Organization\Livewire\EnvironmentSource;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Services\EnvironmentPullService;

class Index extends Component
{
    public $search = '';
    public $categoryFilter = '';
    public $showInactive = false;
    public $modalShow = false;
    public $editingId = null;

    public $form = [
        'name' => '',
        'source_type' => 'rss',
        'category' => 'industry',
        'url' => '',
        'pull_interval_hours' => 6,
        'extraction_prompt' => '',
        'is_active' => true,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'categoryFilter' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    #[Computed]
    public function sources()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationEnvironmentSource::forTeam($teamId)
            ->withCount('snapshots');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('config->url', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter) {
            $query->where('category', $this->categoryFilter);
        }

        if (! $this->showInactive) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function categories()
    {
        return ['industry', 'technology', 'regulation', 'market', 'competition', 'talent', 'sustainability', 'other'];
    }

    public function create()
    {
        $this->reset('form', 'editingId');
        $this->form['is_active'] = true;
        $this->form['source_type'] = 'rss';
        $this->form['category'] = 'industry';
        $this->form['pull_interval_hours'] = 6;
        $this->modalShow = true;
    }

    public function edit(int $id)
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        $source = OrganizationEnvironmentSource::forTeam($teamId)->findOrFail($id);

        $this->editingId = $source->id;
        $this->form = [
            'name' => $source->name,
            'source_type' => $source->source_type,
            'category' => $source->category,
            'url' => $source->config['url'] ?? '',
            'pull_interval_hours' => $source->pull_interval_hours,
            'extraction_prompt' => $source->config['extraction_prompt'] ?? '',
            'is_active' => $source->is_active,
        ];
        $this->modalShow = true;
    }

    public function store()
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.source_type' => ['required', 'string', 'in:rss'],
            'form.category' => ['required', 'string'],
            'form.url' => ['required', 'url', 'max:2048'],
            'form.pull_interval_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'form.extraction_prompt' => ['nullable', 'string', 'max:1000'],
            'form.is_active' => ['boolean'],
        ]);

        $data = [
            'name' => $this->form['name'],
            'source_type' => $this->form['source_type'],
            'category' => $this->form['category'],
            'pull_interval_hours' => (int) $this->form['pull_interval_hours'],
            'is_active' => (bool) $this->form['is_active'],
            'config' => [
                'url' => $this->form['url'],
                'extraction_prompt' => $this->form['extraction_prompt'] ?: null,
            ],
        ];

        if ($this->editingId) {
            $teamId = auth()->user()?->currentTeamRelation?->id;
            $source = OrganizationEnvironmentSource::forTeam($teamId)->findOrFail($this->editingId);
            $source->update($data);
            $this->dispatch('toast', message: 'Quelle aktualisiert');
        } else {
            OrganizationEnvironmentSource::create($data);
            $this->dispatch('toast', message: 'Quelle erstellt');
        }

        $this->modalShow = false;
        $this->editingId = null;
    }

    public function toggleActive(int $id)
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        $source = OrganizationEnvironmentSource::forTeam($teamId)->findOrFail($id);
        $source->update(['is_active' => ! $source->is_active]);
        $this->dispatch('toast', message: $source->is_active ? 'Quelle aktiviert' : 'Quelle deaktiviert');
    }

    public function pullNow(int $id)
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        $source = OrganizationEnvironmentSource::forTeam($teamId)->findOrFail($id);

        $service = new EnvironmentPullService();
        $snapshot = $service->pullSource($source);

        if ($snapshot) {
            $this->dispatch('toast', message: 'Pull erfolgreich — Snapshot erstellt');
        } else {
            $this->dispatch('toast', message: 'Pull abgeschlossen — keine neuen Items');
        }
    }

    public function render()
    {
        return view('organization::livewire.environment-source.index')
            ->layout('platform::layouts.app');
    }
}
