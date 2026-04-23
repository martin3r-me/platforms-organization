<?php

namespace Platform\Organization\Services;

use Platform\Organization\Enums\AutomationLevel;
use Platform\Organization\Enums\CorefitClassification;
use Platform\Organization\Enums\RunStatus;
use Platform\Organization\Models\OrganizationProcess;

class ProcessCertificateService
{
    public static function compute(OrganizationProcess $process): array
    {
        $process->loadMissing(['ownerEntity', 'vsmSystem', 'steps', 'improvements', 'team', 'runs.runSteps']);

        $steps = $process->steps->sortBy('position');
        $totalSteps = $steps->count();

        $hourlyRate = (float) ($process->hourly_rate ?? 0);
        $minuteRate = $hourlyRate > 0 ? $hourlyRate / 60 : 0;

        // Basic metrics
        $totalDuration = $steps->sum('duration_target_minutes') ?? 0;
        $totalWait = $steps->sum('wait_target_minutes') ?? 0;
        $leadTime = $totalDuration + $totalWait;

        // COREFIT distribution
        $corefitGrouped = $steps->groupBy(fn ($s) => $s->corefit_classification?->value ?? 'core');
        $corefit = [];
        foreach (CorefitClassification::values() as $classification) {
            $group = $corefitGrouped->get($classification, collect());
            $count = $group->count();
            $minutes = $group->sum('duration_target_minutes') ?? 0;
            $percent = $totalSteps > 0 ? round(($count / $totalSteps) * 100, 1) : 0;
            $cost = round($minutes * $minuteRate, 2);

            $corefit[$classification] = [
                'count' => $count,
                'minutes' => $minutes,
                'percent' => $percent,
                'cost' => $cost,
            ];
        }

        // Automation distribution
        $autoGrouped = $steps->groupBy(fn ($s) => $s->automation_level?->value ?? 'human');
        $automation = [];
        $llmCount = 0;
        foreach (AutomationLevel::values() as $level) {
            $group = $autoGrouped->get($level, collect());
            $count = $group->count();
            $minutes = $group->sum('duration_target_minutes') ?? 0;
            $percent = $totalSteps > 0 ? round(($count / $totalSteps) * 100, 1) : 0;

            if (AutomationLevel::tryFrom($level)?->isLlm()) {
                $llmCount += $count;
            }

            $automation[$level] = [
                'count' => $count,
                'percent' => $percent,
                'minutes' => $minutes,
            ];
        }

        $llmQuote = $totalSteps > 0 ? round(($llmCount / $totalSteps) * 100, 1) : 0;

        // 5-dimension composite score
        $compositeScore = self::computeCompositeScore($process, $steps, $totalSteps, $totalDuration, $totalWait);
        $efficiencyClass = self::efficiencyClass($compositeScore['score']);

        // Handlungsbedarf (action items)
        $recommendations = [
            'core' => ['human' => 'Investieren', 'llm_assisted' => 'Gut', 'llm_autonomous' => 'Optimal', 'hybrid' => 'Gut'],
            'context' => ['human' => 'Automatisieren', 'llm_assisted' => 'Akzeptabel', 'llm_autonomous' => 'Akzeptabel', 'hybrid' => 'Akzeptabel'],
            'no_fit' => ['human' => 'Eliminieren', 'llm_assisted' => 'Eliminieren', 'llm_autonomous' => 'Eliminieren', 'hybrid' => 'Eliminieren'],
        ];

        $actionItems = ['eliminate' => 0, 'automate' => 0, 'invest' => 0, 'optimal' => 0];
        foreach (CorefitClassification::values() as $cf) {
            foreach (AutomationLevel::values() as $al) {
                $cellCount = $steps->filter(fn ($s) =>
                    ($s->corefit_classification?->value ?? 'core') === $cf &&
                    ($s->automation_level?->value ?? 'human') === $al
                )->count();

                if ($cellCount === 0) continue;
                $rec = $recommendations[$cf][$al] ?? '';
                match ($rec) {
                    'Eliminieren' => $actionItems['eliminate'] += $cellCount,
                    'Automatisieren' => $actionItems['automate'] += $cellCount,
                    'Investieren' => $actionItems['invest'] += $cellCount,
                    'Optimal', 'Gut' => $actionItems['optimal'] += $cellCount,
                    default => null,
                };
            }
        }

        // Cost metrics
        $frequency = $process->frequency;
        $costMetrics = null;
        if ($frequency && $hourlyRate > 0 && $totalDuration > 0) {
            $costPerRun = round(($totalDuration / 60) * $hourlyRate, 2);
            $costPerMonth = round($costPerRun * $frequency->monthlyFactor(), 2);
            $costPerYear = round($costPerMonth * 12, 2);
            $costMetrics = [
                'cost_per_run' => $costPerRun,
                'cost_per_month' => $costPerMonth,
                'cost_per_year' => $costPerYear,
                'frequency_label' => $frequency->label(),
                'runs_per_month' => $frequency->monthlyFactor(),
            ];
        }

        $now = now();

        return [
            'process' => [
                'name' => $process->name,
                'code' => $process->code,
                'version' => $process->version ?? 1,
                'status' => $process->status?->value ?? 'draft',
                'description' => $process->description,
                'owner' => $process->ownerEntity?->name,
                'vsm_system' => $process->vsmSystem?->name,
                'team' => $process->team?->name,
                'target_description' => $process->target_description,
                'value_proposition' => $process->value_proposition,
                'cost_analysis' => $process->cost_analysis,
                'risk_assessment' => $process->risk_assessment,
                'improvement_levers' => $process->improvement_levers,
                'action_plan' => $process->action_plan,
                'standardization_notes' => $process->standardization_notes,
            ],
            'efficiency_class' => $efficiencyClass,
            'process_score' => $compositeScore['score'],
            'score_dimensions' => $compositeScore['dimensions'],
            'has_run_data' => $compositeScore['has_run_data'],
            'run_count' => $compositeScore['run_count'],
            // Keep old keys for backward compatibility
            'efficiency_percent' => $compositeScore['score'],
            'efficiency_components' => collect($compositeScore['dimensions'])->mapWithKeys(fn ($d, $k) => [$k => $d['score']])->toArray(),
            'kpis' => [
                'total_steps' => $totalSteps,
                'lead_time' => $leadTime,
                'total_duration' => $totalDuration,
                'total_wait' => $totalWait,
                'llm_quote' => $llmQuote,
                'llm_count' => $llmCount,
            ],
            'corefit' => $corefit,
            'automation' => $automation,
            'action_items' => $actionItems,
            'cost_metrics' => $costMetrics,
            'steps_list' => $steps->map(fn ($s) => [
                'position' => $s->position,
                'name' => $s->name,
                'corefit' => $s->corefit_classification?->value ?? 'core',
                'automation' => $s->automation_level?->value ?? 'human',
                'duration' => $s->duration_target_minutes,
                'wait' => $s->wait_target_minutes,
            ])->values()->toArray(),
            'improvements_list' => $process->improvements
                ->sortByDesc('created_at')
                ->map(fn ($i) => [
                    'title' => $i->title,
                    'category' => $i->category,
                    'priority' => $i->priority,
                    'status' => $i->status?->value,
                ])->values()->toArray(),
            'meta' => [
                'generated_at' => $now->toIso8601String(),
                'generated_at_formatted' => $now->format('d.m.Y H:i'),
                'checksum' => hash('sha256', $process->uuid . '|' . $now->toIso8601String()),
            ],
        ];
    }

