<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationSoftSkill;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteSoftSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.soft_skills.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/soft-skills/{id} - Löscht einen Soft-Skill (soft delete). Warnung wenn Zuordnungen existieren.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'       => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'soft_skill_id' => ['type' => 'integer', 'description' => 'ID des Soft-Skills (ERFORDERLICH).'],
            ],
            'required' => ['soft_skill_id'],
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
                'soft_skill_id',
                OrganizationSoftSkill::class,
                'NOT_FOUND',
                'Soft-Skill nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationSoftSkill $ss */
            $ss = $found['model'];
            if ((int) $ss->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Soft-Skill gehört nicht zum Team.');
            }

            if ($ss->jobProfiles()->exists() || $ss->persons()->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Soft-Skill ist JobProfiles oder Personen zugeordnet. Bitte zuerst Zuordnungen entfernen oder is_active=false setzen.');
            }

            $ss->delete();

            return ToolResult::success([
                'id'      => $ss->id,
                'message' => 'Soft-Skill gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Soft-Skills: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'soft_skills', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
