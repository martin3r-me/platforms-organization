<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationProcessTrigger;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessTriggersTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_triggers.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process-triggers - Listet Prozess-Trigger. Filter: process_id, trigger_type, entity_type_id, source_process_id, interlink_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'process_id', 'trigger_type', 'entity_type_id', 'source_process_id', 'interlink_id']),
            [
                'properties' => [
                    'team_id'           => ['type' => 'integer'],
                    'process_id'        => ['type' => 'integer', 'description' => 'EMPFOHLEN: Filter nach Prozess.'],
                    'trigger_type'      => ['type' => 'string', 'description' => 'Optional: entity | process | interlink | schedule | manual.'],
                    'entity_type_id'    => ['type' => 'integer'],
                    'source_process_id' => ['type' => 'integer'],
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

            $q = OrganizationProcessTrigger::query()->where('team_id', $rootTeamId);

            if (! empty($arguments['process_id'])) {
                $q->where('process_id', (int) $arguments['process_id']);
            }
            if (! empty($arguments['trigger_type'])) {
                $q->where('trigger_type', (string) $arguments['trigger_type']);
            }
            if (! empty($arguments['entity_type_id'])) {
                $q->where('entity_type_id', (int) $arguments['entity_type_id']);
            }
            if (! empty($arguments['source_process_id'])) {
                $q->where('source_process_id', (int) $arguments['source_process_id']);
            }
            if (! empty($arguments['interlink_id'])) {
                $q->where('interlink_id', (int) $arguments['interlink_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'process_id', 'trigger_type', 'entity_type_id', 'source_process_id', 'interlink_id', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['label', 'description']);
            $this->applyStandardSort($q, $arguments, ['id', 'label', 'trigger_type', 'created_at'], 'id', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationProcessTrigger $t) => [
                'id'                  => $t->id,
                'uuid'                => $t->uuid,
                'process_id'          => $t->process_id,
                'label'               => $t->label,
                'description'         => $t->description,
                'trigger_type'        => $t->trigger_type,
                'entity_type_id'      => $t->entity_type_id,
                'entity_id'           => $t->entity_id,
                'source_process_id'   => $t->source_process_id,
                'interlink_id'        => $t->interlink_id,
                'schedule_expression' => $t->schedule_expression,
                'team_id'             => $t->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Prozess-Trigger: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'process_triggers', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
