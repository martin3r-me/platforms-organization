<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class RemovePersonSoftSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_soft_skills.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/person-soft-skills - Entfernt einen Soft-Skill von einer Person.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'person_entity_id' => ['type' => 'integer', 'description' => 'ID der Person-Entity (ERFORDERLICH).'],
                'soft_skill_id'    => ['type' => 'integer', 'description' => 'ID des Soft-Skills (ERFORDERLICH).'],
            ],
            'required' => ['person_entity_id', 'soft_skill_id'],
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
                $arguments, $context, 'person_entity_id',
                OrganizationEntity::class, 'NOT_FOUND', 'Person-Entity nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            $person = $found['model'];
            if ((int) $person->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Person gehört nicht zum Team.');
            }

            $softSkillId = (int) ($arguments['soft_skill_id'] ?? 0);
            if (! $person->softSkills()->where('organization_soft_skills.id', $softSkillId)->exists()) {
                return ToolResult::error('NOT_FOUND', 'Soft-Skill ist dieser Person nicht zugeordnet.');
            }

            $person->softSkills()->detach($softSkillId);

            return ToolResult::success([
                'person_entity_id' => $person->id,
                'soft_skill_id'    => $softSkillId,
                'message'          => 'Soft-Skill-Zuordnung entfernt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'persons', 'soft-skills', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
