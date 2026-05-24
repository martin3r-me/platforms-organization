<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdatePersonSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.person_skills.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/person-skills - Aktualisiert die Skill-Zuordnung einer Person (level, certified_at, notes).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'          => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'person_entity_id' => ['type' => 'integer', 'description' => 'ID der Person-Entity (ERFORDERLICH).'],
                'skill_id'         => ['type' => 'integer', 'description' => 'ID des Skills (ERFORDERLICH).'],
                'level'            => ['type' => 'string', 'description' => 'Optional: basic/advanced/expert.'],
                'certified_at'     => ['type' => 'string', 'description' => 'Optional: Datum (YYYY-MM-DD). "" zum Leeren.'],
                'notes'            => ['type' => 'string', 'description' => 'Optional: Anmerkungen. "" zum Leeren.'],
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
            if (! $person->skills()->where('organization_skills.id', $skillId)->exists()) {
                return ToolResult::error('NOT_FOUND', 'Skill ist dieser Person nicht zugeordnet.');
            }

            $pivotUpdate = [];
            if (array_key_exists('level', $arguments)) {
                $level = $arguments['level'];
                if (! in_array($level, ['basic', 'advanced', 'expert'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'level muss basic, advanced oder expert sein.');
                }
                $pivotUpdate['level'] = $level;
            }
            if (array_key_exists('certified_at', $arguments)) {
                $val = (string) ($arguments['certified_at'] ?? '');
                $pivotUpdate['certified_at'] = $val === '' ? null : $val;
            }
            if (array_key_exists('notes', $arguments)) {
                $val = (string) ($arguments['notes'] ?? '');
                $pivotUpdate['notes'] = $val === '' ? null : $val;
            }

            if (! empty($pivotUpdate)) {
                $person->skills()->updateExistingPivot($skillId, $pivotUpdate);
            }

            return ToolResult::success([
                'person_entity_id' => $person->id,
                'skill_id'         => $skillId,
                'message'          => 'Person-Skill-Zuordnung aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'persons', 'skills', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
