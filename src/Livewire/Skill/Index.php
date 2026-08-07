<?php

namespace Platform\Organization\Livewire\Skill;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationSkill;
use Platform\Organization\Models\OrganizationSoftSkill;

class Index extends Component
{
    public string $activeTab = 'skills';
    public string $search = '';
    public string $categoryFilter = '';
    public bool $showCreateModal = false;
    public array $form = ['name' => '', 'category' => 'technical', 'description' => ''];
    public ?int $editingId = null;

    #[Computed]
    public function skills()
    {
        $teamId = Auth::user()->currentTeam->id;

        return OrganizationSkill::forTeam($teamId)
            ->withCount('jobProfiles')
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function softSkills()
    {
        $teamId = Auth::user()->currentTeam->id;

        return OrganizationSoftSkill::forTeam($teamId)
            ->withCount('jobProfiles')
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->get();
    }

    public function openCreate(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'category' => 'technical', 'description' => ''];
        $this->showCreateModal = true;
    }

    public function openEdit(int $id): void
    {
        if ($this->activeTab === 'skills') {
            $item = OrganizationSkill::findOrFail($id);
            $this->form = [
                'name' => $item->name,
                'category' => $item->category ?? 'technical',
                'description' => $item->description ?? '',
            ];
        } else {
            $item = OrganizationSoftSkill::findOrFail($id);
            $this->form = [
                'name' => $item->name,
                'category' => 'technical',
                'description' => $item->description ?? '',
            ];
        }
        $this->editingId = $id;
        $this->showCreateModal = true;
    }

    public function saveItem(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:255',
            'form.description' => 'nullable|string',
        ]);

        $teamId = Auth::user()->currentTeam->id;

        if ($this->activeTab === 'skills') {
            $data = [
                'name' => trim($this->form['name']),
                'category' => $this->form['category'],
                'description' => $this->form['description'] !== '' ? $this->form['description'] : null,
                'team_id' => $teamId,
            ];

            if ($this->editingId) {
                OrganizationSkill::where('id', $this->editingId)->where('team_id', $teamId)->update($data);
            } else {
                OrganizationSkill::create($data);
            }
        } else {
            $data = [
                'name' => trim($this->form['name']),
                'description' => $this->form['description'] !== '' ? $this->form['description'] : null,
                'team_id' => $teamId,
            ];

            if ($this->editingId) {
                OrganizationSoftSkill::where('id', $this->editingId)->where('team_id', $teamId)->update($data);
            } else {
                OrganizationSoftSkill::create($data);
            }
        }

        $this->showCreateModal = false;
        $this->editingId = null;
        $this->form = ['name' => '', 'category' => 'technical', 'description' => ''];
        unset($this->skills, $this->softSkills);
        $this->dispatch('toast', message: $this->editingId ? 'Gespeichert' : 'Erstellt');
    }

    public function deleteItem(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;

        if ($this->activeTab === 'skills') {
            OrganizationSkill::where('id', $id)->where('team_id', $teamId)->delete();
        } else {
            OrganizationSoftSkill::where('id', $id)->where('team_id', $teamId)->delete();
        }

        unset($this->skills, $this->softSkills);
        $this->dispatch('toast', message: 'Gelöscht');
    }

    public function toggleActive(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;

        if ($this->activeTab === 'skills') {
            $item = OrganizationSkill::where('id', $id)->where('team_id', $teamId)->firstOrFail();
        } else {
            $item = OrganizationSoftSkill::where('id', $id)->where('team_id', $teamId)->firstOrFail();
        }

        $item->update(['is_active' => ! $item->is_active]);
        unset($this->skills, $this->softSkills);
        $this->dispatch('toast', message: $item->is_active ? 'Aktiviert' : 'Deaktiviert');
    }

    public function updatedActiveTab(): void
    {
        $this->search = '';
        $this->categoryFilter = '';
        unset($this->skills, $this->softSkills);
    }

    public function render()
    {
        return view('organization::livewire.skill.index')
            ->layout('platform::layouts.app');
    }
}
