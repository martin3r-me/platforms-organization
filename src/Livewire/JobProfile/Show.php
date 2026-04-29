<?php

namespace Platform\Organization\Livewire\JobProfile;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationJobProfile;
use Platform\Organization\Models\OrganizationPersonJobProfile;

class Show extends Component
{
    public OrganizationJobProfile $jobProfile;

    public array $form = [];

    public array $assignForm = [
        'person_entity_id' => '',
        'percentage' => '100',
        'is_primary' => false,
        'valid_from' => '',
        'valid_to' => '',
        'note' => '',
    ];

    public function mount(OrganizationJobProfile $jobProfile): void
    {
        abort_unless(
            (int) $jobProfile->team_id === (int) Auth::user()->currentTeam->id,
            403
        );

        $this->jobProfile = $jobProfile->load('ownerEntity', 'assignments.person', 'user');
        $this->loadForm();
    }

    public function loadForm(): void
    {
        $jp = $this->jobProfile;
        $this->form = [
            'name'             => (string) $jp->name,
            'description'      => (string) ($jp->description ?? ''),
            'purpose'          => (string) ($jp->purpose ?? ''),
            'job_family'       => (string) ($jp->job_family ?? ''),
            'content'          => (string) ($jp->content ?? ''),
            'level'            => (string) ($jp->level ?? ''),
            'skills'           => $jp->skills ?? [],
            'responsibilities' => $jp->responsibilities ?? [],
            'requirements'     => $jp->requirements ?? [],
            'soft_skills'      => $jp->soft_skills ?? [],
            'kpis'               => $jp->kpis ?? [],
            'exclusion_criteria' => $jp->exclusion_criteria ?? [],
            'work_model'         => $jp->work_model ?? [
                'type' => '', 'travel_required' => false, 'self_organized' => false, 'location_notes' => '',
            ],
            'reporting'          => $jp->reporting ?? [
                'reports_to' => '', 'autonomy_level' => '',
            ],
            'status'             => (string) ($jp->status ?? 'active'),
            'owner_entity_id'  => (string) ($jp->owner_entity_id ?? ''),
            'effective_from'   => $jp->effective_from?->toDateString(),
            'effective_to'     => $jp->effective_to?->toDateString(),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'form.name'           => ['required', 'string', 'max:255'],
            'form.description'    => ['nullable', 'string'],
            'form.purpose'        => ['nullable', 'string'],
            'form.job_family'     => ['nullable', 'string', 'max:100'],
            'form.content'        => ['nullable', 'string'],
            'form.level'          => ['nullable', 'string', 'max:50'],
            'form.status'         => ['required', 'in:active,archived,draft'],
            'form.owner_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'form.effective_from' => ['nullable', 'date'],
            'form.effective_to'   => ['nullable', 'date'],
        ]);

        $this->jobProfile->update([
            'name'             => trim($this->form['name']),
            'description'      => $this->form['description'] !== '' ? $this->form['description'] : null,
            'purpose'          => $this->form['purpose'] !== '' ? $this->form['purpose'] : null,
            'job_family'       => $this->form['job_family'] !== '' ? $this->form['job_family'] : null,
            'content'          => $this->form['content'] !== '' ? $this->form['content'] : null,
            'level'            => $this->form['level'] !== '' ? $this->form['level'] : null,
            'skills'           => ! empty($this->form['skills']) ? $this->form['skills'] : null,
            'responsibilities' => ! empty($this->form['responsibilities']) ? $this->form['responsibilities'] : null,
            'requirements'     => ! empty($this->form['requirements']) ? $this->form['requirements'] : null,
            'soft_skills'      => ! empty($this->form['soft_skills']) ? $this->form['soft_skills'] : null,
            'kpis'               => ! empty($this->form['kpis']) ? $this->form['kpis'] : null,
            'exclusion_criteria' => ! empty($this->form['exclusion_criteria']) ? $this->form['exclusion_criteria'] : null,
            'work_model'         => $this->hasWorkModelData() ? $this->form['work_model'] : null,
            'reporting'          => $this->hasReportingData() ? $this->form['reporting'] : null,
            'status'             => $this->form['status'],
            'owner_entity_id'  => $this->form['owner_entity_id'] !== '' ? (int) $this->form['owner_entity_id'] : null,
            'effective_from'   => $this->form['effective_from'] ?: null,
            'effective_to'     => $this->form['effective_to'] ?: null,
        ]);