    /**
     * 5-Dimensionen Prozess-Score:
     * 1. Design-Qualität (COREFIT) — 20%
     * 2. Automatisierungsgrad — 20%
     * 3. Zeitperformance — 25%
     * 4. Prozessreife — 15%
     * 5. Flow-Effizienz — 20%
     */
    public static function computeCompositeScore(
        OrganizationProcess $process,
        $steps,
        int $totalSteps,
        int $totalDuration = 0,
        int $totalWait = 0,
    ): array {
        if ($totalSteps === 0) {
            return [
                'score' => 0,
                'dimensions' => [],
                'has_run_data' => false,
                'run_count' => 0,
            ];
        }

        $completedRuns = $process->runs->where('status', RunStatus::COMPLETED);
        $runCount = $completedRuns->count();
        $hasRunData = $runCount > 0;

        $design = self::computeDesignScore($steps, $totalSteps);
        $automation = self::computeAutomationScore($steps);
        $time = self::computeTimePerformanceScore($process, $steps, $completedRuns);
        $maturity = self::computeMaturityScore($process, $steps, $totalSteps);
        $flow = self::computeFlowScore($steps, $totalDuration, $totalWait, $completedRuns);

        $score = round(
            $design * 0.20 +
            $automation * 0.20 +
            $time * 0.25 +
            $maturity * 0.15 +
            $flow * 0.20,
            1
        );

        return [
            'score' => $score,
            'dimensions' => [
                'design'     => ['score' => round($design, 1), 'weight' => 20, 'label' => 'Design-Qualität'],
                'automation' => ['score' => round($automation, 1), 'weight' => 20, 'label' => 'Automatisierung'],
                'time'       => ['score' => round($time, 1), 'weight' => 25, 'label' => 'Zeitperformance'],
                'maturity'   => ['score' => round($maturity, 1), 'weight' => 15, 'label' => 'Prozessreife'],
                'flow'       => ['score' => round($flow, 1), 'weight' => 20, 'label' => 'Flow-Effizienz'],
            ],
            'has_run_data' => $hasRunData,
            'run_count' => $runCount,
        ];
    }

