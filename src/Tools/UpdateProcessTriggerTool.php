<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcessTrigger;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessTriggerTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_triggers.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/process-triggers/{id} - Aktualisiert einen Prozess-Trigger.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'              => ['type' => 'integer'],
                'process_trigger_id'   => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'label'                => ['type' => 'string'],
                'description'          => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'trigger_type'         => ['type' => 'string'],
                'entity_type_id'       => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'entity_id'            => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'source_process_id'    => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'interlink_id'         => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'schedule_expression'  => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'metadata'             => ['type' => 'object'],
            ],
            'required' => ['process_trigger_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'process_trigger_id',
                OrganizationProcessTrigger::class,
                'NOT_FOUND',
                'Prozess-Trigger nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessTrigger $trigger */
            $trigger = $found['model'];
            if ((int) $trigger->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess-Trigger gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            if (array_key_exists('label', $arguments)) {
                $val = trim((string) ($arguments['label'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'label darf nicht leer sein.');
                }
                $update['label'] = $val;
            }
            foreach (['description', 'schedule_expression'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }
            if (array_key_exists('trigger_type', $arguments)) {
                $update['trigger_type'] = (string) $arguments['trigger_type'];
            }
            foreach (['entity_type_id', 'entity_id', 'source_process_id', 'interlink_id'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = $arguments[$field];
                    $update[$field] = (! empty($val) && (int) $val > 0) ? (int) $val : null;
                }
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = $arguments['metadata'];
            }

            if (! empty($update)) {
                $trigger->update($update);
            }
            $trigger->refresh();

            return ToolResult::success([
                'id'           => $trigger->id,
                'uuid'         => $trigger->uuid,
                'process_id'   => $trigger->process_id,
                'label'        => $trigger->label,
                'trigger_type' => $trigger->trigger_type,
                'team_id'      => $trigger->team_id,
                'message'      => 'Prozess-Trigger erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Prozess-Triggers: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_triggers', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
