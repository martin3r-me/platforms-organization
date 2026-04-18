<?php

namespace Platform\Organization\Livewire\Process;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Enums\ProcessCategory;
use Platform\Organization\Enums\ProcessFrequency;
use Platform\Organization\Enums\StepComplexity;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessStep;
use Platform\Organization\Models\OrganizationProcessFlow;
use Platform\Organization\Models\OrganizationProcessTrigger;
use Platform\Organization\Models\OrganizationProcessOutput;
use Platform\Organization\Models\OrganizationProcessSnapshot;
use Platform\Organization\Models\OrganizationProcessImprovement;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationVsmSystem;
use Platform\Organization\Services\ProcessCertificateService;
use Illuminate\Support\Str;

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
        'automation_level' => 'human',
        'complexity' => '',
        'is_active' => true,
        'llm_tools' => [],
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
        'entity_scope' => 'none',
        'entity_type_id' => '',
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
        'category' => 'speed',
        'priority' => 'medium',
        'status' => 'identified',
        'target_step_id' => '',
        'projected_duration_target_minutes' => '',
        'projected_automation_level' => '',
        'projected_complexity' => '',
    ];

    public function mount(OrganizationProcess $process)
    {
        $this->process = $process->load(['ownerEntity', 'vsmSystem', 'user']);

        // Backward-compat: alter zusammengeführter Tab (chaining) → triggers
        if ($this->activeTab === 'chaining') {
            $this->activeTab = 'triggers';
        }

        $this->loadForm();
    }

    public function loadForm()
    {
        $this->form = [
            'name'                  => $this->process->name,
            'code'                  => $this->process->code ?? '',
            'description'           => $this->process->description ?? '',
            'status'                => $this->process->status ?? 'draft',
            'process_category'      => (string) ($this->process->process_category?->value ?? ''),
            'is_focus'              => (bool) $this->process->is_focus,
            'focus_reason'          => (string) ($this->process->focus_reason ?? ''),
            'focus_until'           => $this->process->focus_until?->format('Y-m-d'),
            'owner_entity_id'       => (string) ($this->process->owner_entity_id ?? ''),
            'vsm_system_id'         => (string) ($this->process->vsm_system_id ?? ''),
            'version'               => (string) ($this->process->version ?? '1'),
            'is_active'             => $this->process->is_active,
            'hourly_rate'           => (string) ($this->process->hourly_rate ?? ''),
            'frequency'             => (string) ($this->process->frequency?->value ?? ''),
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
               $this->form['process_category'] !== (string) ($this->process->process_category?->value ?? '') ||
               $this->form['is_focus'] !== (bool) $this->process->is_focus ||
               $this->form['focus_reason'] !== (string) ($this->process->focus_reason ?? '') ||
               $this->form['focus_until'] !== $this->process->focus_until?->format('Y-m-d') ||
               $this->form['owner_entity_id'] != ($this->process->owner_entity_id ?? '') ||
               $this->form['vsm_system_id'] != ($this->process->vsm_system_id ?? '') ||
               (int) $this->form['version'] !== ($this->process->version ?? 1) ||
               $this->form['is_active'] !== $this->process->is_active ||
               $this->form['hourly_rate'] !== (string) ($this->process->hourly_rate ?? '') ||
               $this->form['frequency'] !== (string) ($this->process->frequency?->value ?? '') ||
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
        return $this->process->steps()
            ->with(['subProcess:id,name,code'])
            ->orderBy('position')
            ->get();
    }

    #[Computed]
    public function chains()
    {
        return $this->process->chains()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function flows()
    {
        return $this->process->flows()->with(['fromStep', 'toStep'])->get();
    }

    #[Computed]
    public function triggers()
    {
        return $this->process->triggers()->with(['entityType', 'entity', 'sourceProcess', 'interlink'])->get();
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
    public function groupedEntityOptions(): array
    {
        $entities = OrganizationEntity::where('team_id', Auth::user()->currentTeam->id)
            ->with(['type.group', 'parent'])
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        // Build tree: root entities first (no parent), then children indented
        $result = [];
        $byType = $entities->groupBy(fn ($e) => $e->type?->group?->sort_order ?? 999);

        // Sort by group sort_order
        $sorted = $byType->sortKeys();

        foreach ($sorted as $entities_in_group) {
            // Sort by type sort_order, then by name
            $typed = $entities_in_group->sortBy([
                fn ($a, $b) => ($a->type?->sort_order ?? 999) <=> ($b->type?->sort_order ?? 999),
                fn ($a, $b) => $a->name <=> $b->name,
            ]);

            // Separate roots and children
            $roots = $typed->whereNull('parent_entity_id');
            $childrenByParent = $typed->whereNotNull('parent_entity_id')->groupBy('parent_entity_id');

            foreach ($roots as $root) {
                $typeName = $root->type?->name ?? '';
                $result[] = [
                    'value' => (string) $root->id,
                    'label' => ($typeName ? $typeName . ' / ' : '') . $root->name,
                ];
                $this->addChildOptions($result, $root->id, $entities, 1);
            }
        }

        // Also add orphan children whose parent is in a different type group
        $usedIds = collect($result)->pluck('value')->toArray();
        foreach ($entities as $e) {
            if (! in_array((string) $e->id, $usedIds, true)) {
                $typeName = $e->type?->name ?? '';
                $result[] = [
                    'value' => (string) $e->id,
                    'label' => ($typeName ? $typeName . ' / ' : '') . $e->name,
                ];
            }
        }

        return $result;
    }

    private function addChildOptions(array &$result, int $parentId, $entities, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $children = $entities->where('parent_entity_id', $parentId)->sortBy('name');

        foreach ($children as $child) {
            $result[] = [
                'value' => (string) $child->id,
                'label' => $indent . '└ ' . $child->name,
            ];
            $this->addChildOptions($result, $child->id, $entities, $depth + 1);
        }
    }

    #[Computed]
    public function availableEntityTypes()
    {
        return OrganizationEntityType::active()->ordered()->get();
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

    #[Computed]
    public function corefitMetrics(): array
    {
        $steps = $this->steps;
        $totalSteps = $steps->count();

        $empty = ['count' => 0, 'minutes' => 0, 'wait' => 0, 'percent' => 0, 'cost' => 0];
        if ($totalSteps === 0) {
            return [
                'total_steps' => 0,
                'total_duration' => 0,
                'total_wait' => 0,
                'lead_time' => 0,
                'efficiency' => 0,
                'core' => $empty,
                'context' => $empty,
                'no_fit' => $empty,
                'total_cost' => 0,
            ];
        }

        $hourlyRate = (float) ($this->form['hourly_rate'] ?? 0);
        $minuteRate = $hourlyRate > 0 ? $hourlyRate / 60 : 0;

        $grouped = $steps->groupBy('corefit_classification');
        $totalDuration = $steps->sum('duration_target_minutes') ?? 0;
        $totalWait = $steps->sum('wait_target_minutes') ?? 0;
        $leadTime = $totalDuration + $totalWait;
        $efficiency = $leadTime > 0 ? round(($totalDuration / $leadTime) * 100, 1) : 0;

        $result = [
            'total_steps' => $totalSteps,
            'total_duration' => $totalDuration,
            'total_wait' => $totalWait,
            'lead_time' => $leadTime,
            'efficiency' => $efficiency,
        ];

        $totalCost = 0;
        foreach (['core', 'context', 'no_fit'] as $classification) {
            $group = $grouped->get($classification, collect());
            $count = $group->count();
            $minutes = $group->sum('duration_target_minutes') ?? 0;
            $wait = $group->sum('wait_target_minutes') ?? 0;
            $percent = $totalSteps > 0 ? round(($count / $totalSteps) * 100, 1) : 0;
            $cost = round($minutes * $minuteRate, 2);
            $totalCost += $cost;

            $result[$classification] = [
                'count' => $count,
                'minutes' => $minutes,
                'wait' => $wait,
                'percent' => $percent,
                'cost' => $cost,
            ];
        }

        $result['total_cost'] = round($totalCost, 2);

        return $result;
    }

    #[Computed]
    public function automationMetrics(): array
    {
        $steps = $this->steps;
        $totalSteps = $steps->count();

        $empty = ['count' => 0, 'percent' => 0, 'minutes' => 0];
        if ($totalSteps === 0) {
            return [
                'human' => $empty,
                'llm_assisted' => $empty,
                'llm_autonomous' => $empty,
                'hybrid' => $empty,
            ];
        }

        $grouped = $steps->groupBy('automation_level');
        $result = [];

        foreach (['human', 'llm_assisted', 'llm_autonomous', 'hybrid'] as $level) {
            $group = $grouped->get($level, collect());
            $count = $group->count();
            $minutes = $group->sum('duration_target_minutes') ?? 0;
            $percent = $totalSteps > 0 ? round(($count / $totalSteps) * 100, 1) : 0;

            $result[$level] = [
                'count' => $count,
                'percent' => $percent,
                'minutes' => $minutes,
            ];
        }

        return $result;
    }

    #[Computed]
    public function complexityMetrics(): array
    {
        $steps = $this->steps;
        $totalSteps = $steps->count();

        if ($totalSteps === 0) {
            return [
                'total' => 0,
                'count_with' => 0,
                'distribution' => [],
                'avg_label' => null,
                'total_points' => 0,
            ];
        }

        $distribution = [];
        foreach (StepComplexity::cases() as $case) {
            $count = $steps->filter(fn ($s) => $s->complexity === $case)->count();
            $distribution[$case->value] = [
                'count' => $count,
                'label' => strtoupper($case->value),
                'points' => $case->points(),
            ];
        }

        $withComplexity = $steps->filter(fn ($s) => $s->complexity !== null);
        $countWith = $withComplexity->count();
        $totalPoints = $withComplexity->sum(fn ($s) => $s->complexity->points());
        $avgPoints = $countWith > 0 ? round($totalPoints / $countWith, 1) : 0;

        // Find closest T-shirt size for average
        $avgLabel = null;
        if ($countWith > 0) {
            $closest = null;
            $closestDiff = PHP_INT_MAX;
            foreach (StepComplexity::cases() as $case) {
                $diff = abs($case->points() - $avgPoints);
                if ($diff < $closestDiff) {
                    $closestDiff = $diff;
                    $closest = $case;
                }
            }
            $avgLabel = $closest ? strtoupper($closest->value) : null;
        }

        return [
            'total' => $totalSteps,
            'count_with' => $countWith,
            'distribution' => $distribution,
            'avg_label' => $avgLabel,
            'avg_points' => $avgPoints,
            'total_points' => $totalPoints,
        ];
    }

    #[Computed]
    public function automationScore(): array
    {
        $steps = $this->steps;
        $totalSteps = $steps->count();

        if ($totalSteps === 0) {
            return ['score' => null, 'label' => null, 'color' => null, 'step_scores' => []];
        }

        $stepScores = [];
        $weightedSum = 0;
        $weightSum = 0;

        foreach ($steps as $step) {
            $automationLevel = $step->automation_level ?? 'human';
            $complexity = $step->complexity;
            $points = $complexity ? $complexity->points() : 1;

            $score = match ($automationLevel) {
                'llm_autonomous' => 100,
                'llm_assisted' => 85,
                'hybrid' => 70,
                default => $complexity
                    ? (int) round(15 + ($complexity->points() / 13) * 80)
                    : 30,
            };

            $stepScores[] = [
                'id' => $step->id,
                'name' => $step->name,
                'score' => $score,
                'weight' => $points,
            ];

            $weightedSum += $score * $points;
            $weightSum += $points;
        }

        $processScore = $weightSum > 0 ? (int) round($weightedSum / $weightSum) : 0;

        [$label, $color] = match (true) {
            $processScore >= 90 => ['A+', 'success'],
            $processScore >= 75 => ['A', 'success'],
            $processScore >= 60 => ['B', 'info'],
            $processScore >= 40 => ['C', 'warning'],
            $processScore >= 20 => ['D', 'danger'],
            default => ['F', 'danger'],
        };

        return [
            'score' => $processScore,
            'label' => $label,
            'color' => $color,
            'step_scores' => $stepScores,
        ];
    }

    #[Computed]
    public function costMetrics(): array
    {
        $steps = $this->steps;
        $totalDuration = $steps->sum('duration_target_minutes') ?? 0;
        $hourlyRate = (float) ($this->form['hourly_rate'] ?? 0);
        $frequencyValue = $this->form['frequency'] ?? '';
        $frequency = $frequencyValue !== '' ? ProcessFrequency::tryFrom($frequencyValue) : null;

        if ($hourlyRate <= 0 || $totalDuration <= 0 || ! $frequency) {
            return [
                'cost_per_run' => 0,
                'cost_per_month' => 0,
                'cost_per_year' => 0,
                'frequency_label' => $frequency?->label() ?? null,
                'runs_per_month' => $frequency?->monthlyFactor() ?? null,
            ];
        }

        $costPerRun = round(($totalDuration / 60) * $hourlyRate, 2);
        $costPerMonth = round($costPerRun * $frequency->monthlyFactor(), 2);
        $costPerYear = round($costPerMonth * 12, 2);

        return [
            'cost_per_run' => $costPerRun,
            'cost_per_month' => $costPerMonth,
            'cost_per_year' => $costPerYear,
            'frequency_label' => $frequency->label(),
            'runs_per_month' => $frequency->monthlyFactor(),
        ];
    }

    #[Computed]
    public function improvementSimulations(): array
    {
        $improvements = $this->processImprovements;
        $steps = $this->steps;
        $hourlyRate = (float) ($this->form['hourly_rate'] ?? 0);
        $frequencyValue = $this->form['frequency'] ?? '';
        $frequency = $frequencyValue !== '' ? ProcessFrequency::tryFrom($frequencyValue) : null;
        $monthlyFactor = $frequency?->monthlyFactor() ?? 0;

        $currentScore = $this->automationScore;
        $simulations = [];

        foreach ($improvements as $imp) {
            if (! $imp->target_step_id) {
                continue;
            }

            // Clone steps and overlay projected values on the target step
            $simulatedSteps = $steps->map(function ($step) use ($imp) {
                if ($step->id !== $imp->target_step_id) {
                    return $step;
                }

                $clone = clone $step;
                if ($imp->projected_duration_target_minutes !== null) {
                    $clone->duration_target_minutes = $imp->projected_duration_target_minutes;
                }
                if ($imp->projected_automation_level !== null) {
                    $clone->automation_level = $imp->projected_automation_level;
                }
                if ($imp->projected_complexity !== null) {
                    $clone->complexity = StepComplexity::tryFrom($imp->projected_complexity);
                }

                return $clone;
            });

            // Recalculate automation score with simulated steps
            $weightedSum = 0;
            $weightSum = 0;
            foreach ($simulatedSteps as $step) {
                $automationLevel = $step->automation_level ?? 'human';
                $complexity = $step->complexity;
                $points = $complexity ? $complexity->points() : 1;

                $score = match ($automationLevel) {
                    'llm_autonomous' => 100,
                    'llm_assisted' => 85,
                    'hybrid' => 70,
                    default => $complexity
                        ? (int) round(15 + ($complexity->points() / 13) * 80)
                        : 30,
                };

                $weightedSum += $score * $points;
                $weightSum += $points;
            }
            $projectedScore = $weightSum > 0 ? (int) round($weightedSum / $weightSum) : 0;
            $scoreDelta = $projectedScore - ($currentScore['score'] ?? 0);

            // Cost delta
            $originalDuration = $steps->sum('duration_target_minutes') ?? 0;
            $simulatedDuration = $simulatedSteps->sum('duration_target_minutes') ?? 0;
            $durationDelta = $originalDuration - $simulatedDuration;
            $costSavingPerRun = $hourlyRate > 0 ? round(($durationDelta / 60) * $hourlyRate, 2) : 0;
            $costSavingPerMonth = round($costSavingPerRun * $monthlyFactor, 2);

            $simulations[$imp->id] = [
                'score_delta' => $scoreDelta,
                'projected_score' => $projectedScore,
                'cost_saving_per_run' => $costSavingPerRun,
                'cost_saving_per_month' => $costSavingPerMonth,
                'duration_delta' => $durationDelta,
            ];
        }

        // Totals: aggregate all simulations (theoretical maximum if all applied)
        $totalCostSavingsPerMonth = collect($simulations)->sum('cost_saving_per_month');
        $totalCostSavingsPerYear = round($totalCostSavingsPerMonth * 12, 2);

        return [
            'simulations' => $simulations,
            'total_cost_savings_per_month' => $totalCostSavingsPerMonth,
            'total_cost_savings_per_year' => $totalCostSavingsPerYear,
        ];
    }

    #[Computed]
    public function efficiencyMatrix(): array
    {
        $steps = $this->steps;
        $hourlyRate = (float) ($this->form['hourly_rate'] ?? 0);
        $minuteRate = $hourlyRate > 0 ? $hourlyRate / 60 : 0;

        $matrix = [];
        foreach (['core', 'context', 'no_fit'] as $corefit) {
            foreach (['human', 'llm_assisted', 'llm_autonomous', 'hybrid'] as $auto) {
                $group = $steps->filter(fn($s) =>
                    ($s->corefit_classification ?? 'core') === $corefit &&
                    ($s->automation_level ?? 'human') === $auto
                );
                $count = $group->count();
                $minutes = $group->sum('duration_target_minutes') ?? 0;
                $cost = round($minutes * $minuteRate, 2);

                $matrix[$corefit][$auto] = [
                    'count' => $count,
                    'minutes' => $minutes,
                    'cost' => $cost,
                ];
            }
        }

        return $matrix;
    }

    #[Computed]
    public function certificateData(): array
    {
        return ProcessCertificateService::compute($this->process);
    }

    public function generatePublicLink(): void
    {
        $this->process->update([
            'public_token' => Str::random(48),
            'public_token_expires_at' => now()->addYear(),
        ]);

        $this->process->refresh();
        $this->dispatch('toast', message: 'Öffentlicher Link erstellt');
    }

    public function revokePublicLink(): void
    {
        $this->process->update([
            'public_token' => null,
            'public_token_expires_at' => null,
        ]);

        $this->process->refresh();
        $this->dispatch('toast', message: 'Öffentlicher Link widerrufen');
    }

    #[Computed]
    public function improvementsByCategory(): array
    {
        $improvements = $this->processImprovements;
        $grouped = [];

        foreach (['cost', 'quality', 'speed', 'risk', 'standardization'] as $category) {
            $catImprovements = $improvements->where('category', $category);
            $statusCounts = $catImprovements->groupBy('status')->map->count();
            $grouped[$category] = [
                'total' => $catImprovements->count(),
                'statuses' => $statusCounts->toArray(),
            ];
        }

        return $grouped;
    }

    // ── Process save/delete ─────────────────────────────────────

    public function save()
    {
        $this->validate([
            'form.name'                  => 'required|string|max:255',
            'form.code'                  => 'nullable|string|max:100',
            'form.description'           => 'nullable|string',
            'form.status'                => 'required|in:draft,under_review,pilot,active,deprecated',
            'form.process_category'      => 'nullable|in:core,support,management',
            'form.is_focus'              => 'boolean',
            'form.focus_reason'          => 'nullable|string',
            'form.focus_until'           => 'nullable|date',
            'form.owner_entity_id'       => 'nullable|integer|exists:organization_entities,id',
            'form.vsm_system_id'         => 'nullable|integer|exists:organization_vsm_systems,id',
            'form.version'               => 'required|integer|min:1',
            'form.is_active'             => 'boolean',
            'form.hourly_rate'           => 'nullable|numeric|min:0',
            'form.frequency'             => 'nullable|in:' . implode(',', ProcessFrequency::values()),
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
            'process_category'      => $this->form['process_category'] !== '' ? $this->form['process_category'] : null,
            'is_focus'              => (bool) $this->form['is_focus'],
            'focus_reason'          => $this->form['is_focus'] && $this->form['focus_reason'] !== '' ? $this->form['focus_reason'] : null,
            'focus_until'           => $this->form['is_focus'] && $this->form['focus_until'] ? $this->form['focus_until'] : null,
            'owner_entity_id'       => $this->form['owner_entity_id'] !== '' ? (int) $this->form['owner_entity_id'] : null,
            'vsm_system_id'         => $this->form['vsm_system_id'] !== '' ? (int) $this->form['vsm_system_id'] : null,
            'version'               => (int) $this->form['version'],
            'is_active'             => $this->form['is_active'],
            'hourly_rate'           => $this->form['hourly_rate'] !== '' ? (float) $this->form['hourly_rate'] : null,
            'frequency'             => $this->form['frequency'] !== '' ? $this->form['frequency'] : null,
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
        unset($this->corefitMetrics, $this->costMetrics, $this->improvementSimulations);
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
            'automation_level' => 'human',
            'complexity' => '',
            'is_active' => true,
            'llm_tools' => [],
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
            'automation_level'        => $step->automation_level ?? 'human',
            'complexity'              => $step->complexity?->value ?? '',
            'is_active'               => $step->is_active,
            'llm_tools'               => $step->llm_tools ?? [],
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
            'stepForm.automation_level'        => 'required|in:human,llm_assisted,llm_autonomous,hybrid',
            'stepForm.complexity'              => 'nullable|in:xs,s,m,l,xl,xxl',
            'stepForm.is_active'               => 'boolean',
            'stepForm.llm_tools'               => 'nullable|array',
            'stepForm.llm_tools.*.tool_name'   => 'required|string|max:255',
            'stepForm.llm_tools.*.description'  => 'nullable|string|max:500',
            'stepForm.llm_tools.*.mcp_server'   => 'nullable|string|max:255',
        ]);

        $payload = [
            'name'                    => $this->stepForm['name'],
            'description'             => $this->stepForm['description'] !== '' ? $this->stepForm['description'] : null,
            'position'                => (int) $this->stepForm['position'],
            'step_type'               => $this->stepForm['step_type'],
            'duration_target_minutes' => $this->stepForm['duration_target_minutes'] !== '' ? (int) $this->stepForm['duration_target_minutes'] : null,
            'wait_target_minutes'     => $this->stepForm['wait_target_minutes'] !== '' ? (int) $this->stepForm['wait_target_minutes'] : null,
            'corefit_classification'  => $this->stepForm['corefit_classification'],
            'automation_level'        => $this->stepForm['automation_level'],
            'complexity'              => $this->stepForm['complexity'] !== '' ? $this->stepForm['complexity'] : null,
            'llm_tools'               => !empty($this->stepForm['llm_tools']) ? $this->stepForm['llm_tools'] : null,
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
        unset($this->steps, $this->corefitMetrics, $this->automationMetrics, $this->efficiencyMatrix, $this->complexityMetrics, $this->automationScore, $this->costMetrics, $this->improvementSimulations);
    }

    public function deleteStep(int $id): void
    {
        $this->process->steps()->where('id', $id)->delete();
        $this->dispatch('toast', message: 'Schritt gelöscht');
        unset($this->steps, $this->corefitMetrics, $this->automationMetrics, $this->efficiencyMatrix, $this->complexityMetrics, $this->automationScore, $this->costMetrics, $this->improvementSimulations);
    }

    public function addLlmTool(): void
    {
        $this->stepForm['llm_tools'][] = ['tool_name' => '', 'description' => '', 'mcp_server' => ''];
    }

    public function removeLlmTool(int $index): void
    {
        unset($this->stepForm['llm_tools'][$index]);
        $this->stepForm['llm_tools'] = array_values($this->stepForm['llm_tools']);
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
            'entity_scope' => 'none', 'entity_type_id' => '', 'entity_id' => '',
            'source_process_id' => '', 'interlink_id' => '', 'schedule_expression' => '',
        ];
        $this->triggerModalShow = true;
    }

    public function editTrigger(int $id): void
    {
        $trigger = $this->process->triggers()->find($id);
        if (! $trigger) return;

        $this->resetValidation();
        $this->editingTriggerId = $trigger->id;

        $entityScope = 'none';
        if ($trigger->entity_type_id) {
            $entityScope = 'entity_type';
        } elseif ($trigger->entity_id) {
            $entityScope = 'entity';
        }

        $this->triggerForm = [
            'label'               => $trigger->label,
            'description'         => $trigger->description ?? '',
            'trigger_type'        => $trigger->trigger_type ?? 'manual',
            'entity_scope'        => $entityScope,
            'entity_type_id'      => (string) ($trigger->entity_type_id ?? ''),
            'entity_id'           => (string) ($trigger->entity_id ?? ''),
            'source_process_id'   => (string) ($trigger->source_process_id ?? ''),
            'interlink_id'        => (string) ($trigger->interlink_id ?? ''),
            'schedule_expression' => $trigger->schedule_expression ?? '',
        ];
        $this->triggerModalShow = true;
    }

    public function storeTrigger(): void
    {
        $rules = [
            'triggerForm.label'               => 'required|string|max:255',
            'triggerForm.description'          => 'nullable|string',
            'triggerForm.trigger_type'         => 'required|in:manual,scheduled,event,process_output,interlink',
            'triggerForm.source_process_id'    => 'nullable|integer|exists:organization_processes,id',
            'triggerForm.interlink_id'         => 'nullable|integer|exists:organization_interlinks,id',
            'triggerForm.schedule_expression'  => 'nullable|string|max:255',
        ];

        $entityScope = $this->triggerForm['entity_scope'] ?? 'none';
        if ($entityScope === 'entity_type') {
            $rules['triggerForm.entity_type_id'] = 'required|integer|exists:organization_entity_types,id';
        } elseif ($entityScope === 'entity') {
            $rules['triggerForm.entity_id'] = 'required|integer|exists:organization_entities,id';
        }

        $this->validate($rules);

        $payload = [
            'label'               => $this->triggerForm['label'],
            'description'         => $this->triggerForm['description'] !== '' ? $this->triggerForm['description'] : null,
            'trigger_type'        => $this->triggerForm['trigger_type'],
            'source_process_id'   => $this->triggerForm['source_process_id'] !== '' ? (int) $this->triggerForm['source_process_id'] : null,
            'interlink_id'        => $this->triggerForm['interlink_id'] !== '' ? (int) $this->triggerForm['interlink_id'] : null,
            'schedule_expression' => $this->triggerForm['schedule_expression'] !== '' ? $this->triggerForm['schedule_expression'] : null,
        ];

        // Either-or: entity_type_id OR entity_id, never both
        if ($entityScope === 'entity_type') {
            $payload['entity_type_id'] = (int) $this->triggerForm['entity_type_id'];
            $payload['entity_id'] = null;
        } elseif ($entityScope === 'entity') {
            $payload['entity_id'] = (int) $this->triggerForm['entity_id'];
            $payload['entity_type_id'] = null;
        } else {
            $payload['entity_type_id'] = null;
            $payload['entity_id'] = null;
        }

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
                'hourly_rate',
            ]),
            'steps'    => $process->steps->map(fn ($s) => $s->only([
                'id', 'name', 'description', 'position', 'step_type',
                'duration_target_minutes', 'wait_target_minutes',
                'corefit_classification', 'automation_level', 'complexity', 'is_active',
            ]))->values()->toArray(),
            'flows'    => $process->flows->map(fn ($f) => $f->only([
                'id', 'from_step_id', 'to_step_id', 'condition_label', 'is_default',
            ]))->values()->toArray(),
            'triggers' => $process->triggers->map(fn ($t) => $t->only([
                'id', 'label', 'description', 'trigger_type',
                'entity_type_id', 'entity_id', 'source_process_id', 'interlink_id', 'schedule_expression',
            ]))->values()->toArray(),
            'outputs'  => $process->outputs->map(fn ($o) => $o->only([
                'id', 'label', 'description', 'output_type',
                'entity_id', 'target_process_id', 'interlink_id',
            ]))->values()->toArray(),
        ];

        $steps = $process->steps;
        $corefitCounts = $steps->groupBy('corefit_classification')->map->count();
        $automationCounts = $steps->groupBy('automation_level')->map->count();
        // Complexity metrics for snapshot
        $withComplexity = $steps->filter(fn ($s) => $s->complexity !== null);
        $complexityCount = $withComplexity->count();
        $totalComplexityPoints = $withComplexity->sum(fn ($s) => $s->complexity->points());
        $avgComplexityPoints = $complexityCount > 0 ? round($totalComplexityPoints / $complexityCount, 1) : null;

        // Automation score for snapshot
        $snapshotAutomationScore = null;
        if ($steps->count() > 0) {
            $weightedSum = 0;
            $weightSum = 0;
            foreach ($steps as $s) {
                $al = $s->automation_level ?? 'human';
                $pts = $s->complexity ? $s->complexity->points() : 1;
                $sc = match ($al) {
                    'llm_autonomous' => 100,
                    'llm_assisted' => 85,
                    'hybrid' => 70,
                    default => $s->complexity ? (int) round(15 + ($s->complexity->points() / 13) * 80) : 30,
                };
                $weightedSum += $sc * $pts;
                $weightSum += $pts;
            }
            $snapshotAutomationScore = $weightSum > 0 ? (int) round($weightedSum / $weightSum) : null;
        }

        $metrics = [
            'total_steps'    => $steps->count(),
            'total_flows'    => $process->flows->count(),
            'total_triggers' => $process->triggers->count(),
            'total_outputs'  => $process->outputs->count(),
            'total_duration' => $steps->sum('duration_target_minutes') ?? 0,
            'total_wait'     => $steps->sum('wait_target_minutes') ?? 0,
            'avg_complexity_points' => $avgComplexityPoints,
            'automation_score'      => $snapshotAutomationScore,
            'corefit' => [
                'core'    => $corefitCounts->get('core', 0),
                'context' => $corefitCounts->get('context', 0),
                'no_fit'  => $corefitCounts->get('no_fit', 0),
            ],
            'automation' => [
                'human'          => $automationCounts->get('human', 0),
                'llm_assisted'   => $automationCounts->get('llm_assisted', 0),
                'llm_autonomous' => $automationCounts->get('llm_autonomous', 0),
                'hybrid'         => $automationCounts->get('hybrid', 0),
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
            'title' => '', 'category' => 'speed',
            'priority' => 'medium', 'status' => 'identified',
            'target_step_id' => '', 'projected_duration_target_minutes' => '',
            'projected_automation_level' => '', 'projected_complexity' => '',
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
            'title'                             => $imp->title,
            'category'                          => $imp->category,
            'priority'                          => $imp->priority,
            'status'                            => $imp->status,
            'target_step_id'                    => (string) ($imp->target_step_id ?? ''),
            'projected_duration_target_minutes' => (string) ($imp->projected_duration_target_minutes ?? ''),
            'projected_automation_level'        => (string) ($imp->projected_automation_level ?? ''),
            'projected_complexity'              => (string) ($imp->projected_complexity ?? ''),
        ];
        $this->improvementModalShow = true;
    }

    public function storeImprovement(): void
    {
        $this->validate([
            'improvementForm.title'                             => 'required|string|max:255',
            'improvementForm.category'                          => 'required|in:cost,quality,speed,risk,standardization',
            'improvementForm.priority'                          => 'required|in:low,medium,high,critical',
            'improvementForm.status'                            => 'required|in:identified,planned,in_progress,on_hold,completed,under_observation,validated,failed,rejected',
            'improvementForm.target_step_id'                    => 'nullable|integer|exists:organization_process_steps,id',
            'improvementForm.projected_duration_target_minutes' => 'nullable|integer|min:0',
            'improvementForm.projected_automation_level'        => 'nullable|in:human,llm_assisted,llm_autonomous,hybrid',
            'improvementForm.projected_complexity'              => 'nullable|in:' . implode(',', StepComplexity::values()),
        ]);

        $payload = [
            'title'                             => $this->improvementForm['title'],
            'category'                          => $this->improvementForm['category'],
            'priority'                          => $this->improvementForm['priority'],
            'status'                            => $this->improvementForm['status'],
            'target_step_id'                    => $this->improvementForm['target_step_id'] !== '' ? (int) $this->improvementForm['target_step_id'] : null,
            'projected_duration_target_minutes' => $this->improvementForm['projected_duration_target_minutes'] !== '' ? (int) $this->improvementForm['projected_duration_target_minutes'] : null,
            'projected_automation_level'        => $this->improvementForm['projected_automation_level'] !== '' ? $this->improvementForm['projected_automation_level'] : null,
            'projected_complexity'              => $this->improvementForm['projected_complexity'] !== '' ? $this->improvementForm['projected_complexity'] : null,
        ];

        // States that imply the improvement has been implemented (completion timestamp set)
        $completedStates = ['completed', 'under_observation', 'validated', 'failed'];

        if ($this->editingImprovementId) {
            $imp = $this->process->improvements()->find($this->editingImprovementId);
            if ($imp) {
                if (in_array($this->improvementForm['status'], $completedStates, true)) {
                    // Preserve existing completed_at, set now() if not yet set
                    $payload['completed_at'] = $imp->completed_at ?? now();
                } else {
                    $payload['completed_at'] = null;
                }
                $imp->update($payload);
            }
            $this->dispatch('toast', message: 'Verbesserung aktualisiert');
        } else {
            if (in_array($this->improvementForm['status'], $completedStates, true)) {
                $payload['completed_at'] = now();
            }
            $this->process->improvements()->create(array_merge($payload, [
                'team_id' => Auth::user()->currentTeam->id,
                'user_id' => Auth::id(),
            ]));
            $this->dispatch('toast', message: 'Verbesserung erstellt');
        }

        $this->improvementModalShow = false;
        unset($this->processImprovements, $this->improvementsByCategory, $this->improvementSimulations);
    }

    public function deleteImprovement(int $id): void
    {
        $this->process->improvements()->where('id', $id)->delete();
        unset($this->processImprovements, $this->improvementsByCategory, $this->improvementSimulations);
        $this->dispatch('toast', message: 'Verbesserung gelöscht');
    }

    public function render()
    {
        return view('organization::livewire.process.show')
            ->layout('platform::layouts.app');
    }
}
