<?php

namespace Platform\Organization\Livewire\Perspective;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationPerspective;

class Index extends Component
{
    public $search = '';
    public $modalShow = false;

    public $form = [
        'name' => '',
        'description' => '',
        'is_default' => false,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    #[Computed]
    public function perspectives()
    {
        $query = OrganizationPerspective::query()
            ->where('team_id', auth()->user()->currentTeam->id);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        return $query->orderByDesc('is_default')->orderBy('name')->get();
    }

    public function create()
    {
        $this->reset('form');
        $this->modalShow = true;
    }

    public function store()
    {
        $data = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string'],
            'form.is_default' => ['boolean'],
        ])['form'];

        OrganizationPerspective::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
        ]);

        $this->modalShow = false;
        $this->dispatch('toast', message: 'Perspektive erstellt');
    }

    public function deletePerspective($id)
    {
        $perspective = OrganizationPerspective::where('team_id', auth()->user()->currentTeam->id)
            ->findOrFail($id);

        if ($perspective->is_default) {
            $this->dispatch('toast', message: 'Standard-Perspektive kann nicht gelöscht werden', type: 'error');
            return;
        }

        $perspective->delete();
        $this->dispatch('toast', message: 'Perspektive gelöscht');
    }

    public function render()
    {
        return view('organization::livewire.perspective.index')
            ->layout('platform::layouts.app');
    }
}