    /**
     * Design-Qualität (COREFIT): Core=100, Context=50, No-Fit=-50
     * With quadratic penalty for No-Fit steps.
     */
    private static function computeDesignScore($steps, int $totalSteps): float
    {
        $pointsSum = $steps->sum(function ($s) {
            return match ($s->corefit_classification?->value ?? 'core') {
                'core' => 100,
                'context' => 50,
                'no_fit' => -50,
                default => 50,
            };
        });

        $raw = $pointsSum / $totalSteps;

        $noFitCount = $steps->filter(fn ($s) => ($s->corefit_classification?->value ?? 'core') === 'no_fit')->count();
        $noFitRatio = $noFitCount / $totalSteps;
        $penalty = ($noFitRatio ** 2) * 100;

        return max(0, min(100, $raw - $penalty));
    }

    /**
     * Automatisierungsgrad: complexity-weighted automation level scoring.
     */
    private static function computeAutomationScore($steps): float
    {
        $weightedSum = 0;
        $weightSum = 0;

        foreach ($steps as $s) {
            $al = $s->automation_level ?? AutomationLevel::HUMAN;
            $complexity = $s->complexity;
            $pts = $complexity ? $complexity->points() : 1;
            $sc = match ($al) {
                AutomationLevel::LLM_AUTONOMOUS => 100,
                AutomationLevel::LLM_ASSISTED => 85,
                AutomationLevel::HYBRID => 70,
                default => 30,
            };
            $weightedSum += $sc * $pts;
            $weightSum += $pts;
        }

        return $weightSum > 0 ? $weightedSum / $weightSum : 0;
    }

    /**
     * Zeitperformance: Compare actual run durations vs. target durations.
     * Without runs: returns 50 (neutral). With runs: deviation-based scoring.
     */
    private static function computeTimePerformanceScore(OrganizationProcess $process, $steps, $completedRuns): float
    {
        if ($completedRuns->isEmpty()) {
            return 50;
        }

        // Build map: process_step_id => target duration
        $stepTargets = $steps->keyBy('id')->map(fn ($s) => [
            'target' => (int) ($s->duration_target_minutes ?? 0),
            'complexity_pts' => $s->complexity ? $s->complexity->points() : 1,
        ]);

        // Collect all run step actuals grouped by process_step_id
        $runStepActuals = collect();
        foreach ($completedRuns as $run) {
            foreach ($run->runSteps as $rs) {
                if ($rs->active_duration_minutes !== null && $rs->process_step_id) {
                    $runStepActuals->push([
                        'step_id' => $rs->process_step_id,
                        'actual' => (int) $rs->active_duration_minutes,
                    ]);
                }
            }
        }

        if ($runStepActuals->isEmpty()) {
            return 50;
        }

        // Average actual per step
        $avgByStep = $runStepActuals->groupBy('step_id')->map(fn ($group) => $group->avg('actual'));

        $weightedScoreSum = 0;
        $weightSum = 0;

        foreach ($avgByStep as $stepId => $avgActual) {
            $meta = $stepTargets->get($stepId);
            if (! $meta || $meta['target'] <= 0) {
                continue;
            }

            $target = $meta['target'];
            $pts = $meta['complexity_pts'];
            $deviation = abs($avgActual - $target) / $target;

            if ($avgActual <= $target) {
                // Faster than target: bonus up to 100
                $savedPercent = ($target - $avgActual) / $target;
                $stepScore = min(100, 100 + ($savedPercent * 20));
            } else {
                // Slower than target: penalize
                $stepScore = max(0, 100 - ($deviation * 100));
            }

            $weightedScoreSum += $stepScore * $pts;
            $weightSum += $pts;
        }

        return $weightSum > 0 ? $weightedScoreSum / $weightSum : 50;
    }

