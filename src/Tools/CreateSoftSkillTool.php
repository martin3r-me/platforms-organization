<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationSoftSkill;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateSoftSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.soft_skills.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/soft-skills - Erstellt einen Soft-Skill im Team-Katalog.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'     => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'name'        => ['type' => 'string', 'description' => 'ERFORDERLICH: Name des Soft-Skills.'],
                'description' => ['type' => 'string', 'description' => 'Optional: Beschreibung.'],
                'is_active'   => ['type' => 'boolean', 'description' => 'Optional: Aktivstatus. Default: true.'],
            ],
            'required' => ['name'],
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

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            // Unique check
            $exists = OrganizationSoftSkill::where('team_id', $rootTeamId)->where('name', $name)->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "Soft-Skill '{$name}' existiert bereits in diesem Team.");
            }

            $ss = OrganizationSoftSkill::create([
                'team_id'     => $rootTeamId,
                'name'        => $name,
                'description' => ($arguments['description'] ?? null) ?: null,
                'is_active'   => $arguments['is_active'] ?? true,
            ]);

            return ToolResult::success([
                'id'      => $ss->id,
                'uuid'    => $ss->uuid,
                'name'    => $ss->name,
                'message' => 'Soft-Skill erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Soft-Skills: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'soft_skills', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
