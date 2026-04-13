<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationProcessOutput;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessOutputsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_outputs.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process-outputs - Listet Prozess-Outputs. Filter: process_id, output_type, entity_type_id, target_process_id, interlink_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'process_id', 'output_type', 'entity_type_id', 'target_process_id', 'interlink_id']),
            [
                'properties' => [
                    'team_id'           => ['type' => 'integer'],
                    'process_id'        => ['type' => 'integer', 'description' => 'EMPFOHLEN: Filter nach Prozess.'],
                    'output_type'       => ['type' => 'string', 'description' => 'Optional: entity_created | entity_modified | triggers_process | interlink | deliverable.'],
                    'entity_type_id'    => ['type' => 'integer'],
                    'target_process_id' => ['type' => 'integer'],
                    'interlink_id'      => ['type' => 'integer'],
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

            $q = OrganizationProcessOutput::query()->where('team_id', $rootTeamId);

            if (! empty($arguments['process_id'])) {
                $q->where('process_id', (int) $arguments['process_id']);
            }
            if (! empty($arguments['output_type'])) {
                $q->where('output_type', (string) $arguments['output_type']);
            }
            if (! empty($arguments['entity_type_id'])) {
                $q->where('entity_type_id', (int) $arguments['entity_type_id']);
            }
            if (! empty($arguments['target_process_id'])) {
                $q->where('target_process_id', (int) $arguments['target_process_id']);
            }
            if (! empty($arguments['interlink_id'])) {
                $q->where('interlink_id', (int) $arguments['interlink_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'process_id', 'output_type', 'entity_type_id', 'target_process_id', 'interlink_id', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['label', 'description']);
            $this->applyStandardSort($q, $arguments, ['id', 'label', 'output_type', 'created_at'], 'id', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationProcessOutput $o) => [
                'id'                => $o->id,
                'uuid'              => $o->uuid,
                'process_id'        => $o->process_id,
                'label'             => $o->label,
                'description'       => $o->description,
                'output_type'       => $o->output_type,
                'entity_type_id'    => $o->entity_type_id,
                'entity_id'         => $o->entity_id,
                'target_process_id' => $o->target_process_id,
                'interlink_id'      => $o->interlink_id,
                'team_id'           => $o->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Prozess-Outputs: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'process_outputs', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