    /**
     * Prozessreife: Documentation completeness, run history, improvements.
     */
    private static function computeMaturityScore(OrganizationProcess $process, $steps, int $totalSteps): float
    {
        $score = 0;

        // Has runs?
        $runCount = $process->runs->where('status', RunStatus::COMPLETED)->count();
        if ($runCount >= 5) {
            $score += 50;
        } elseif ($runCount >= 3) {
            $score += 40;
        } elseif ($runCount >= 1) {
            $score += 25;
        }

        // Has description or target_description?
        if (! empty($process->description) || ! empty($process->target_description)) {
            $score += 10;
        }

        // COREFIT analysis fields filled
        $analysisFields = ['value_proposition', 'cost_analysis', 'risk_assessment', 'improvement_levers', 'action_plan', 'standardization_notes'];
        $filledFields = 0;
        foreach ($analysisFields as $field) {
            if (! empty($process->$field)) {
                $filledFields++;
            }
        }
        $score += min(20, $filledFields * 5);

        // Has improvements?
        $improvementCount = $process->improvements->count();
        if ($improvementCount >= 3) {
            $score += 20;
        } elseif ($improvementCount >= 1) {
            $score += 10;
        }

        // Steps have complexity set?
        if ($totalSteps > 0) {
            $complexitySetCount = $steps->filter(fn ($s) => $s->complexity !== null)->count();
            if (($complexitySetCount / $totalSteps) > 0.8) {
                $score += 10;
            }
        }

        return min(100, $score);
    }

    /**
     * Flow-Effizienz: Active time vs. wait time ratio.
     * Without any wait data: returns 70 (neutral-positive).
     * With run data: uses actual averages. Otherwise uses targets.
     */
    private static function computeFlowScore($steps, int $totalDuration, int $totalWait, $completedRuns): float
    {
        // Try to use actual run data first
        if ($completedRuns->isNotEmpty()) {
            $totalActualActive = 0;
            $totalActualWait = 0;
            $hasData = false;

            foreach ($completedRuns as $run) {
                foreach ($run->runSteps as $rs) {
                    if ($rs->active_duration_minutes !== null) {
                        $totalActualActive += (int) $rs->active_duration_minutes;
                        $totalActualWait += (int) ($rs->wait_duration_minutes ?? 0);
                        $hasData = true;
                    }
                }
            }

            if ($hasData && ($totalActualActive + $totalActualWait) > 0) {
                return round(($totalActualActive / ($totalActualActive + $totalActualWait)) * 100, 1);
            }
        }

        // Fall back to target values
        if ($totalWait > 0 && $totalDuration > 0) {
            return round(($totalDuration / ($totalDuration + $totalWait)) * 100, 1);
        }

        // No wait data at all
        return 70;
    }

    public static function efficiencyClass(float $efficiency): array
    {
        $classes = [
            ['min' => 90, 'class' => 'A+', 'color' => '#16a34a', 'label' => 'Exzellent'],
            ['min' => 80, 'class' => 'A',  'color' => '#22c55e', 'label' => 'Sehr gut'],
            ['min' => 70, 'class' => 'B',  'color' => '#84cc16', 'label' => 'Gut'],
            ['min' => 60, 'class' => 'C',  'color' => '#eab308', 'label' => 'Durchschnittlich'],
            ['min' => 50, 'class' => 'D',  'color' => '#f97316', 'label' => 'Unterdurchschnittlich'],
            ['min' => 40, 'class' => 'E',  'color' => '#ef4444', 'label' => 'Schlecht'],
            ['min' => 25, 'class' => 'F',  'color' => '#dc2626', 'label' => 'Sehr schlecht'],
            ['min' => 0,  'class' => 'G',  'color' => '#991b1b', 'label' => 'Kritisch'],
        ];

        foreach ($classes as $c) {
            if ($efficiency >= $c['min']) {
                return $c;
            }
        }

        return end($classes);
    }
}
