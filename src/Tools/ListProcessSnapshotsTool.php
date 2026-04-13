<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessSnapshot;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessSnapshotsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_snapshots.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process_snapshots - Listet Snapshots eines Prozesses, sortiert nach Version (neueste zuerst).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'    => ['type' => 'integer'],
                'process_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Prozess-ID.'],
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

            $snapshots = OrganizationProcessSnapshot::where('process_id', $process->id)
                ->orderByDesc('version')
                ->get()
                ->map(fn (OrganizationProcessSnapshot $s) => [
                    'id'                 => $s->id,
                    'uuid'               => $s->uuid,
                    'version'            => $s->version,
                    'label'              => $s->label,
                    'metrics'            => $s->metrics,
                    'created_by_user_id' => $s->created_by_user_id,
                    'created_at'         => $s->created_at?->toIso8601String(),
                ])
                ->values()
                ->toArray();

            return ToolResult::success([
                'data'       => $snapshots,
                'process_id' => $process->id,
                'total'      => count($snapshots),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Snapshots: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'processes', 'snapshots', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
