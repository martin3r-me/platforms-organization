<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationProcessStepInterlink;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessStepInterlinksTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_step_interlinks.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process-step-interlinks - Listet Interlink-Zuordnungen zu Prozess-Schritten. Filter: process_step_id, interlink_id, role.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'process_step_id', 'interlink_id', 'role']),
            [
                'properties' => [
                    'team_id'         => ['type' => 'integer'],
                    'process_step_id' => ['type' => 'integer', 'description' => 'EMPFOHLEN: Filter nach Prozess-Schritt.'],
                    'interlink_id'    => ['type' => 'integer'],
                    'role'            => ['type' => 'string', 'description' => 'Optional: triggers | consumes | produces.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = OrganizationProcessStepInterlink::query()->where('team_id', $rootTeamId);

            if (! empty($arguments['process_step_id'])) {
                $q->where('process_step_id', (int) $arguments['process_step_id']);
            }
            if (! empty($arguments['interlink_id'])) {
                $q->where('interlink_id', (int) $arguments['interlink_id']);
            }
            if (! empty($arguments['role'])) {
                $q->where('role', (string) $arguments['role']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'process_step_id', 'interlink_id', 'role', 'created_at']);
            $this->applyStandardSort($q, $arguments, ['id', 'role', 'created_at'], 'id', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationProcessStepInterlink $si) => [
                'id'              => $si->id,
                'uuid'            => $si->uuid,
                'process_step_id' => $si->process_step_id,
                'interlink_id'    => $si->interlink_id,
                'role'            => $si->role,
                'team_id'         => $si->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Step-Interlink-Zuordnungen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'process_step_interlinks', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
