<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationProcessStepEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessStepEntitiesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_step_entities.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process-step-entities - Listet Entity-Zuordnungen zu Prozess-Schritten (wer macht was). Filter: process_step_id, entity_type_id, entity_id, role.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'process_step_id', 'entity_type_id', 'entity_id', 'role']),
            [
                'properties' => [
                    'team_id'         => ['type' => 'integer'],
                    'process_step_id' => ['type' => 'integer', 'description' => 'EMPFOHLEN: Filter nach Prozess-Schritt.'],
                    'entity_type_id'  => ['type' => 'integer'],
                    'entity_id'       => ['type' => 'integer'],
                    'role'            => ['type' => 'string', 'description' => 'Optional: executes | decides | informs | receives | provides_input.'],
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

            $q = OrganizationProcessStepEntity::query()->where('team_id', $rootTeamId);

            if (! empty($arguments['process_step_id'])) {
                $q->where('process_step_id', (int) $arguments['process_step_id']);
            }
            if (! empty($arguments['entity_type_id'])) {
                $q->where('entity_type_id', (int) $arguments['entity_type_id']);
            }
            if (! empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }
            if (! empty($arguments['role'])) {
                $q->where('role', (string) $arguments['role']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'process_step_id', 'entity_type_id', 'entity_id', 'role', 'created_at']);
            $this->applyStandardSort($q, $arguments, ['id', 'role', 'created_at'], 'id', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationProcessStepEntity $se) => [
                'id'              => $se->id,
                'uuid'            => $se->uuid,
                'process_step_id' => $se->process_step_id,
                'entity_type_id'  => $se->entity_type_id,
                'entity_id'       => $se->entity_id,
                'role'            => $se->role,
                'team_id'         => $se->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Step-Entity-Zuordnungen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'process_step_entities', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
