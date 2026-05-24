<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationSkill;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class AssignPersonSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_skills.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/person-skills - Ordnet einen Skill einer Person zu.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'person_entity_id' => ['type' => 'integer', 'description' => 'ID der Person-Entity (ERFORDERLICH).'],
                'skill_id'         => ['type' => 'integer', 'description' => 'ID des Skills (ERFORDERLICH).'],
                'level'            => ['type' => 'string', 'description' => 'Optional: basic/advanced/expert. Default: basic.'],
                'certified_at'     => ['type' => 'string', 'description' => 'Optional: Zertifizierungsdatum (YYYY-MM-DD).'],
                'notes'            => ['type' => 'string', 'description' => 'Optional: Anmerkungen.'],
            ],
            'required' => ['person_entity_id', 'skill_id'],
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

            $skillId = (int) ($arguments['skill_id'] ?? 0);
            $skill = OrganizationSkill::find($skillId);
            if (! $skill || (int) $skill->team_id !== $rootTeamId) {
                return ToolResult::error('NOT_FOUND', 'Skill nicht gefunden oder gehört nicht zum Team.');
            }

            if ($person->skills()->where('organization_skills.id', $skillId)->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Skill ist dieser Person bereits zugeordnet.');
            }

            $level = $arguments['level'] ?? 'basic';
            if (! in_array($level, ['basic', 'advanced', 'expert'])) {
                $level = 'basic';
            }

            $person->skills()->attach($skillId, [
                'level'        => $level,
                'certified_at' => ($arguments['certified_at'] ?? null) ?: null,
                'notes'        => ($arguments['notes'] ?? null) ?: null,
            ]);

            return ToolResult::success([
                'person_entity_id' => $person->id,
                'skill_id'         => $skillId,
                'level'            => $level,
                'message'          => 'Skill erfolgreich zugeordnet.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'persons', 'skills', 'assign'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
