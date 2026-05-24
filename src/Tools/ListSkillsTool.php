<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationSkill;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListSkillsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.skills.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/skills - Listet den Skill-Katalog (technische, methodische, fachliche Skills) des Teams. Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'category', 'is_active']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Es wird automatisch auf das Root/Elterteam aufgelöst.',
                    ],
                    'category' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Kategorie (technical/methodical/domain).',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: aktive/inaktive Skills. Default: true.',
                        'default' => true,
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int)$resolved['root_team_id'];

            $q = OrganizationSkill::query()
                ->where('team_id', $rootTeamId);

            if (array_key_exists('category', $arguments) && $arguments['category'] !== null && $arguments['category'] !== '') {
                $q->where('category', trim((string)$arguments['category']));
            }

            $activeOnly = (bool)($arguments['is_active'] ?? true);
            if ($activeOnly) {
                $q->where('is_active', true);
            } elseif (array_key_exists('is_active', $arguments)) {
                $q->where('is_active', false);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'category', 'is_active']);
            $this->applyStandardSearch($q, $arguments, ['name', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'category', 'id', 'created_at'], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn ($skill) => [
                'id' => $skill->id,
                'uuid' => $skill->uuid,
                'name' => $skill->name,
                'category' => $skill->category,
                'description' => $skill->description,
                'is_active' => (bool)$skill->is_active,
                'usage_count' => $skill->jobProfiles()->count() + $skill->persons()->count(),
            ])->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Skills: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'skills', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
