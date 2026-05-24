<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationJobProfile;
use Platform\Organization\Models\OrganizationSoftSkill;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class AssignJobProfileSoftSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.job_profile_soft_skills.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/job-profile-soft-skills - Ordnet einen Soft-Skill einem JobProfile zu.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'        => ['type' => 'integer', 'description' => 'Optional: Team-ID.'],
                'job_profile_id' => ['type' => 'integer', 'description' => 'ID des JobProfiles (ERFORDERLICH).'],
                'soft_skill_id'  => ['type' => 'integer', 'description' => 'ID des Soft-Skills (ERFORDERLICH).'],
                'level'          => ['type' => 'string', 'description' => 'Optional: basic/advanced/expert. Default: expert.'],
                'is_required'    => ['type' => 'boolean', 'description' => 'Optional: Pflicht-Soft-Skill? Default: true.'],
                'sort_order'     => ['type' => 'integer', 'description' => 'Optional: Sortierung. Default: 0.'],
            ],
            'required' => ['job_profile_id', 'soft_skill_id'],
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

            $softSkillId = (int) ($arguments['soft_skill_id'] ?? 0);
            $softSkill = OrganizationSoftSkill::find($softSkillId);
            if (! $softSkill || (int) $softSkill->team_id !== $rootTeamId) {
                return ToolResult::error('NOT_FOUND', 'Soft-Skill nicht gefunden oder gehört nicht zum Team.');
            }

            // Check duplicate
            if ($jp->softSkillRecords()->where('organization_soft_skills.id', $softSkillId)->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Soft-Skill ist bereits diesem JobProfile zugeordnet.');
            }

            $level = $arguments['level'] ?? 'expert';
            if (! in_array($level, ['basic', 'advanced', 'expert'])) {
                $level = 'expert';
            }

            $jp->softSkillRecords()->attach($softSkillId, [
                'level'       => $level,
                'is_required' => $arguments['is_required'] ?? true,
                'sort_order'  => (int) ($arguments['sort_order'] ?? 0),
            ]);

            return ToolResult::success([
                'job_profile_id' => $jp->id,
                'soft_skill_id'  => $softSkillId,
                'level'          => $level,
                'message'        => 'Soft-Skill erfolgreich zugeordnet.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'job_profiles', 'soft_skills', 'assign'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
