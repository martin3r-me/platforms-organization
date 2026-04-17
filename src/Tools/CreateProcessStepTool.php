<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Enums\ProcessEventType;
use Platform\Organization\Enums\ProcessGatewayType;
use Platform\Organization\Enums\StepComplexity;
use Platform\Organization\Models\OrganizationProcessStep;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessStepTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_steps.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-steps - Erstellt einen Prozess-Schritt. step_type: action | gateway | wait | subprocess. corefit_classification: green | yellow | red.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'                 => ['type' => 'integer'],
                'process_id'              => ['type' => 'integer', 'description' => 'ERFORDERLICH: Zugehöriger Prozess.'],
                'name'                    => ['type' => 'string', 'description' => 'ERFORDERLICH.'],
                'description'             => ['type' => 'string'],
                'position'                => ['type' => 'integer', 'description' => 'ERFORDERLICH: Reihenfolge im Prozess.'],
                'step_type'               => ['type' => 'string', 'description' => 'Optional: action | gateway | wait | subprocess | event. Default: action.'],
                'gateway_type'            => ['type' => 'string', 'description' => 'ERFORDERLICH wenn step_type=gateway: exclusive | parallel | inclusive | event_based.'],
                'event_type'              => ['type' => 'string', 'description' => 'Optional (für step_type=event): start | end | intermediate_throw | intermediate_catch | timer | message | error | escalation.'],
                'duration_target_minutes' => ['type' => 'integer', 'description' => 'Optional: Soll-Dauer in Minuten.'],
                'wait_target_minutes'     => ['type' => 'integer', 'description' => 'Optional: Soll-Wartezeit in Minuten.'],
                'corefit_classification'  => ['type' => 'string', 'description' => 'Optional: green | yellow | red.'],
                'automation_level'        => ['type' => 'string', 'description' => 'Optional: human | llm_assisted | llm_autonomous | hybrid.'],
                'complexity'              => ['type' => 'string', 'description' => 'Optional: T-Shirt-Größe. xs | s | m | l | xl | xxl.'],
                'sub_process_id'          => ['type' => 'integer', 'description' => 'Optional: Verknüpfter Sub-Prozess (bei step_type=subprocess).'],
                'is_active'               => ['type' => 'boolean', 'description' => 'Optional: Default true.'],
                'metadata'                => ['type' => 'object'],
            ],
            'required' => ['process_id', 'name', 'position'],
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
            $name = trim((string) ($arguments['name'] ?? ''));
            $position = $arguments['position'] ?? null;

            if ($processId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'process_id ist erforderlich.');
            }
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }
            if ($position === null || $position === '') {
                return ToolResult::error('VALIDATION_ERROR', 'position ist erforderlich.');
            }

            $stepType = (string) ($arguments['step_type'] ?? 'action');
            $gatewayType = ($arguments['gateway_type'] ?? null) ?: null;
            $eventType = ($arguments['event_type'] ?? null) ?: null;

            if ($stepType === 'gateway' && ! $gatewayType) {
                return ToolResult::error('VALIDATION_ERROR', 'gateway_type ist bei step_type=gateway erforderlich.');
            }
            if ($gatewayType && ! in_array($gatewayType, ProcessGatewayType::values(), true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiger gateway_type. Erlaubt: '.implode(', ', ProcessGatewayType::values()));
            }
            if ($eventType && ! in_array($eventType, ProcessEventType::values(), true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültiger event_type. Erlaubt: '.implode(', ', ProcessEventType::values()));
            }

            $complexity = ($arguments['complexity'] ?? null) ?: null;
            if ($complexity && ! in_array($complexity, StepComplexity::values(), true)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültige complexity. Erlaubt: '.implode(', ', StepComplexity::values()));
            }

            $step = OrganizationProcessStep::create([
                'team_id'                 => $rootTeamId,
                'user_id'                 => $context->user?->id,
                'process_id'              => $processId,
                'name'                    => $name,
                'description'             => ($arguments['description'] ?? null) ?: null,
                'position'                => (int) $position,
                'step_type'               => $stepType,
                'gateway_type'            => $gatewayType,
                'event_type'              => $eventType,
                'duration_target_minutes' => isset($arguments['duration_target_minutes']) ? (int) $arguments['duration_target_minutes'] : null,
                'wait_target_minutes'     => isset($arguments['wait_target_minutes']) ? (int) $arguments['wait_target_minutes'] : null,
                'corefit_classification'  => ($arguments['corefit_classification'] ?? null) ?: null,
                'automation_level'        => ($arguments['automation_level'] ?? null) ?: null,
                'complexity'              => $complexity,
                'sub_process_id'          => ! empty($arguments['sub_process_id']) ? (int) $arguments['sub_process_id'] : null,
                'is_active'               => $arguments['is_active'] ?? true,
                'metadata'                => $arguments['metadata'] ?? null,
            ]);

            return ToolResult::success([
                'id'         => $step->id,
                'uuid'       => $step->uuid,
                'process_id' => $step->process_id,
                'name'       => $step->name,
                'position'   => $step->position,
                'step_type'        => $step->step_type,
                'sub_process_id'   => $step->sub_process_id,
                'team_id'          => $step->team_id,
                'message'    => 'Prozess-Schritt erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Prozess-Schritts: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_steps', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
