<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessSnapshot;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class GetProcessSnapshotTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_snapshot.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process_snapshot/{id} - Gibt einen einzelnen Snapshot mit vollständigen snapshot_data und metrics zurück.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'     => ['type' => 'integer'],
                'snapshot_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Snapshot-ID.'],
            ],
            'required' => ['snapshot_id'],
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

            $snapshot = OrganizationProcessSnapshot::with('process')->find($arguments['snapshot_id'] ?? 0);
            if (! $snapshot) {
                return ToolResult::error('NOT_FOUND', 'Snapshot nicht gefunden.');
            }
            if ((int) $snapshot->process->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Snapshot gehört nicht zum Team.');
            }

            return ToolResult::success([
                'id'                 => $snapshot->id,
                'uuid'               => $snapshot->uuid,
                'process_id'         => $snapshot->process_id,
                'version'            => $snapshot->version,
                'label'              => $snapshot->label,
                'snapshot_data'      => $snapshot->snapshot_data,
                'metrics'            => $snapshot->metrics,
                'created_by_user_id' => $snapshot->created_by_user_id,
                'created_at'         => $snapshot->created_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Snapshots: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'processes', 'snapshots', 'detail'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
