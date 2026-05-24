<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationJobProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateJobProfileSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.job_profile_skills.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/job-profile-skills - Aktualisiert die Zuordnung eines Skills zu einem JobProfile (level, is_required, sort_order).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'        => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'job_profile_id' => ['type' => 'integer', 'description' => 'ID des JobProfiles (ERFORDERLICH).'],
                'skill_id'       => ['type' => 'integer', 'description' => 'ID des Skills (ERFORDERLICH).'],
                'level'          => ['type' => 'string', 'description' => 'Optional: basic/advanced/expert.'],
                'is_required'    => ['type' => 'boolean', 'description' => 'Optional: Pflicht-Skill?'],
                'sort_order'     => ['type' => 'integer', 'description' => 'Optional: Sortierung.'],
            ],
            'required' => ['job_profile_id', 'skill_id'],
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
                $arguments, $context, 'job_profile_id',
                OrganizationJobProfile::class, 'NOT_FOUND', 'JobProfile nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            $jp = $found['model'];
            if ((int) $jp->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'JobProfile gehört nicht zum Team.');
            }

            $skillId = (int) ($arguments['skill_id'] ?? 0);
            if (! $jp->skillRecords()->where('organization_skills.id', $skillId)->exists()) {
                return ToolResult::error('NOT_FOUND', 'Skill ist diesem JobProfile nicht zugeordnet.');
            }

            $pivotUpdate = [];
            if (array_key_exists('level', $arguments)) {
                $level = $arguments['level'];
                if (! in_array($level, ['basic', 'advanced', 'expert'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'level muss basic, advanced oder expert sein.');
                }
                $pivotUpdate['level'] = $level;
            }
            if (array_key_exists('is_required', $arguments)) {
                $pivotUpdate['is_required'] = (bool) $arguments['is_required'];
            }
            if (array_key_exists('sort_order', $arguments)) {
                $pivotUpdate['sort_order'] = (int) $arguments['sort_order'];
            }

            if (! empty($pivotUpdate)) {
                $jp->skillRecords()->updateExistingPivot($skillId, $pivotUpdate);
            }

            return ToolResult::success([
                'job_profile_id' => $jp->id,
                'skill_id'       => $skillId,
                'message'        => 'Skill-Zuordnung aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'job_profiles', 'skills', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
