<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessRun;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessRunTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_runs.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process_runs - Startet einen neuen Durchlauf für einen Prozess. Kopiert alle aktiven Steps als Checkliste.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'    => ['type' => 'integer'],
                'process_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'notes'      => ['type' => 'string', 'description' => 'Optional: Kontext/Notizen zum Durchlauf.'],
            ],
            'required' => ['process_id'],
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

            $process = OrganizationProcess::find($arguments['process_id'] ?? 0);
            if (! $process) {
                return ToolResult::error('NOT_FOUND', 'Prozess nicht gefunden.');
            }
            if ((int) $process->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess gehört nicht zum Team.');
            }

            $activeSteps = $process->steps()
                ->where('is_active', true)
                ->orderBy('position')
                ->get();

            if ($activeSteps->isEmpty()) {
                return ToolResult::error('VALIDATION_ERROR', 'Prozess hat keine aktiven Steps.');
            }

            $run = OrganizationProcessRun::create([
                'team_id'    => $rootTeamId,
                'user_id'    => $context->user?->id,
                'process_id' => $process->id,
                'status'     => 'active',
                'notes'      => ($arguments['notes'] ?? null) ?: null,
                'started_at' => now(),
            ]);

            $runSteps = [];
            foreach ($activeSteps as $step) {
                $runSteps[] = $run->runSteps()->create([
                    'process_step_id' => $step->id,
                    'position'        => $step->position,
                    'status'          => 'pending',
                ]);
            }

            return ToolResult::success([
                'id'          => $run->id,
                'uuid'        => $run->uuid,
                'status'      => 'active',
                'process_id'  => $process->id,
                'started_at'  => $run->started_at->toIso8601String(),
                'total_steps' => count($runSteps),
                'steps'       => collect($runSteps)->map(fn ($rs) => [
                    'id'              => $rs->id,
                    'process_step_id' => $rs->process_step_id,
                    'position'        => $rs->position,
                    'status'          => 'pending',
                ])->toArray(),
                'message'     => 'Durchlauf gestartet.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Durchlaufs: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'processes', 'runs', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
