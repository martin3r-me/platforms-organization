<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListSignalsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.signals.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/signals - Listet ausgelöste Signale (algedonic alerts) im Team. Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'status', 'severity', 'entity_id', 'signal_definition_id', 'source']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'status' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Status (open, acknowledged, resolved, dismissed).',
                    ],
                    'severity' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Severity (info, warning, critical, algedonic).',
                    ],
                    'source' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Quelle (rule, inference).',
                        'enum' => ['rule', 'inference'],
                    ],
                    'entity_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Entity-ID.',
                    ],
                    'signal_definition_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Signal-Definition-ID.',
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

            $q = OrganizationSignal::query()
                ->where('team_id', $rootTeamId)
                ->with(['entity:id,name', 'definition:id,name,pattern_type', 'inferencePrompt:id,name,vsm_system']);

            if (! empty($arguments['status'])) {
                $q->where('status', $arguments['status']);
            }

            if (! empty($arguments['severity'])) {
                $q->where('severity', $arguments['severity']);
            }

            if (! empty($arguments['entity_id'])) {
                $q->where('entity_id', (int) $arguments['entity_id']);
            }

            if (! empty($arguments['signal_definition_id'])) {
                $q->where('signal_definition_id', (int) $arguments['signal_definition_id']);
            }

            if (! empty($arguments['source'])) {
                $q->where('source', $arguments['source']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'status', 'severity', 'entity_id', 'signal_definition_id', 'source', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['message']);
            $this->applyStandardSort($q, $arguments, ['id', 'created_at', 'severity', 'status'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(fn ($signal) => [
                'id' => $signal->id,
                'uuid' => $signal->uuid,
                'entity_id' => $signal->entity_id,
                'entity_name' => $signal->entity?->name,
                'source' => $signal->source ?? 'rule',
                'signal_definition_id' => $signal->signal_definition_id,
                'definition_name' => $signal->definition?->name,
                'pattern_type' => $signal->definition?->pattern_type,
                'inference_prompt_id' => $signal->inference_prompt_id,
                'inference_prompt_name' => $signal->inferencePrompt?->name,
                'inference_vsm_system' => $signal->inferencePrompt?->vsm_system,
                'status' => $signal->status,
                'severity' => $signal->severity,
                'message' => $signal->message,
                'trigger_metrics' => $signal->trigger_metrics,
                'resolved_at' => $signal->resolved_at?->toIso8601String(),
                'created_at' => $signal->created_at?->toIso8601String(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Signale: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'signals', 'algedonic', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
