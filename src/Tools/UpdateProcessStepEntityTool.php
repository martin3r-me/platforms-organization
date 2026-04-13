<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcessStepEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessStepEntityTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_step_entities.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/process-step-entities/{id} - Aktualisiert eine Step-Entity-Zuordnung.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'                   => ['type' => 'integer'],
                'process_step_entity_id'    => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'entity_type_id'            => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'entity_id'                 => ['type' => 'integer', 'description' => '0 oder null zum Leeren.'],
                'role'                      => ['type' => 'string'],
                'metadata'                  => ['type' => 'object'],
            ],
            'required' => ['process_step_entity_id'],
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
                'process_step_entity_id',
                OrganizationProcessStepEntity::class,
                'NOT_FOUND',
                'Step-Entity-Zuordnung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationProcessStepEntity $stepEntity */
            $stepEntity = $found['model'];
            if ((int) $stepEntity->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Step-Entity-Zuordnung gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            foreach (['entity_type_id', 'entity_id'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = $arguments[$field];
                    $update[$field] = (! empty($val) && (int) $val > 0) ? (int) $val : null;
                }
            }
            if (array_key_exists('role', $arguments)) {
                $val = trim((string) ($arguments['role'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'role darf nicht leer sein.');
                }
                $update['role'] = $val;
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = $arguments['metadata'];
            }

            if (! empty($update)) {
                $stepEntity->update($update);
            }
            $stepEntity->refresh();

            return ToolResult::success([
                'id'              => $stepEntity->id,
                'uuid'            => $stepEntity->uuid,
                'process_step_id' => $stepEntity->process_step_id,
                'entity_type_id'  => $stepEntity->entity_type_id,
                'entity_id'       => $stepEntity->entity_id,
                'role'            => $stepEntity->role,
                'team_id'         => $stepEntity->team_id,
                'message'         => 'Step-Entity-Zuordnung erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Step-Entity-Zuordnung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_step_entities', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
