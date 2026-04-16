<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationProcessFlow;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessFlowsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_flows.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process-flows - Listet Prozess-Flows (Verbindungen zwischen Steps). Filter: process_id, from_step_id, to_step_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'process_id', 'from_step_id', 'to_step_id', 'flow_kind', 'priority']),
            [
                'properties' => [
                    'team_id'      => ['type' => 'integer'],
                    'process_id'   => ['type' => 'integer', 'description' => 'EMPFOHLEN: Filter nach Prozess.'],
                    'from_step_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach Quell-Schritt.'],
                    'to_step_id'   => ['type' => 'integer', 'description' => 'Optional: Filter nach Ziel-Schritt.'],
                    'flow_kind'    => ['type' => 'string', 'description' => 'Optional: sequence | conditional | exception | loop_back | compensation.'],
                    'priority'     => ['type' => 'integer', 'description' => 'Optional: exakte Priorität.'],
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

            $q = OrganizationProcessFlow::query()->where('team_id', $rootTeamId);

            if (! empty($arguments['process_id'])) {
                $q->where('process_id', (int) $arguments['process_id']);
            }
            if (! empty($arguments['from_step_id'])) {
                $q->where('from_step_id', (int) $arguments['from_step_id']);
            }
            if (! empty($arguments['to_step_id'])) {
                $q->where('to_step_id', (int) $arguments['to_step_id']);
            }
            if (! empty($arguments['flow_kind'])) {
                $q->where('flow_kind', (string) $arguments['flow_kind']);
            }
            if (array_key_exists('priority', $arguments) && $arguments['priority'] !== null && $arguments['priority'] !== '') {
                $q->where('priority', (int) $arguments['priority']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'process_id', 'from_step_id', 'to_step_id', 'flow_kind', 'priority', 'created_at']);
            $this->applyStandardSort($q, $arguments, ['id', 'priority', 'created_at'], 'priority', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationProcessFlow $f) => [
                'id'                   => $f->id,
                'uuid'                 => $f->uuid,
                'process_id'           => $f->process_id,
                'from_step_id'         => $f->from_step_id,
                'to_step_id'           => $f->to_step_id,
                'condition_label'      => $f->condition_label,
                'condition_expression' => $f->condition_expression,
                'flow_kind'            => $f->flow_kind instanceof \BackedEnum ? $f->flow_kind->value : $f->flow_kind,
                'priority'             => $f->priority,
                'is_default'           => $f->is_default,
                'team_id'              => $f->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Prozess-Flows: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'process_flows', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
