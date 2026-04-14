<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessFlow;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class BulkCreateProcessFlowsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_flows.bulk_POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-flows/bulk - Erstellt mehrere Prozess-Flows auf einmal. Ideal zum Verdrahten eines Prozesses nach dem Anlegen der Steps.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'process_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Zugehöriger Prozess.'],
                'flows' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Array von Flows. Jeder Flow benötigt from_step_id und to_step_id.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'from_step_id'         => ['type' => 'integer', 'description' => 'ERFORDERLICH: Quell-Schritt.'],
                            'to_step_id'           => ['type' => 'integer', 'description' => 'ERFORDERLICH: Ziel-Schritt.'],
                            'condition_label'      => ['type' => 'string', 'description' => 'Optional: Beschriftung der Bedingung.'],
                            'condition_expression' => ['type' => 'object', 'description' => 'Optional: JSON-Bedingung.'],
                            'is_default'           => ['type' => 'boolean', 'description' => 'Optional: Default true.'],
                            'metadata'             => ['type' => 'object'],
                        ],
                        'required' => ['from_step_id', 'to_step_id'],
                    ],
                ],
            ],
            'required' => ['process_id', 'flows'],
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

            $processId = (int) ($arguments['process_id'] ?? 0);
            if ($processId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'process_id ist erforderlich.');
            }

            $flowsData = $arguments['flows'] ?? [];
            if (empty($flowsData)) {
                return ToolResult::error('VALIDATION_ERROR', 'flows Array darf nicht leer sein.');
            }

            $created = [];
            $errors = [];

            foreach ($flowsData as $i => $flowData) {
                $fromStepId = (int) ($flowData['from_step_id'] ?? 0);
                $toStepId = (int) ($flowData['to_step_id'] ?? 0);

                if ($fromStepId <= 0 || $toStepId <= 0) {
                    $errors[] = "Flow #{$i}: from_step_id und to_step_id sind erforderlich.";
                    continue;
                }

                $flow = OrganizationProcessFlow::create([
                    'team_id'              => $rootTeamId,
                    'user_id'              => $context->user?->id,
                    'process_id'           => $processId,
                    'from_step_id'         => $fromStepId,
                    'to_step_id'           => $toStepId,
                    'condition_label'      => ($flowData['condition_label'] ?? null) ?: null,
                    'condition_expression' => $flowData['condition_expression'] ?? null,
                    'is_default'           => $flowData['is_default'] ?? true,
                    'metadata'             => $flowData['metadata'] ?? null,
                ]);

                $created[] = [
                    'id'           => $flow->id,
                    'uuid'         => $flow->uuid,
                    'from_step_id' => $flow->from_step_id,
                    'to_step_id'   => $flow->to_step_id,
                ];
            }

            $result = [
                'process_id' => $processId,
                'created_count' => count($created),
                'created' => $created,
                'message' => count($created) . ' Prozess-Flows erstellt.',
            ];

            if (! empty($errors)) {
                $result['errors'] = $errors;
                $result['message'] .= ' ' . count($errors) . ' Fehler.';
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Erstellen der Prozess-Flows: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_flows', 'bulk', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
