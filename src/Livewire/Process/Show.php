<?php

namespace Platform\Organization\Livewire\Process;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessStep;
use Platform\Organization\Models\OrganizationProcessFlow;
use Platform\Organization\Models\OrganizationProcessTrigger;
use Platform\Organization\Models\OrganizationProcessOutput;
use Platform\Organization\Models\OrganizationProcessSnapshot;
use Platform\Organization\Models\OrganizationProcessImprovement;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationVsmSystem;

class Show extends Component
{
    public OrganizationProcess $process;
    public array $form = [];
    public string $activeTab = 'details';

    // Step CRUD
    public bool $stepModalShow = false;
    public ?int $editingStepId = null;
    public array $stepForm = [
        'name' => '',
        'description' => '',
        'position' => '',
        'step_type' => 'task',
        'duration_target_minutes' => '',
        'wait_target_minutes' => '',
        'corefit_classification' => 'core',
        'is_active' => true,
    ];

    // Flow CRUD
    public bool $flowModalShow = false;
    public ?int $editingFlowId = null;
    public array $flowForm = [
        'from_step_id' => '',
        'to_step_id' => '',
        'condition_label' => '',
        'is_default' => false,
    ];

    // Trigger CRUD
    public bool $triggerModalShow = false;
    public ?int $editingTriggerId = null;
    public array $triggerForm = [
        'label' => '',
        'description' => '',
        'trigger_type' => 'manual',
        'entity_id' => '',
        'source_process_id' => '',
        'interlink_id' => '',
        'schedule_expression' => '',
    ];

    // Output CRUD
    public bool $outputModalShow = false;
    public ?int $editingOutputId = null;
    public array $outputForm = [
        'label' => '',
        'description' => '',
        'output_type' => 'document',
        'entity_id' => '',
        'target_process_id' => '',
        'interlink_id' => '',
    ];

    // Snapshot
    public bool $snapshotModalShow = false;
    public string $snapshotLabel = '';

    // Improvement CRUD
    public bool $improvementModalShow = false;
    public ?int $editingImprovementId = null;
    public array $improvementForm = [
        'title' => '',
        'description' => '',
        'category' => 'speed',
        'priority' => 'medium',
        'status' => 'identified',
        'expected_outcome' => '',
        'actual_outcome' => '',
    ];

