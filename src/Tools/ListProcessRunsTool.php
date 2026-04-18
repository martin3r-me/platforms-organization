<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessRun;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessRunsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_runs.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process_runs - Listet Durchläufe eines Prozesses. Filter: status (active|completed|cancelled).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'    => ['type' => 'integer'],
                'process_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'status'     => ['type' => 'string', 'description' => 'Optional: active | completed | cancelled.'],
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

            $q = OrganizationProcessRun::where('process_id', $process->id)
                ->where('team_id', $rootTeamId);

            if (! empty($arguments['status'])) {
                $q->where('status', (string) $arguments['status']);
            }

            $items = $q->with(['runSteps.processStep:id,name,position', 'user:id,name'])
                ->orderByDesc('started_at')
                ->get()
                ->map(fn (OrganizationProcessRun $r) => [
                    'id'             => $r->id,
                    'uuid'           => $r->uuid,
                    'status'         => $r->status?->value,
                    'notes'          => $r->notes,
                    'started_at'     => $r->started_at?->toIso8601String(),
                    'completed_at'   => $r->completed_at?->toIso8601String(),
                    'cancelled_at'   => $r->cancelled_at?->toIso8601String(),
                    'user_id'        => $r->user_id,
                    'user_name'      => $r->user?->name,
                    'total_steps'    => $r->runSteps->count(),
                    'completed_steps' => $r->runSteps->whereIn('status', ['completed', 'skipped'])->count(),
                    'total_active_minutes' => $r->runSteps->sum('active_duration_minutes'),
                    'total_wait_minutes'   => $r->runSteps->sum('wait_duration_minutes'),
                    'created_at'     => $r->created_at?->toIso8601String(),
                ])
                ->values()
                ->toArray();

            return ToolResult::success([
                'data'       => $items,
                'process_id' => $process->id,
                'total'      => count($items),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Durchläufe: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'processes', 'runs', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
