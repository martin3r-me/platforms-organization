<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessFlow;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessFlowTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_flows.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-flows - Erstellt eine Verbindung (Flow) zwischen zwei Prozess-Schritten.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'              => ['type' => 'integer'],
                'process_id'           => ['type' => 'integer', 'description' => 'ERFORDERLICH: Zugehöriger Prozess.'],
                'from_step_id'         => ['type' => 'integer', 'description' => 'ERFORDERLICH: Quell-Schritt.'],
                'to_step_id'           => ['type' => 'integer', 'description' => 'ERFORDERLICH: Ziel-Schritt.'],
                'condition_label'      => ['type' => 'string', 'description' => 'Optional: Beschriftung der Bedingung.'],
                'condition_expression' => ['type' => 'object', 'description' => 'Optional: JSON-Bedingung.'],
                'is_default'           => ['type' => 'boolean', 'description' => 'Optional: Default true.'],
                'metadata'             => ['type' => 'object'],
            ],
            'required' => ['process_id', 'from_step_id', 'to_step_id'],
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

            $processId  = (int) ($arguments['process_id'] ?? 0);
            $fromStepId = (int) ($arguments['from_step_id'] ?? 0);
            $toStepId   = (int) ($arguments['to_step_id'] ?? 0);

            if ($processId <= 0 || $fromStepId <= 0 || $toStepId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'process_id, from_step_id und to_step_id sind erforderlich.');
            }

            $flow = OrganizationProcessFlow::create([
                'team_id'              => $rootTeamId,
                'user_id'              => $context->user?->id,
                'process_id'           => $processId,
                'from_step_id'         => $fromStepId,
                'to_step_id'           => $toStepId,
                'condition_label'      => ($arguments['condition_label'] ?? null) ?: null,
                'condition_expression' => $arguments['condition_expression'] ?? null,
                'is_default'           => $arguments['is_default'] ?? true,
                'metadata'             => $arguments['metadata'] ?? null,
            ]);

            return ToolResult::success([
                'id'           => $flow->id,
                'uuid'         => $flow->uuid,
                'process_id'   => $flow->process_id,
                'from_step_id' => $flow->from_step_id,
                'to_step_id'   => $flow->to_step_id,
                'team_id'      => $flow->team_id,
                'message'      => 'Prozess-Flow erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Prozess-Flows: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_flows', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
