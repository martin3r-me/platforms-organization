<?php

namespace Platform\Organization\Tools;

use Illuminate\Validation\ValidationException;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationRoleAssignment;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateRoleAssignmentTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.role_assignments.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/role-assignments/{id} - Aktualisiert eine Rollen-Zuweisung.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'            => ['type' => 'integer'],
                'role_assignment_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'role_id'            => ['type' => 'integer'],
                'person_entity_id'   => ['type' => 'integer'],
                'context_entity_id'  => ['type' => 'integer'],
                'percentage'         => ['type' => 'integer'],
                'valid_from'         => ['type' => 'string'],
                'valid_to'           => ['type' => 'string'],
                'note'               => ['type' => 'string'],
            ],
            'required' => ['role_assignment_id'],
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
                'role_assignment_id',
                OrganizationRoleAssignment::class,
                'NOT_FOUND',
                'Rollen-Zuweisung nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationRoleAssignment $a */
            $a = $found['model'];
            if ((int) $a->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Zuweisung gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            foreach (['role_id', 'person_entity_id', 'context_entity_id'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (int) $arguments[$field];
                    if ($val <= 0) {
                        return ToolResult::error('VALIDATION_ERROR', $field.' muss eine gültige ID sein.');
                    }
                    $update[$field] = $val;
                }
            }
            if (array_key_exists('percentage', $arguments)) {
                if ($arguments['percentage'] === null || $arguments['percentage'] === '') {
                    $update['percentage'] = null;
                } else {
                    $p = (int) $arguments['percentage'];
                    if ($p < 0 || $p > 100) {
                        return ToolResult::error('VALIDATION_ERROR', 'percentage muss zwischen 0 und 100 liegen.');
                    }
                    $update['percentage'] = $p;
                }
            }
            foreach (['valid_from', 'valid_to', 'note'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }

            if (! empty($update)) {
                $a->update($update);
            }
            $a->refresh();

            return ToolResult::success([
                'id'                => $a->id,
                'role_id'           => $a->role_id,
                'person_entity_id'  => $a->person_entity_id,
                'context_entity_id' => $a->context_entity_id,
                'percentage'        => $a->percentage,
                'message'           => 'Rollen-Zuweisung erfolgreich aktualisiert.',
            ]);
        } catch (ValidationException $e) {
            return ToolResult::error('VALIDATION_ERROR', collect($e->errors())->flatten()->first() ?? $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'role_assignments', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