        $this->jobProfile->refresh();
        $this->loadForm();
        $this->dispatch('toast', message: 'JobProfile gespeichert');
    }

    public function archive(): void
    {
        $this->jobProfile->update(['status' => 'archived']);
        $this->jobProfile->refresh();
        $this->loadForm();
        $this->dispatch('toast', message: 'JobProfile archiviert');
    }

    public function unarchive(): void
    {
        $this->jobProfile->update(['status' => 'active']);
        $this->jobProfile->refresh();
        $this->loadForm();
        $this->dispatch('toast', message: 'JobProfile reaktiviert');
    }

    // --- JSON array field helpers ---

    public function addSkill(): void
    {
        $this->form['skills'][] = ['name' => '', 'level' => 'expert', 'category' => 'technical'];
    }

    public function removeSkill(int $index): void
    {
        unset($this->form['skills'][$index]);
        $this->form['skills'] = array_values($this->form['skills']);
    }

    public function addSoftSkill(): void
    {
        $this->form['soft_skills'][] = ['name' => '', 'level' => 'basic'];
    }

    public function removeSoftSkill(int $index): void
    {
        unset($this->form['soft_skills'][$index]);
        $this->form['soft_skills'] = array_values($this->form['soft_skills']);
    }

    public function addResponsibility(): void
    {
        $this->form['responsibilities'][] = ['name' => '', 'is_core' => true];
    }

    public function removeResponsibility(int $index): void
    {
        unset($this->form['responsibilities'][$index]);
        $this->form['responsibilities'] = array_values($this->form['responsibilities']);
    }

    public function addRequirement(): void
    {
        $this->form['requirements'][] = ['name' => '', 'type' => 'experience', 'required' => true];
    }

    public function removeRequirement(int $index): void
    {
        unset($this->form['requirements'][$index]);
        $this->form['requirements'] = array_values($this->form['requirements']);
    }

    public function addKpi(): void
    {
        $this->form['kpis'][] = ['name' => '', 'description' => ''];
    }

    public function removeKpi(int $index): void
    {
        unset($this->form['kpis'][$index]);
        $this->form['kpis'] = array_values($this->form['kpis']);
    }

    // --- Exclusion Criteria ---

    public function addExclusionCriterion(): void
    {
        $this->form['exclusion_criteria'][] = '';
    }

    public function removeExclusionCriterion(int $index): void
    {
        unset($this->form['exclusion_criteria'][$index]);
        $this->form['exclusion_criteria'] = array_values($this->form['exclusion_criteria']);
    }

    // --- Work Model / Reporting helpers ---

    private function hasWorkModelData(): bool
    {
        $wm = $this->form['work_model'] ?? [];

        return ($wm['type'] ?? '') !== ''
            || ! empty($wm['travel_required'])
            || ! empty($wm['self_organized'])
            || ($wm['location_notes'] ?? '') !== '';
    }

    private function hasReportingData(): bool
    {
        $r = $this->form['reporting'] ?? [];

        return ($r['reports_to'] ?? '') !== ''
            || ($r['autonomy_level'] ?? '') !== '';
    }

    // --- Assignments ---

    #[Computed]
    public function assignments()
    {
        return $this->jobProfile->assignments()->with('person')->get();
    }

    #[Computed]
    public function assignmentStats(): array
    {
        $assignments = $this->assignments;

        return [
            'count' => $assignments->count(),
            'avg_percentage' => $assignments->count() > 0
                ? round($assignments->avg('percentage') ?? 0)
                : 0,
        ];
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

    public function storeAssignment(): void
    {
        if (empty($this->assignForm['person_entity_id'])) {
            return;
        }

        $teamId = Auth::user()->currentTeam->id;

        OrganizationPersonJobProfile::create([
            'team_id'          => $teamId,
            'job_profile_id'   => $this->jobProfile->id,
            'person_entity_id' => (int) $this->assignForm['person_entity_id'],
            'percentage'       => $this->assignForm['percentage'] !== '' ? (int) $this->assignForm['percentage'] : null,
            'is_primary'       => (bool) $this->assignForm['is_primary'],
            'valid_from'       => $this->assignForm['valid_from'] ?: null,
            'valid_to'         => $this->assignForm['valid_to'] ?: null,
            'note'             => $this->assignForm['note'] !== '' ? $this->assignForm['note'] : null,
        ]);

        $this->reset('assignForm');
        $this->assignForm['percentage'] = '100';
        unset($this->assignments, $this->assignmentStats);
        $this->dispatch('toast', message: 'Zuweisung erstellt');
    }

    public function deleteAssignment(int $id): void
    {
        $teamId = Auth::user()->currentTeam->id;
        $assignment = OrganizationPersonJobProfile::where('team_id', $teamId)->find($id);
        if ($assignment) {
            $assignment->delete();
            unset($this->assignments, $this->assignmentStats);
            $this->dispatch('toast', message: 'Zuweisung entfernt');
        }
    }

    #[Computed]
    public function availableEntities()
    {
        return OrganizationEntity::where('team_id', Auth::user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function isDirty(): bool
    {
        $jp = $this->jobProfile;

        return $this->form['name'] !== (string) $jp->name
            || $this->form['description'] !== (string) ($jp->description ?? '')
            || $this->form['purpose'] !== (string) ($jp->purpose ?? '')
            || $this->form['job_family'] !== (string) ($jp->job_family ?? '')
            || $this->form['content'] !== (string) ($jp->content ?? '')
            || $this->form['level'] !== (string) ($jp->level ?? '')
            || $this->form['status'] !== (string) ($jp->status ?? 'active')
            || $this->form['owner_entity_id'] !== (string) ($jp->owner_entity_id ?? '')
            || $this->form['effective_from'] !== $jp->effective_from?->toDateString()
            || $this->form['effective_to'] !== $jp->effective_to?->toDateString()
            || $this->form['skills'] !== ($jp->skills ?? [])
            || $this->form['responsibilities'] !== ($jp->responsibilities ?? [])
            || $this->form['requirements'] !== ($jp->requirements ?? [])
            || $this->form['soft_skills'] !== ($jp->soft_skills ?? [])
            || $this->form['kpis'] !== ($jp->kpis ?? [])
            || $this->form['exclusion_criteria'] !== ($jp->exclusion_criteria ?? [])
            || $this->form['work_model'] !== ($jp->work_model ?? ['type' => '', 'travel_required' => false, 'self_organized' => false, 'location_notes' => ''])
            || $this->form['reporting'] !== ($jp->reporting ?? ['reports_to' => '', 'autonomy_level' => '']);
    }

    public function render()
    {
        return view('organization::livewire.job-profile.show')
            ->layout('platform::layouts.app');
    }
}
