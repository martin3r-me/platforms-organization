<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessStep;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class BulkCreateProcessStepsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_steps.bulk_POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-steps/bulk - Erstellt mehrere Prozess-Schritte auf einmal. Effizienter als einzelne POST-Aufrufe beim Aufbau eines Prozesses.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'process_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Zugehöriger Prozess.'],
                'steps' => [
                    'type' => 'array',
                    'description' => 'ERFORDERLICH: Array von Steps. Jeder Step benötigt mindestens name und position.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name'                    => ['type' => 'string', 'description' => 'ERFORDERLICH.'],
                            'description'             => ['type' => 'string'],
                            'position'                => ['type' => 'integer', 'description' => 'ERFORDERLICH: Reihenfolge im Prozess.'],
                            'step_type'               => ['type' => 'string', 'description' => 'Optional: action | gateway | wait | subprocess. Default: action.'],
                            'duration_target_minutes' => ['type' => 'integer', 'description' => 'Optional: Soll-Dauer in Minuten.'],
                            'wait_target_minutes'     => ['type' => 'integer', 'description' => 'Optional: Soll-Wartezeit in Minuten.'],
                            'corefit_classification'  => ['type' => 'string', 'description' => 'Optional: core | context | no_fit.'],
                            'automation_level'        => ['type' => 'string', 'description' => 'Optional: human | llm_assisted | llm_autonomous | hybrid.'],
                            'sub_process_id'          => ['type' => 'integer'],
                            'is_active'               => ['type' => 'boolean'],
                            'metadata'                => ['type' => 'object'],
                        ],
                        'required' => ['name', 'position'],
                    ],
                ],
            ],
            'required' => ['process_id', 'steps'],
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

            $stepsData = $arguments['steps'] ?? [];
            if (empty($stepsData)) {
                return ToolResult::error('VALIDATION_ERROR', 'steps Array darf nicht leer sein.');
            }

            $created = [];
            $errors = [];

            foreach ($stepsData as $i => $stepData) {
                $name = trim((string) ($stepData['name'] ?? ''));
                $position = $stepData['position'] ?? null;

                if ($name === '') {
                    $errors[] = "Step #{$i}: name ist erforderlich.";
                    continue;
                }
                if ($position === null || $position === '') {
                    $errors[] = "Step #{$i} ({$name}): position ist erforderlich.";
                    continue;
                }

                $step = OrganizationProcessStep::create([
                    'team_id'                 => $rootTeamId,
                    'user_id'                 => $context->user?->id,
                    'process_id'              => $processId,
                    'name'                    => $name,
                    'description'             => ($stepData['description'] ?? null) ?: null,
                    'position'                => (int) $position,
                    'step_type'               => ($stepData['step_type'] ?? 'action'),
                    'duration_target_minutes' => isset($stepData['duration_target_minutes']) ? (int) $stepData['duration_target_minutes'] : null,
                    'wait_target_minutes'     => isset($stepData['wait_target_minutes']) ? (int) $stepData['wait_target_minutes'] : null,
                    'corefit_classification'  => ($stepData['corefit_classification'] ?? null) ?: null,
                    'automation_level'        => ($stepData['automation_level'] ?? null) ?: null,
                    'sub_process_id'          => ! empty($stepData['sub_process_id']) ? (int) $stepData['sub_process_id'] : null,
                    'is_active'               => $stepData['is_active'] ?? true,
                    'metadata'                => $stepData['metadata'] ?? null,
                ]);

                $created[] = [
                    'id'       => $step->id,
                    'uuid'     => $step->uuid,
                    'name'     => $step->name,
                    'position' => $step->position,
                ];
            }

            $result = [
                'process_id' => $processId,
                'created_count' => count($created),
                'created' => $created,
                'message' => count($created) . ' Prozess-Schritte erstellt.',
            ];

            if (! empty($errors)) {
                $result['errors'] = $errors;
                $result['message'] .= ' ' . count($errors) . ' Fehler.';
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Erstellen der Prozess-Schritte: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_steps', 'bulk', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
