<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationRoleAssignment;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListRoleAssignmentsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.role_assignments.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/role-assignments - Listet Rollen-Zuweisungen (Person ⇄ Rolle ⇄ Kontext-Entity). Filter: person_entity_id, context_entity_id, role_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'person_entity_id', 'context_entity_id', 'role_id']),
            [
                'properties' => [
                    'team_id'           => ['type' => 'integer'],
                    'person_entity_id'  => ['type' => 'integer', 'description' => 'Optional: Filter nach Person.'],
                    'context_entity_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach Kontext-Entity.'],
                    'role_id'           => ['type' => 'integer', 'description' => 'Optional: Filter nach Rolle.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = OrganizationRoleAssignment::query()
                ->with([
                    'role:id,name,slug',
                    'person:id,name,entity_type_id',
                    'context:id,name,entity_type_id',
                ])
                ->where('team_id', $rootTeamId);

            if (! empty($arguments['person_entity_id'])) {
                $q->where('person_entity_id', (int) $arguments['person_entity_id']);
            }
            if (! empty($arguments['context_entity_id'])) {
                $q->where('context_entity_id', (int) $arguments['context_entity_id']);
            }
            if (! empty($arguments['role_id'])) {
                $q->where('role_id', (int) $arguments['role_id']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'person_entity_id', 'context_entity_id', 'role_id', 'created_at']);
            $this->applyStandardSort($q, $arguments, ['id', 'percentage', 'valid_from', 'created_at'], 'id', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationRoleAssignment $a) => [
                'id'                => $a->id,
                'role_id'           => $a->role_id,
                'role_name'         => $a->role?->name,
                'role_slug'         => $a->role?->slug,
                'person_entity_id'  => $a->person_entity_id,
                'person_name'       => $a->person?->name,
                'context_entity_id' => $a->context_entity_id,
                'context_name'      => $a->context?->name,
                'percentage'        => $a->percentage,
                'valid_from'        => $a->valid_from?->toDateString(),
                'valid_to'          => $a->valid_to?->toDateString(),
                'note'              => $a->note,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Rollen-Zuweisungen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'role_assignments', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
