<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdatePersonSoftSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_soft_skills.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/person-soft-skills - Aktualisiert die Soft-Skill-Zuordnung einer Person.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'person_entity_id' => ['type' => 'integer', 'description' => 'ID der Person-Entity (ERFORDERLICH).'],
                'soft_skill_id'    => ['type' => 'integer', 'description' => 'ID des Soft-Skills (ERFORDERLICH).'],
                'level'            => ['type' => 'string', 'description' => 'Optional: basic/advanced/expert.'],
                'notes'            => ['type' => 'string', 'description' => 'Optional: Anmerkungen. "" zum Leeren.'],
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

            $pivotUpdate = [];
            if (array_key_exists('level', $arguments)) {
                $level = $arguments['level'];
                if (! in_array($level, ['basic', 'advanced', 'expert'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'level muss basic, advanced oder expert sein.');
                }
                $pivotUpdate['level'] = $level;
            }
            if (array_key_exists('notes', $arguments)) {
                $val = (string) ($arguments['notes'] ?? '');
                $pivotUpdate['notes'] = $val === '' ? null : $val;
            }

            if (! empty($pivotUpdate)) {
                $person->softSkills()->updateExistingPivot($softSkillId, $pivotUpdate);
            }

            return ToolResult::success([
                'person_entity_id' => $person->id,
                'soft_skill_id'    => $softSkillId,
                'message'          => 'Person-Soft-Skill-Zuordnung aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'persons', 'soft-skills', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
