<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationInferenceRun;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListInferenceRunsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.inference_runs.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/inference_runs - Listet Inference-Runs mit Statistiken (Prompts evaluiert, Signale erstellt, Dauer, Token-Usage).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'trigger_type', 'status']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID.',
                    ],
                    'trigger_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Trigger-Typ (scheduled, event, on_demand).',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status (running, completed, failed).',
                    ],
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

            $q = OrganizationInferenceRun::query()->where('team_id', $rootTeamId);

            if (! empty($arguments['trigger_type'])) {
                $q->where('trigger_type', $arguments['trigger_type']);
            }

            if (! empty($arguments['status'])) {
                $q->where('status', $arguments['status']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'trigger_type', 'status']);
            $this->applyStandardSort($q, $arguments, ['id', 'created_at', 'duration_ms'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn ($run) => [
                'id' => $run->id,
                'uuid' => $run->uuid,
                'trigger_type' => $run->trigger_type,
                'status' => $run->status,
                'prompts_evaluated' => $run->prompts_evaluated,
                'entities_analyzed' => $run->entities_analyzed,
                'signals_created' => $run->signals_created,
                'inquiries_created' => $run->inquiries_created,
                'memory_updates' => $run->memory_updates,
                'do_nothing_count' => $run->do_nothing_count,
                'duration_ms' => $run->duration_ms,
                'llm_model' => $run->llm_model,
                'error_message' => $run->error_message,
                'created_at' => $run->created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Runs: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'inference', 'runs', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
