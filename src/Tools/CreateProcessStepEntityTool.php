<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessStepEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessStepEntityTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_step_entities.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-step-entities - Ordnet eine Entity/EntityType einem Prozess-Schritt zu. role: executes | decides | informs | receives | provides_input.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'         => ['type' => 'integer'],
                'process_step_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Prozess-Schritt.'],
                'entity_type_id'  => ['type' => 'integer', 'description' => 'Optional: EntityType (Abteilung, Person, etc.).'],
                'entity_id'       => ['type' => 'integer', 'description' => 'Optional: Konkrete Entity.'],
                'role'            => ['type' => 'string', 'description' => 'ERFORDERLICH: executes | decides | informs | receives | provides_input.'],
                'metadata'        => ['type' => 'object'],
            ],
            'required' => ['process_step_id', 'role'],
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

            $processStepId = (int) ($arguments['process_step_id'] ?? 0);
            $role = trim((string) ($arguments['role'] ?? ''));

            if ($processStepId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'process_step_id ist erforderlich.');
            }
            if ($role === '') {
                return ToolResult::error('VALIDATION_ERROR', 'role ist erforderlich.');
            }

            $stepEntity = OrganizationProcessStepEntity::create([
                'team_id'         => $rootTeamId,
                'user_id'         => $context->user?->id,
                'process_step_id' => $processStepId,
                'entity_type_id'  => ! empty($arguments['entity_type_id']) ? (int) $arguments['entity_type_id'] : null,
                'entity_id'       => ! empty($arguments['entity_id']) ? (int) $arguments['entity_id'] : null,
                'role'            => $role,
                'metadata'        => $arguments['metadata'] ?? null,
            ]);

            return ToolResult::success([
                'id'              => $stepEntity->id,
                'uuid'            => $stepEntity->uuid,
                'process_step_id' => $stepEntity->process_step_id,
                'entity_type_id'  => $stepEntity->entity_type_id,
                'entity_id'       => $stepEntity->entity_id,
                'role'            => $stepEntity->role,
                'team_id'         => $stepEntity->team_id,
                'message'         => 'Step-Entity-Zuordnung erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Step-Entity-Zuordnung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_step_entities', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
