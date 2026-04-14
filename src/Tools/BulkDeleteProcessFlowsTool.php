<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcessFlow;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class BulkDeleteProcessFlowsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_flows.bulk_DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/process-flows/bulk - Löscht mehrere Prozess-Flows auf einmal. Akzeptiert entweder eine Liste von IDs oder löscht alle Flows eines Prozesses. Ideal beim Umbauen eines Prozesses.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'process_flow_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Optional: Array von Flow-IDs zum Löschen. Wenn nicht angegeben, wird process_id benötigt.',
                ],
                'process_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Löscht ALLE Flows dieses Prozesses. Nur verwenden wenn process_flow_ids nicht angegeben.',
                ],
            ],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $flowIds = $arguments['process_flow_ids'] ?? [];
            $processId = (int) ($arguments['process_id'] ?? 0);

            if (empty($flowIds) && $processId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder process_flow_ids oder process_id ist erforderlich.');
            }

            $query = OrganizationProcessFlow::where('team_id', $rootTeamId);

            if (! empty($flowIds)) {
                $query->whereIn('id', array_map('intval', $flowIds));
            } else {
                $query->where('process_id', $processId);
            }

            $count = $query->count();
            if ($count === 0) {
                return ToolResult::success([
                    'deleted_count' => 0,
                    'message' => 'Keine Flows gefunden.',
                ]);
            }

            $query->delete();

            return ToolResult::success([
                'deleted_count' => $count,
                'message' => $count . ' Prozess-Flows gelöscht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Löschen der Prozess-Flows: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_flows', 'bulk', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
