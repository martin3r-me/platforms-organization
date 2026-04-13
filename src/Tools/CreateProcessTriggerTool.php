<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessTrigger;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessTriggerTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_triggers.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-triggers - Erstellt einen Prozess-Trigger. trigger_type: entity | process | interlink | schedule | manual.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'             => ['type' => 'integer'],
                'process_id'          => ['type' => 'integer', 'description' => 'ERFORDERLICH: Zugehöriger Prozess.'],
                'label'               => ['type' => 'string', 'description' => 'ERFORDERLICH: Bezeichnung.'],
                'description'         => ['type' => 'string'],
                'trigger_type'        => ['type' => 'string', 'description' => 'ERFORDERLICH: entity | process | interlink | schedule | manual.'],
                'entity_type_id'      => ['type' => 'integer', 'description' => 'Optional: Für trigger_type=entity.'],
                'entity_id'           => ['type' => 'integer', 'description' => 'Optional: Für trigger_type=entity.'],
                'source_process_id'   => ['type' => 'integer', 'description' => 'Optional: Für trigger_type=process.'],
                'interlink_id'        => ['type' => 'integer', 'description' => 'Optional: Für trigger_type=interlink.'],
                'schedule_expression' => ['type' => 'string', 'description' => 'Optional: Für trigger_type=schedule (z.B. "0 8 * * 1").'],
                'metadata'            => ['type' => 'object'],
            ],
            'required' => ['process_id', 'label', 'trigger_type'],
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

            $processId   = (int) ($arguments['process_id'] ?? 0);
            $label       = trim((string) ($arguments['label'] ?? ''));
            $triggerType = trim((string) ($arguments['trigger_type'] ?? ''));

            if ($processId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'process_id ist erforderlich.');
            }
            if ($label === '') {
                return ToolResult::error('VALIDATION_ERROR', 'label ist erforderlich.');
            }
            if ($triggerType === '') {
                return ToolResult::error('VALIDATION_ERROR', 'trigger_type ist erforderlich.');
            }

            $trigger = OrganizationProcessTrigger::create([
                'team_id'             => $rootTeamId,
                'user_id'             => $context->user?->id,
                'process_id'          => $processId,
                'label'               => $label,
                'description'         => ($arguments['description'] ?? null) ?: null,
                'trigger_type'        => $triggerType,
                'entity_type_id'      => ! empty($arguments['entity_type_id']) ? (int) $arguments['entity_type_id'] : null,
                'entity_id'           => ! empty($arguments['entity_id']) ? (int) $arguments['entity_id'] : null,
                'source_process_id'   => ! empty($arguments['source_process_id']) ? (int) $arguments['source_process_id'] : null,
                'interlink_id'        => ! empty($arguments['interlink_id']) ? (int) $arguments['interlink_id'] : null,
                'schedule_expression' => ($arguments['schedule_expression'] ?? null) ?: null,
                'metadata'            => $arguments['metadata'] ?? null,
            ]);

            return ToolResult::success([
                'id'           => $trigger->id,
                'uuid'         => $trigger->uuid,
                'process_id'   => $trigger->process_id,
                'label'        => $trigger->label,
                'trigger_type' => $trigger->trigger_type,
                'team_id'      => $trigger->team_id,
                'message'      => 'Prozess-Trigger erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Prozess-Triggers: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_triggers', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
