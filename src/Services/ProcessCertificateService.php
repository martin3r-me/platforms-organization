<?php

namespace Platform\Organization\Services;

use Platform\Organization\Models\OrganizationProcess;

class ProcessCertificateService
{
    public static function compute(OrganizationProcess $process): array
    {
        $process->loadMissing(['ownerEntity', 'vsmSystem', 'steps', 'improvements', 'team']);

        $steps = $process->steps->sortBy('position');
        $totalSteps = $steps->count();

        $hourlyRate = (float) ($process->hourly_rate ?? 0);
        $minuteRate = $hourlyRate > 0 ? $hourlyRate / 60 : 0;

        // Basic metrics
        $totalDuration = $steps->sum('duration_target_minutes') ?? 0;
        $totalWait = $steps->sum('wait_target_minutes') ?? 0;
        $leadTime = $totalDuration + $totalWait;
        $efficiency = $leadTime > 0 ? round(($totalDuration / $leadTime) * 100, 1) : 0;

        // COREFIT distribution
        $corefitGrouped = $steps->groupBy('corefit_classification');
        $corefit = [];
        foreach (['core', 'context', 'no_fit'] as $classification) {
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
        $autoGrouped = $steps->groupBy('automation_level');
        $automation = [];
        $llmCount = 0;
        foreach (['human', 'llm_assisted', 'llm_autonomous', 'hybrid'] as $level) {
            $group = $autoGrouped->get($level, collect());
            $count = $group->count();
            $minutes = $group->sum('duration_target_minutes') ?? 0;
            $percent = $totalSteps > 0 ? round(($count / $totalSteps) * 100, 1) : 0;

            if (in_array($level, ['llm_assisted', 'llm_autonomous', 'hybrid'])) {
                $llmCount += $count;
            }

            $automation[$level] = [
                'count' => $count,
                'percent' => $percent,
                'minutes' => $minutes,
            ];
        }

        $llmQuote = $totalSteps > 0 ? round(($llmCount / $totalSteps) * 100, 1) : 0;

        // Efficiency class
        $efficiencyClass = self::efficiencyClass($efficiency);

        // Handlungsbedarf (action items)
        $recommendations = [
            'core' => ['human' => 'Investieren', 'llm_assisted' => 'Gut', 'llm_autonomous' => 'Optimal', 'hybrid' => 'Gut'],
            'context' => ['human' => 'Automatisieren', 'llm_assisted' => 'Akzeptabel', 'llm_autonomous' => 'Akzeptabel', 'hybrid' => 'Akzeptabel'],
            'no_fit' => ['human' => 'Eliminieren', 'llm_assisted' => 'Eliminieren', 'llm_autonomous' => 'Eliminieren', 'hybrid' => 'Eliminieren'],
        ];

        $actionItems = ['eliminate' => 0, 'automate' => 0, 'invest' => 0, 'optimal' => 0];
        foreach (['core', 'context', 'no_fit'] as $cf) {
            foreach (['human', 'llm_assisted', 'llm_autonomous', 'hybrid'] as $al) {
                $cellCount = $steps->filter(fn ($s) =>
                    ($s->corefit_classification ?? 'core') === $cf &&
                    ($s->automation_level ?? 'human') === $al
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

        $now = now();

        return [
            'process' => [
                'name' => $process->name,
                'code' => $process->code,
                'version' => $process->version ?? 1,
                'status' => $process->status ?? 'draft',
                'description' => $process->description,
                'owner' => $process->ownerEntity?->name,
                'vsm_system' => $process->vsmSystem?->name,
                'team' => $process->team?->name,
            ],
            'efficiency_class' => $efficiencyClass,
            'efficiency_percent' => $efficiency,
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
            'steps_list' => $steps->map(fn ($s) => [
                'position' => $s->position,
                'name' => $s->name,
                'corefit' => $s->corefit_classification ?? 'core',
                'automation' => $s->automation_level ?? 'human',
                'duration' => $s->duration_target_minutes,
                'wait' => $s->wait_target_minutes,
            ])->values()->toArray(),
            'improvements_list' => $process->improvements
                ->sortByDesc('created_at')
                ->map(fn ($i) => [
                    'title' => $i->title,
                    'category' => $i->category,
                    'priority' => $i->priority,
                    'status' => $i->status,
                ])->values()->toArray(),
            'meta' => [
                'generated_at' => $now->toIso8601String(),
                'generated_at_formatted' => $now->format('d.m.Y H:i'),
                'checksum' => hash('sha256', $process->uuid . '|' . $now->toIso8601String()),
            ],
        ];
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