    public function mount(OrganizationProcess $process)
    {
        $this->process = $process->load(['ownerEntity', 'vsmSystem', 'user']);
        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name'                  => $this->process->name,
            'code'                  => $this->process->code ?? '',
            'description'           => $this->process->description ?? '',
            'status'                => $this->process->status ?? 'draft',
            'owner_entity_id'       => (string) ($this->process->owner_entity_id ?? ''),
            'vsm_system_id'         => (string) ($this->process->vsm_system_id ?? ''),
            'version'               => (string) ($this->process->version ?? '1'),
            'is_active'             => $this->process->is_active,
            'target_description'    => $this->process->target_description ?? '',
            'value_proposition'     => $this->process->value_proposition ?? '',
            'cost_analysis'         => $this->process->cost_analysis ?? '',
            'risk_assessment'       => $this->process->risk_assessment ?? '',
            'improvement_levers'    => $this->process->improvement_levers ?? '',
            'action_plan'           => $this->process->action_plan ?? '',
            'standardization_notes' => $this->process->standardization_notes ?? '',
        ];
    }

    #[Computed]
    public function isDirty()
    {
        return $this->form['name'] !== ($this->process->name ?? '') ||
               $this->form['code'] !== ($this->process->code ?? '') ||
               $this->form['description'] !== ($this->process->description ?? '') ||
               $this->form['status'] !== ($this->process->status ?? 'draft') ||
               $this->form['owner_entity_id'] != ($this->process->owner_entity_id ?? '') ||
               $this->form['vsm_system_id'] != ($this->process->vsm_system_id ?? '') ||
               (int) $this->form['version'] !== ($this->process->version ?? 1) ||
               $this->form['is_active'] !== $this->process->is_active ||
               $this->form['target_description'] !== ($this->process->target_description ?? '') ||
               $this->form['value_proposition'] !== ($this->process->value_proposition ?? '') ||
               $this->form['cost_analysis'] !== ($this->process->cost_analysis ?? '') ||
               $this->form['risk_assessment'] !== ($this->process->risk_assessment ?? '') ||
               $this->form['improvement_levers'] !== ($this->process->improvement_levers ?? '') ||
               $this->form['action_plan'] !== ($this->process->action_plan ?? '') ||
               $this->form['standardization_notes'] !== ($this->process->standardization_notes ?? '');
    }

    #[Computed]
    public function steps()
    {
        return $this->process->steps()->orderBy('position')->get();
    }

    #[Computed]
    public function flows()
    {
        return $this->process->flows()->with(['fromStep', 'toStep'])->get();
    }

    #[Computed]
    public function triggers()
    {
        return $this->process->triggers()->with(['entity', 'sourceProcess', 'interlink'])->get();
    }

    #[Computed]
    public function outputs()
    {
        return $this->process->outputs()->with(['entity', 'targetProcess', 'interlink'])->get();
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

    #[Computed]
    public function availableProcesses()
    {
        return OrganizationProcess::where('team_id', Auth::user()->currentTeam->id)
            ->where('id', '!=', $this->process->id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function processSnapshots()
    {
        return $this->process->snapshots()->orderByDesc('version')->get();
    }

    #[Computed]
    public function processImprovements()
    {
        return $this->process->improvements()->orderByDesc('created_at')->get();
    }

    // ── Process save/delete ─────────────────────────────────────

    public function save()
    {
        $this->validate([
            'form.name'                  => 'required|string|max:255',
            'form.code'                  => 'nullable|string|max:100',
            'form.description'           => 'nullable|string',
            'form.status'                => 'required|in:draft,active,deprecated',
            'form.owner_entity_id'       => 'nullable|integer|exists:organization_entities,id',
            'form.vsm_system_id'         => 'nullable|integer|exists:organization_vsm_systems,id',
            'form.version'               => 'required|integer|min:1',
            'form.is_active'             => 'boolean',
            'form.target_description'    => 'nullable|string',
            'form.value_proposition'     => 'nullable|string',
            'form.cost_analysis'         => 'nullable|string',
            'form.risk_assessment'       => 'nullable|string',
            'form.improvement_levers'    => 'nullable|string',
            'form.action_plan'           => 'nullable|string',
            'form.standardization_notes' => 'nullable|string',
        ]);

        $this->process->update([
            'name'                  => $this->form['name'],
            'code'                  => $this->form['code'] !== '' ? $this->form['code'] : null,
            'description'           => $this->form['description'] !== '' ? $this->form['description'] : null,
            'status'                => $this->form['status'],
            'owner_entity_id'       => $this->form['owner_entity_id'] !== '' ? (int) $this->form['owner_entity_id'] : null,
            'vsm_system_id'         => $this->form['vsm_system_id'] !== '' ? (int) $this->form['vsm_system_id'] : null,
            'version'               => (int) $this->form['version'],
            'is_active'             => $this->form['is_active'],
            'target_description'    => $this->form['target_description'] !== '' ? $this->form['target_description'] : null,
            'value_proposition'     => $this->form['value_proposition'] !== '' ? $this->form['value_proposition'] : null,
            'cost_analysis'         => $this->form['cost_analysis'] !== '' ? $this->form['cost_analysis'] : null,
            'risk_assessment'       => $this->form['risk_assessment'] !== '' ? $this->form['risk_assessment'] : null,
            'improvement_levers'    => $this->form['improvement_levers'] !== '' ? $this->form['improvement_levers'] : null,
            'action_plan'           => $this->form['action_plan'] !== '' ? $this->form['action_plan'] : null,
            'standardization_notes' => $this->form['standardization_notes'] !== '' ? $this->form['standardization_notes'] : null,
        ]);

        $this->process->refresh();
        $this->loadForm();
        $this->dispatch('toast', message: 'Prozess gespeichert');
    }

    public function delete()
    {
        $this->process->delete();
        $this->dispatch('toast', message: 'Prozess gelöscht');

        return redirect()->route('organization.processes.index');
    }

    // ── Step CRUD ───────────────────────────────────────────────

    public function createStep(): void
    {
        $this->resetValidation();
        $this->editingStepId = null;
        $this->stepForm = [
            'name' => '',
            'description' => '',
            'position' => (string) (($this->steps->max('position') ?? 0) + 1),
            'step_type' => 'task',
            'duration_target_minutes' => '',
            'wait_target_minutes' => '',
            'corefit_classification' => 'core',
            'is_active' => true,
        ];
        $this->stepModalShow = true;
    }

    public function editStep(int $id): void
    {
        $step = $this->process->steps()->find($id);
        if (! $step) return;

        $this->resetValidation();
        $this->editingStepId = $step->id;
        $this->stepForm = [
            'name'                    => $step->name,
            'description'             => $step->description ?? '',
            'position'                => (string) $step->position,
            'step_type'               => $step->step_type ?? 'task',
            'duration_target_minutes' => (string) ($step->duration_target_minutes ?? ''),
            'wait_target_minutes'     => (string) ($step->wait_target_minutes ?? ''),
            'corefit_classification'  => $step->corefit_classification ?? 'core',
            'is_active'               => $step->is_active,
        ];
        $this->stepModalShow = true;
    }

    public function storeStep(): void
    {
        $this->validate([
            'stepForm.name'                    => 'required|string|max:255',
            'stepForm.description'             => 'nullable|string',
            'stepForm.position'                => 'required|integer|min:1',
            'stepForm.step_type'               => 'required|in:task,decision,event,subprocess',
            'stepForm.duration_target_minutes' => 'nullable|integer|min:0',
            'stepForm.wait_target_minutes'     => 'nullable|integer|min:0',
            'stepForm.corefit_classification'  => 'required|in:core,context,no_fit',
            'stepForm.is_active'               => 'boolean',
        ]);

        $payload = [
            'name'                    => $this->stepForm['name'],
            'description'             => $this->stepForm['description'] !== '' ? $this->stepForm['description'] : null,
            'position'                => (int) $this->stepForm['position'],
            'step_type'               => $this->stepForm['step_type'],
            'duration_target_minutes' => $this->stepForm['duration_target_minutes'] !== '' ? (int) $this->stepForm['duration_target_minutes'] : null,
            'wait_target_minutes'     => $this->stepForm['wait_target_minutes'] !== '' ? (int) $this->stepForm['wait_target_minutes'] : null,
            'corefit_classification'  => $this->stepForm['corefit_classification'],
            'is_active'               => $this->stepForm['is_active'],
        ];

        if ($this->editingStepId) {
            $step = $this->process->steps()->find($this->editingStepId);
            $step?->update($payload);
            $this->dispatch('toast', message: 'Schritt aktualisiert');
        } else {
            $this->process->steps()->create(array_merge($payload, [
                'team_id' => Auth::user()->currentTeam->id,
                'user_id' => Auth::id(),
            ]));
            $this->dispatch('toast', message: 'Schritt erstellt');
        }

        $this->stepModalShow = false;
        unset($this->steps);
    }

    public function deleteStep(int $id): void
    {
        $this->process->steps()->where('id', $id)->delete();
        $this->dispatch('toast', message: 'Schritt gelöscht');
        unset($this->steps);
    }

    // ── Flow CRUD ───────────────────────────────────────────────

    public function createFlow(): void
    {
        $this->resetValidation();
        $this->editingFlowId = null;
        $this->flowForm = ['from_step_id' => '', 'to_step_id' => '', 'condition_label' => '', 'is_default' => false];
        $this->flowModalShow = true;
    }

    public function editFlow(int $id): void
    {
        $flow = $this->process->flows()->find($id);
        if (! $flow) return;

        $this->resetValidation();
        $this->editingFlowId = $flow->id;
        $this->flowForm = [
            'from_step_id'    => (string) $flow->from_step_id,
            'to_step_id'      => (string) $flow->to_step_id,
            'condition_label' => $flow->condition_label ?? '',
            'is_default'      => $flow->is_default,
        ];
        $this->flowModalShow = true;
    }

    public function storeFlow(): void
    {
        $this->validate([
            'flowForm.from_step_id'    => 'required|integer|exists:organization_process_steps,id',
            'flowForm.to_step_id'      => 'required|integer|exists:organization_process_steps,id',
            'flowForm.condition_label' => 'nullable|string|max:255',
            'flowForm.is_default'      => 'boolean',
        ]);

        $payload = [
            'from_step_id'    => (int) $this->flowForm['from_step_id'],
            'to_step_id'      => (int) $this->flowForm['to_step_id'],
            'condition_label' => $this->flowForm['condition_label'] !== '' ? $this->flowForm['condition_label'] : null,
            'is_default'      => $this->flowForm['is_default'],
        ];

        if ($this->editingFlowId) {
            $flow = $this->process->flows()->find($this->editingFlowId);
            $flow?->update($payload);
            $this->dispatch('toast', message: 'Flow aktualisiert');
        } else {
            $this->process->flows()->create(array_merge($payload, [
                'team_id' => Auth::user()->currentTeam->id,
                'user_id' => Auth::id(),
            ]));
            $this->dispatch('toast', message: 'Flow erstellt');
        }

        $this->flowModalShow = false;
        unset($this->flows);
    }

    public function deleteFlow(int $id): void
    {
        $this->process->flows()->where('id', $id)->delete();
        $this->dispatch('toast', message: 'Flow gelöscht');
        unset($this->flows);
    }

    // ── Trigger CRUD ────────────────────────────────────────────

    public function createTrigger(): void
    {
        $this->resetValidation();
        $this->editingTriggerId = null;
        $this->triggerForm = [
            'label' => '', 'description' => '', 'trigger_type' => 'manual',
            'entity_id' => '', 'source_process_id' => '', 'interlink_id' => '', 'schedule_expression' => '',
        ];
        $this->triggerModalShow = true;
    }

    public function editTrigger(int $id): void
    {
        $trigger = $this->process->triggers()->find($id);
        if (! $trigger) return;

        $this->resetValidation();
        $this->editingTriggerId = $trigger->id;
        $this->triggerForm = [
            'label'               => $trigger->label,
            'description'         => $trigger->description ?? '',
            'trigger_type'        => $trigger->trigger_type ?? 'manual',
            'entity_id'           => (string) ($trigger->entity_id ?? ''),
            'source_process_id'   => (string) ($trigger->source_process_id ?? ''),
            'interlink_id'        => (string) ($trigger->interlink_id ?? ''),
            'schedule_expression' => $trigger->schedule_expression ?? '',
        ];
        $this->triggerModalShow = true;
    }

    public function storeTrigger(): void
    {
        $this->validate([
            'triggerForm.label'               => 'required|string|max:255',
            'triggerForm.description'          => 'nullable|string',
            'triggerForm.trigger_type'         => 'required|in:manual,scheduled,event,process_output,interlink',
            'triggerForm.entity_id'            => 'nullable|integer|exists:organization_entities,id',
            'triggerForm.source_process_id'    => 'nullable|integer|exists:organization_processes,id',
            'triggerForm.interlink_id'         => 'nullable|integer|exists:organization_interlinks,id',
            'triggerForm.schedule_expression'  => 'nullable|string|max:255',
        ]);

        $payload = [
            'label'               => $this->triggerForm['label'],
            'description'         => $this->triggerForm['description'] !== '' ? $this->triggerForm['description'] : null,
            'trigger_type'        => $this->triggerForm['trigger_type'],
            'entity_id'           => $this->triggerForm['entity_id'] !== '' ? (int) $this->triggerForm['entity_id'] : null,
            'source_process_id'   => $this->triggerForm['source_process_id'] !== '' ? (int) $this->triggerForm['source_process_id'] : null,
            'interlink_id'        => $this->triggerForm['interlink_id'] !== '' ? (int) $this->triggerForm['interlink_id'] : null,
            'schedule_expression' => $this->triggerForm['schedule_expression'] !== '' ? $this->triggerForm['schedule_expression'] : null,
        ];

        if ($this->editingTriggerId) {
            $trigger = $this->process->triggers()->find($this->editingTriggerId);
            $trigger?->update($payload);
            $this->dispatch('toast', message: 'Trigger aktualisiert');
        } else {
            $this->process->triggers()->create(array_merge($payload, [
                'team_id' => Auth::user()->currentTeam->id,
                'user_id' => Auth::id(),
            ]));
            $this->dispatch('toast', message: 'Trigger erstellt');
        }

        $this->triggerModalShow = false;
        unset($this->triggers);
    }

    public function deleteTrigger(int $id): void
    {
        $this->process->triggers()->where('id', $id)->delete();
        $this->dispatch('toast', message: 'Trigger gelöscht');
        unset($this->triggers);
    }

    // ── Output CRUD ─────────────────────────────────────────────

    public function createOutput(): void
    {
        $this->resetValidation();
        $this->editingOutputId = null;
        $this->outputForm = [
            'label' => '', 'description' => '', 'output_type' => 'document',
            'entity_id' => '', 'target_process_id' => '', 'interlink_id' => '',
        ];
        $this->outputModalShow = true;
    }

    public function editOutput(int $id): void
    {
        $output = $this->process->outputs()->find($id);
        if (! $output) return;

        $this->resetValidation();
        $this->editingOutputId = $output->id;
        $this->outputForm = [
            'label'             => $output->label,
            'description'       => $output->description ?? '',
            'output_type'       => $output->output_type ?? 'document',
            'entity_id'         => (string) ($output->entity_id ?? ''),
            'target_process_id' => (string) ($output->target_process_id ?? ''),
            'interlink_id'      => (string) ($output->interlink_id ?? ''),
        ];
        $this->outputModalShow = true;
    }

    public function storeOutput(): void
    {
        $this->validate([
            'outputForm.label'             => 'required|string|max:255',
            'outputForm.description'       => 'nullable|string',
            'outputForm.output_type'       => 'required|in:document,data,notification,process_trigger,interlink',
            'outputForm.entity_id'         => 'nullable|integer|exists:organization_entities,id',
            'outputForm.target_process_id' => 'nullable|integer|exists:organization_processes,id',
            'outputForm.interlink_id'      => 'nullable|integer|exists:organization_interlinks,id',
        ]);

        $payload = [
            'label'             => $this->outputForm['label'],
            'description'       => $this->outputForm['description'] !== '' ? $this->outputForm['description'] : null,
            'output_type'       => $this->outputForm['output_type'],
            'entity_id'         => $this->outputForm['entity_id'] !== '' ? (int) $this->outputForm['entity_id'] : null,
            'target_process_id' => $this->outputForm['target_process_id'] !== '' ? (int) $this->outputForm['target_process_id'] : null,
            'interlink_id'      => $this->outputForm['interlink_id'] !== '' ? (int) $this->outputForm['interlink_id'] : null,
        ];

        if ($this->editingOutputId) {
            $output = $this->process->outputs()->find($this->editingOutputId);
            $output?->update($payload);
            $this->dispatch('toast', message: 'Output aktualisiert');
        } else {
            $this->process->outputs()->create(array_merge($payload, [
                'team_id' => Auth::user()->currentTeam->id,
                'user_id' => Auth::id(),
            ]));
            $this->dispatch('toast', message: 'Output erstellt');
        }

        $this->outputModalShow = false;
        unset($this->outputs);
    }

    public function deleteOutput(int $id): void
    {
        $this->process->outputs()->where('id', $id)->delete();
        $this->dispatch('toast', message: 'Output gelöscht');
        unset($this->outputs);
    }

    // ── Snapshot CRUD ───────────────────────────────────────────

    public function createSnapshot(): void
    {
        $this->resetValidation();
        $this->snapshotLabel = '';
        $this->snapshotModalShow = true;
    }

    public function storeSnapshot(): void
    {
        $process = $this->process->load(['steps', 'flows', 'triggers', 'outputs']);
        $maxVersion = $process->snapshots()->max('version') ?? 0;
        $nextVersion = $maxVersion + 1;

        $snapshotData = [
            'process' => $process->only([
                'name', 'code', 'description', 'status', 'version', 'is_active',
                'owner_entity_id', 'vsm_system_id', 'metadata',
                'target_description', 'value_proposition', 'cost_analysis',
                'risk_assessment', 'improvement_levers', 'action_plan', 'standardization_notes',
            ]),
            'steps'    => $process->steps->map(fn ($s) => $s->only([
                'id', 'name', 'description', 'position', 'step_type',
                'duration_target_minutes', 'wait_target_minutes',
                'corefit_classification', 'is_active',
            ]))->values()->toArray(),
            'flows'    => $process->flows->map(fn ($f) => $f->only([
                'id', 'from_step_id', 'to_step_id', 'condition_label', 'is_default',
            ]))->values()->toArray(),
            'triggers' => $process->triggers->map(fn ($t) => $t->only([
                'id', 'label', 'description', 'trigger_type',
                'entity_id', 'source_process_id', 'interlink_id', 'schedule_expression',
            ]))->values()->toArray(),
            'outputs'  => $process->outputs->map(fn ($o) => $o->only([
                'id', 'label', 'description', 'output_type',
                'entity_id', 'target_process_id', 'interlink_id',
            ]))->values()->toArray(),
        ];

        $steps = $process->steps;
        $corefitCounts = $steps->groupBy('corefit_classification')->map->count();
        $metrics = [
            'total_steps'    => $steps->count(),
            'total_flows'    => $process->flows->count(),
            'total_triggers' => $process->triggers->count(),
            'total_outputs'  => $process->outputs->count(),
            'total_duration' => $steps->sum('duration_target_minutes') ?? 0,
            'total_wait'     => $steps->sum('wait_target_minutes') ?? 0,
            'corefit' => [
                'core'    => $corefitCounts->get('core', 0),
                'context' => $corefitCounts->get('context', 0),
                'no_fit'  => $corefitCounts->get('no_fit', 0),
            ],
        ];

        OrganizationProcessSnapshot::create([
            'process_id'         => $process->id,
            'version'            => $nextVersion,
            'label'              => $this->snapshotLabel !== '' ? $this->snapshotLabel : null,
            'snapshot_data'      => $snapshotData,
            'metrics'            => $metrics,
            'created_by_user_id' => Auth::id(),
        ]);

        $this->snapshotModalShow = false;
        unset($this->processSnapshots);
        $this->dispatch('toast', message: "Snapshot v{$nextVersion} erstellt");
    }

    public function deleteSnapshot(int $id): void
    {
        OrganizationProcessSnapshot::where('id', $id)->where('process_id', $this->process->id)->delete();
        unset($this->processSnapshots);
        $this->dispatch('toast', message: 'Snapshot gelöscht');
    }

    // ── Improvement CRUD ────────────────────────────────────────

    public function createImprovement(): void
    {
        $this->resetValidation();
        $this->editingImprovementId = null;
        $this->improvementForm = [
            'title' => '', 'description' => '', 'category' => 'speed',
            'priority' => 'medium', 'status' => 'identified',
            'expected_outcome' => '', 'actual_outcome' => '',
        ];
        $this->improvementModalShow = true;
    }

    public function editImprovement(int $id): void
    {
        $imp = $this->process->improvements()->find($id);
        if (! $imp) return;

        $this->resetValidation();
        $this->editingImprovementId = $imp->id;
        $this->improvementForm = [
            'title'            => $imp->title,
            'description'      => $imp->description ?? '',
            'category'         => $imp->category,
            'priority'         => $imp->priority,
            'status'           => $imp->status,
            'expected_outcome' => $imp->expected_outcome ?? '',
            'actual_outcome'   => $imp->actual_outcome ?? '',
        ];
        $this->improvementModalShow = true;
    }

    public function storeImprovement(): void
    {
        $this->validate([
            'improvementForm.title'            => 'required|string|max:255',
            'improvementForm.description'      => 'nullable|string',
            'improvementForm.category'         => 'required|in:cost,quality,speed,risk,standardization',
            'improvementForm.priority'         => 'required|in:low,medium,high,critical',
            'improvementForm.status'           => 'required|in:identified,planned,in_progress,completed,rejected',
            'improvementForm.expected_outcome' => 'nullable|string',
            'improvementForm.actual_outcome'   => 'nullable|string',
        ]);

        $payload = [
            'title'            => $this->improvementForm['title'],
            'description'      => $this->improvementForm['description'] !== '' ? $this->improvementForm['description'] : null,
            'category'         => $this->improvementForm['category'],
            'priority'         => $this->improvementForm['priority'],
            'status'           => $this->improvementForm['status'],
            'expected_outcome' => $this->improvementForm['expected_outcome'] !== '' ? $this->improvementForm['expected_outcome'] : null,
            'actual_outcome'   => $this->improvementForm['actual_outcome'] !== '' ? $this->improvementForm['actual_outcome'] : null,
        ];

        if ($this->improvementForm['status'] === 'completed') {
            $payload['completed_at'] = now();
        }

        if ($this->editingImprovementId) {
            $imp = $this->process->improvements()->find($this->editingImprovementId);
            if ($imp) {
                if ($this->improvementForm['status'] !== 'completed') {
                    $payload['completed_at'] = null;
                }
                $imp->update($payload);
            }
            $this->dispatch('toast', message: 'Verbesserung aktualisiert');
        } else {
            $this->process->improvements()->create(array_merge($payload, [
                'team_id' => Auth::user()->currentTeam->id,
                'user_id' => Auth::id(),
            ]));
            $this->dispatch('toast', message: 'Verbesserung erstellt');
        }

        $this->improvementModalShow = false;
        unset($this->processImprovements);
    }

    public function deleteImprovement(int $id): void
    {
        $this->process->improvements()->where('id', $id)->delete();
        unset($this->processImprovements);
        $this->dispatch('toast', message: 'Verbesserung gelöscht');
    }

    public function render()
    {
        return view('organization::livewire.process.show')
            ->layout('platform::layouts.app');
    }
}
