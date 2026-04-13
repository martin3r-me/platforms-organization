<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.processes.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/processes - Listet Prozess-Definitionen. Filter: status, is_active, owner_entity_id, vsm_system_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'status', 'is_active', 'owner_entity_id', 'vsm_system_id']),
            [
                'properties' => [
                    'team_id'         => ['type' => 'integer'],
                    'status'          => ['type' => 'string', 'description' => 'Optional: draft | active | deprecated.'],
                    'is_active'       => ['type' => 'boolean', 'description' => 'Optional: Default true.'],
                    'owner_entity_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach Owner-Entity.'],
                    'vsm_system_id'   => ['type' => 'integer', 'description' => 'Optional: Filter nach VSM-System.'],
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

            $q = OrganizationProcess::query()->where('team_id', $rootTeamId);

            if (array_key_exists('status', $arguments) && $arguments['status'] !== null && $arguments['status'] !== '') {
                $q->where('status', (string) $arguments['status']);
            }
            if (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }
            if (! empty($arguments['owner_entity_id'])) {
                $q->where('owner_entity_id', (int) $arguments['owner_entity_id']);
            }
            if (! empty($arguments['vsm_system_id'])) {
                $q->where('vsm_system_id', (int) $arguments['vsm_system_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'status', 'is_active', 'owner_entity_id', 'vsm_system_id', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'code', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'code', 'status', 'version', 'id', 'created_at'], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationProcess $p) => [
                'id'              => $p->id,
                'uuid'            => $p->uuid,
                'name'            => $p->name,
                'code'            => $p->code,
                'description'     => $p->description,
                'owner_entity_id' => $p->owner_entity_id,
                'vsm_system_id'   => $p->vsm_system_id,
                'status'          => $p->status,
                'version'         => $p->version,
                'is_active'       => $p->is_active,
                'team_id'         => $p->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Prozesse: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'processes', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
