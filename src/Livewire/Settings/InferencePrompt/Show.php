<?php

namespace Platform\Organization\Livewire\Settings\InferencePrompt;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationInferenceRun;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;

class Show extends Component
{
    public OrganizationSignalInferencePrompt $prompt;

    public array $form = [
        'name' => '',
        'description' => '',
        'vsm_system' => 's3',
        'prompt_template' => '',
        'data_sources' => [],
        'dimension' => null,
        'default_severity' => 'warning',
        'scope_type' => 'all',
        'schedule_interval_hours' => null,
        'is_active' => true,
        'agent_entity_id' => null,
    ];

    public const VSM_OPTIONS = [
        's1' => 'S1 · Operation',
        's2' => 'S2 · Koordination',
        's3' => 'S3 · Kontrolle',
        's3_star' => 'S3* · Audit',
        's4' => 'S4 · Intelligenz',
        's5' => 'S5 · Identität',
    ];

    public const SEVERITY_OPTIONS = [
        'info' => 'Info',
        'warning' => 'Warning',
        'critical' => 'Critical',
    ];

    public const DATA_SOURCE_OPTIONS = [
        'snapshots' => 'Snapshots',
        'movement' => 'Movement / Deltas',
        'correspondence' => 'Correspondence',
        'recordings' => 'Recordings',
        'activity_log' => 'Activity Log',
        'environment' => 'Environment',
        'interlinks' => 'Interlinks',
        'zukunftsbild' => 'Zukunftsbild',
    ];

    public const SCOPE_OPTIONS = [
        'all' => 'Alle Entities',
        'entity_type' => 'Nach Entity-Type',
        'subtree' => 'Subtree einer Entity',
    ];

    public function mount(OrganizationSignalInferencePrompt $prompt): void
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;

        if (! $teamId || (int) $prompt->team_id !== (int) $teamId) {
            abort(404);
        }

        $this->prompt = $prompt->load(['agentEntity:id,name,code,is_active,entity_type_id', 'agentEntity.type:id,name,code']);
        $this->loadForm();
    }

    public function loadForm(): void
    {
        $this->form = [
            'name' => $this->prompt->name,
            'description' => $this->prompt->description ?? '',
            'vsm_system' => $this->prompt->vsm_system ?? 's3',
            'prompt_template' => $this->prompt->prompt_template ?? '',
            'data_sources' => $this->prompt->data_sources ?? [],
            'dimension' => $this->prompt->dimension,
            'default_severity' => $this->prompt->default_severity ?? 'warning',
            'scope_type' => $this->prompt->scope_type ?? 'all',
            'schedule_interval_hours' => $this->prompt->schedule_interval_hours,
            'is_active' => (bool) $this->prompt->is_active,
            'agent_entity_id' => $this->prompt->agent_entity_id,
        ];
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.name' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.vsm_system' => 'required|in:s1,s2,s3,s3_star,s4,s5',
            'form.prompt_template' => 'required|string|min:10',
            'form.data_sources' => 'array',
            'form.data_sources.*' => 'string',
            'form.dimension' => 'nullable|string|max:50',
            'form.default_severity' => 'required|in:info,warning,critical',
            'form.scope_type' => 'required|in:all,entity_type,subtree',
            'form.schedule_interval_hours' => 'nullable|integer|min:1|max:8760',
            'form.is_active' => 'boolean',
            'form.agent_entity_id' => 'nullable|integer|exists:organization_entities,id',
        ])['form'];

        try {
            $this->prompt->update($data);
            $this->prompt->refresh();
            $this->loadForm();
            session()->flash('message', 'Prompt erfolgreich gespeichert.');
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', 'Validierung: ' . $e->getMessage());
        } catch (\Throwable $e) {
            session()->flash('error', 'Fehler: ' . $e->getMessage());
        }
    }

    public function toggleActive(): void
    {
        $this->prompt->update(['is_active' => !$this->prompt->is_active]);
        $this->prompt->refresh();
        $this->loadForm();
        session()->flash('message', $this->prompt->is_active
            ? 'Prompt aktiviert.'
            : 'Prompt deaktiviert.');
    }

    public function clearLastError(): void
    {
        $this->prompt->update(['last_error' => null]);
        $this->prompt->refresh();
        session()->flash('message', 'Letzter Fehler-Status entfernt.');
    }

    #[Computed]
    public function isDirty(): bool
    {
        return $this->form['name'] !== $this->prompt->name
            || ($this->form['description'] ?? '') !== ($this->prompt->description ?? '')
            || $this->form['vsm_system'] !== $this->prompt->vsm_system
            || $this->form['prompt_template'] !== $this->prompt->prompt_template
            || $this->form['data_sources'] !== ($this->prompt->data_sources ?? [])
            || $this->form['dimension'] !== $this->prompt->dimension
            || $this->form['default_severity'] !== $this->prompt->default_severity
            || $this->form['scope_type'] !== $this->prompt->scope_type
            || $this->form['schedule_interval_hours'] !== $this->prompt->schedule_interval_hours
            || (bool) $this->form['is_active'] !== (bool) $this->prompt->is_active
            || $this->form['agent_entity_id'] !== $this->prompt->agent_entity_id;
    }

    #[Computed]
    public function agentOptions()
    {
        $teamId = $this->prompt->team_id;

        return OrganizationEntity::query()
            ->where('team_id', $teamId)
            ->whereHas('type', fn ($q) => $q->where('code', 'system_agent'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($e) => ['value' => (string) $e->id, 'label' => $e->name])
            ->toArray();
    }

    #[Computed]
    public function recentRuns()
    {
        return OrganizationInferenceRun::query()
            ->whereHas('steps', fn ($q) => $q->where('inference_prompt_id', $this->prompt->id))
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();
    }

    #[Computed]
    public function recentSignals()
    {
        return OrganizationSignal::query()
            ->where('inference_prompt_id', $this->prompt->id)
            ->with('entity:id,name')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();
    }

    public function render()
    {
        return view('organization::livewire.settings.inference-prompt.show')
            ->layout('platform::layouts.app');
    }
}
