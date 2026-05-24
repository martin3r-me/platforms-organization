<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationSoftSkill;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateSoftSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.soft_skills.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/soft-skills/{id} - Aktualisiert einen Soft-Skill im Katalog.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'       => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'soft_skill_id' => ['type' => 'integer', 'description' => 'ID des Soft-Skills (ERFORDERLICH).'],
                'name'          => ['type' => 'string', 'description' => 'Neuer Name.'],
                'description'   => ['type' => 'string', 'description' => 'Neue Beschreibung. "" zum Leeren.'],
                'is_active'     => ['type' => 'boolean', 'description' => 'Aktivstatus.'],
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

            $update = [];
            if (array_key_exists('name', $arguments)) {
                $val = trim((string) ($arguments['name'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                // Check unique
                $duplicate = OrganizationSoftSkill::where('team_id', $rootTeamId)->where('name', $val)->where('id', '!=', $ss->id)->exists();
                if ($duplicate) {
                    return ToolResult::error('VALIDATION_ERROR', "Soft-Skill '{$val}' existiert bereits in diesem Team.");
                }
                $update['name'] = $val;
            }
            if (array_key_exists('description', $arguments)) {
                $val = (string) ($arguments['description'] ?? '');
                $update['description'] = $val === '' ? null : $val;
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (! empty($update)) {
                $ss->update($update);
            }
            $ss->refresh();

            return ToolResult::success([
                'id'        => $ss->id,
                'uuid'      => $ss->uuid,
                'name'      => $ss->name,
                'is_active' => $ss->is_active,
                'message'   => 'Soft-Skill erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Soft-Skills: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'soft_skills', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
