<?php

namespace Platform\Organization\Tools;

use Illuminate\Validation\ValidationException;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationRoleAssignment;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateRoleAssignmentTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.role_assignments.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/role-assignments - Weist einer Person eine Rolle im Kontext einer Entity zu. person_entity_id muss EntityType "person" sein und darf nicht gleich context_entity_id sein.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'           => ['type' => 'integer'],
                'role_id'           => ['type' => 'integer', 'description' => 'ERFORDERLICH: ID der Rolle.'],
                'person_entity_id'  => ['type' => 'integer', 'description' => 'ERFORDERLICH: Entity-ID einer Person.'],
                'context_entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Entity-ID des Kontexts (Projekt, Abteilung, etc.).'],
                'percentage'        => ['type' => 'integer', 'description' => 'Optional: 0–100.'],
                'valid_from'        => ['type' => 'string', 'description' => 'Optional: YYYY-MM-DD.'],
                'valid_to'          => ['type' => 'string', 'description' => 'Optional: YYYY-MM-DD.'],
                'note'              => ['type' => 'string', 'description' => 'Optional.'],
            ],
            'required' => ['role_id', 'person_entity_id', 'context_entity_id'],
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

            $roleId    = (int) ($arguments['role_id'] ?? 0);
            $personId  = (int) ($arguments['person_entity_id'] ?? 0);
            $contextId = (int) ($arguments['context_entity_id'] ?? 0);

            if ($roleId <= 0 || $personId <= 0 || $contextId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'role_id, person_entity_id und context_entity_id sind erforderlich.');
            }

            $percentage = null;
            if (array_key_exists('percentage', $arguments) && $arguments['percentage'] !== null && $arguments['percentage'] !== '') {
                $percentage = (int) $arguments['percentage'];
                if ($percentage < 0 || $percentage > 100) {
                    return ToolResult::error('VALIDATION_ERROR', 'percentage muss zwischen 0 und 100 liegen.');
                }
            }

            $assignment = OrganizationRoleAssignment::create([
                'team_id'           => $rootTeamId,
                'user_id'           => $context->user?->id,
                'role_id'           => $roleId,
                'person_entity_id'  => $personId,
                'context_entity_id' => $contextId,
                'percentage'        => $percentage,
                'valid_from'        => ($arguments['valid_from'] ?? null) ?: null,
                'valid_to'          => ($arguments['valid_to'] ?? null) ?: null,
                'note'              => ($arguments['note'] ?? null) ?: null,
            ]);

            return ToolResult::success([
                'id'                => $assignment->id,
                'role_id'           => $assignment->role_id,
                'person_entity_id'  => $assignment->person_entity_id,
                'context_entity_id' => $assignment->context_entity_id,
                'percentage'        => $assignment->percentage,
                'message'           => 'Rolle erfolgreich zugewiesen.',
            ]);
        } catch (ValidationException $e) {
            return ToolResult::error('VALIDATION_ERROR', collect($e->errors())->flatten()->first() ?? $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Rollen-Zuweisung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'role_assignments', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
