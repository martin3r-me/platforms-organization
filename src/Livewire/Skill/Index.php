<?php

namespace Platform\Organization\Livewire\Skill;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationSkill;
use Platform\Organization\Models\OrganizationSoftSkill;

class Index extends Component
{
    public string $activeTab = 'skills';
    public string $search = '';
    public string $categoryFilter = '';
    public bool $showMatrix = false;
    public bool $showCreateModal = false;
    public array $form = ['name' => '', 'category' => 'technical', 'description' => ''];
    public ?int $editingId = null;

    // Matrix assignment modal
    public bool $showAssignModal = false;
    public ?int $assignSkillId = null;
    public ?int $assignPersonId = null;
    public string $assignLevel = 'basic';

    #[Computed]
    public function skills()
    {
        $teamId = Auth::user()->currentTeam->id;

        return OrganizationSkill::forTeam($teamId)
            ->withCount(['persons', 'jobProfiles'])
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
            ->withCount(['persons', 'jobProfiles'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function personEntities()
    {
        return OrganizationEntity::where('team_id', Auth::user()->currentTeam->id)
            ->where('is_active', true)
            ->whereHas('type', fn ($q) => $q->where('code', 'person'))
            ->with(['skills', 'softSkills'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function matrix(): array
    {
        $persons = $this->personEntities;
        $items = $this->activeTab === 'skills' ? $this->skills : $this->softSkills;

        $grid = [];
        foreach ($items as $item) {
            $row = ['skill' => $item, 'cells' => []];
            foreach ($persons as $person) {
                $relation = $this->activeTab === 'skills'
                    ? $person->skills->firstWhere('id', $item->id)
                    : $person->softSkills->firstWhere('id', $item->id);

                $row['cells'][] = [
                    'person_id' => $person->id,
                    'level' => $relation?->pivot->level ?? null,
                ];
            }
            $grid[] = $row;
        }

        return $grid;
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
        unset($this->skills, $this->softSkills, $this->matrix);
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

        unset($this->skills, $this->softSkills, $this->matrix);
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

    // Matrix assignment
    public function openAssignModal(int $skillId, int $personId): void
    {
        $this->assignSkillId = $skillId;
        $this->assignPersonId = $personId;

        // Check existing assignment
        $person = OrganizationEntity::find($personId);
        if ($person) {
            if ($this->activeTab === 'skills') {
                $existing = $person->skills->firstWhere('id', $skillId);
            } else {
                $existing = $person->softSkills->firstWhere('id', $skillId);
            }
            $this->assignLevel = $existing?->pivot->level ?? 'basic';
        }

        $this->showAssignModal = true;
    }

    public function saveAssignment(): void
    {
        $person = OrganizationEntity::findOrFail($this->assignPersonId);

        if ($this->activeTab === 'skills') {
            $person->skills()->syncWithoutDetaching([
                $this->assignSkillId => ['level' => $this->assignLevel],
            ]);
        } else {
            $person->softSkills()->syncWithoutDetaching([
                $this->assignSkillId => ['level' => $this->assignLevel],
            ]);
        }

        $this->showAssignModal = false;
        unset($this->personEntities, $this->matrix);
        $this->dispatch('toast', message: 'Zuordnung gespeichert');
    }

    public function removeAssignment(): void
    {
        $person = OrganizationEntity::findOrFail($this->assignPersonId);

        if ($this->activeTab === 'skills') {
            $person->skills()->detach($this->assignSkillId);
        } else {
            $person->softSkills()->detach($this->assignSkillId);
        }

        $this->showAssignModal = false;
        unset($this->personEntities, $this->matrix);
        $this->dispatch('toast', message: 'Zuordnung entfernt');
    }

    public function updatedActiveTab(): void
    {
        $this->search = '';
        $this->categoryFilter = '';
        unset($this->skills, $this->softSkills, $this->matrix);
    }

    public function render()
    {
        return view('organization::livewire.skill.index')
            ->layout('platform::layouts.app');
    }
}
