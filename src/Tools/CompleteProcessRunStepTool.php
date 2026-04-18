<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Enums\RunStatus;
use Platform\Organization\Enums\RunStepStatus;
use Platform\Organization\Models\OrganizationProcessRunStep;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CompleteProcessRunStepTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_run_steps.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /organization/process_run_steps - Schließt einen Step ab (completed/skipped). Wartezeit wird automatisch berechnet oder kann überschrieben werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'                 => ['type' => 'integer'],
                'run_step_id'             => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'status'                  => ['type' => 'string', 'description' => 'ERFORDERLICH: completed | skipped.'],
                'active_duration_minutes' => ['type' => 'integer', 'description' => 'Optional: Aktive Bearbeitungszeit in Minuten.'],
                'wait_duration_minutes'   => ['type' => 'integer', 'description' => 'Optional: Wartezeit manuell überschreiben.'],
                'notes'                   => ['type' => 'string', 'description' => 'Optional: Notizen zum Step.'],
            ],
            'required' => ['run_step_id', 'status'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $runStep = OrganizationProcessRunStep::with('run')->find($arguments['run_step_id'] ?? 0);
            if (! $runStep) {
                return ToolResult::error('NOT_FOUND', 'Run-Step nicht gefunden.');
            }

            $run = $runStep->run;
            if ((int) $run->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Durchlauf gehört nicht zum Team.');
            }
            if ($run->status !== RunStatus::ACTIVE) {
                return ToolResult::error('VALIDATION_ERROR', 'Durchlauf ist nicht aktiv.');
            }
            if ($runStep->status !== RunStepStatus::PENDING) {
                return ToolResult::error('VALIDATION_ERROR', 'Step ist bereits abgeschlossen.');
            }

            $status = $arguments['status'] ?? '';
            if (! in_array($status, ['completed', 'skipped'])) {
                return ToolResult::error('VALIDATION_ERROR', 'status muss completed oder skipped sein.');
            }

            $updates = [
                'status'     => $status,
                'checked_at' => now(),
            ];

            if (isset($arguments['active_duration_minutes'])) {
                $updates['active_duration_minutes'] = (int) $arguments['active_duration_minutes'];
            }

            if (isset($arguments['notes'])) {
                $updates['notes'] = ($arguments['notes'] ?? null) ?: null;
            }

            // Wait duration
            if (isset($arguments['wait_duration_minutes'])) {
                $updates['wait_duration_minutes'] = (int) $arguments['wait_duration_minutes'];
                $updates['wait_override'] = true;
            } else {
                // Auto-calculate from previous step
                $previousStep = $run->runSteps()
                    ->where('position', '<', $runStep->position)
                    ->whereNotNull('checked_at')
                    ->orderByDesc('position')
                    ->first();

                if ($previousStep && $previousStep->checked_at) {
                    $totalMinutesSince = (int) $previousStep->checked_at->diffInMinutes(now());
                    $activeDuration = $updates['active_duration_minutes'] ?? 0;
                    $waitMinutes = max(0, $totalMinutesSince - $activeDuration);
                    $updates['wait_duration_minutes'] = $waitMinutes;
                }
            }

            $runStep->update($updates);

            // Auto-complete run if all steps done
            $allSteps = $run->runSteps()->get();
            $allDone = $allSteps->every(fn ($s) => in_array($s->status->value ?? $s->status, ['completed', 'skipped']));
            $runCompleted = false;

            if ($allDone) {
                $run->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);
                $runCompleted = true;
            }

            return ToolResult::success([
                'run_step_id'   => $runStep->id,
                'status'        => $status,
                'checked_at'    => $runStep->checked_at?->toIso8601String(),
                'active_duration_minutes' => $runStep->active_duration_minutes,
                'wait_duration_minutes'   => $runStep->wait_duration_minutes,
                'wait_override' => $runStep->wait_override,
                'run_completed' => $runCompleted,
                'message'       => $runCompleted ? 'Step abgeschlossen. Durchlauf abgeschlossen.' : 'Step abgeschlossen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Abschließen des Steps: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'processes', 'runs', 'steps', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
