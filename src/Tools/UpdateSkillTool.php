<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationSkill;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateSkillTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.skills.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/skills/{id} - Aktualisiert einen Skill im Katalog.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'skill_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Skills (ERFORDERLICH). Nutze organization.skills.GET.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name des Skills.',
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Optional: Kategorie (technical/methodical/domain).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung ("" zum Leeren).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
            ],
            'required' => ['skill_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int)$resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'skill_id',
                OrganizationSkill::class,
                'NOT_FOUND',
                'Skill nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationSkill $skill */
            $skill = $found['model'];
            if ((int)$skill->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Skill gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];
            if (array_key_exists('name', $arguments)) {
                $name = trim((string)($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }
            if (array_key_exists('category', $arguments)) {
                $category = trim((string)($arguments['category'] ?? ''));
                if (!in_array($category, ['technical', 'methodical', 'domain'], true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'category muss technical, methodical oder domain sein.');
                }
                $update['category'] = $category;
            }
            if (array_key_exists('description', $arguments)) {
                $d = (string)($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool)$arguments['is_active'];
            }

            if (!empty($update)) {
                $skill->update($update);
            }
            $skill->refresh();

            return ToolResult::success([
                'id' => $skill->id,
                'uuid' => $skill->uuid,
                'name' => $skill->name,
                'category' => $skill->category,
                'description' => $skill->description,
                'is_active' => (bool)$skill->is_active,
                'message' => 'Skill erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Skills: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'skills', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
