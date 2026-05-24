<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationSoftSkill;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListSoftSkillsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.soft_skills.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/soft-skills - Listet den Soft-Skill-Katalog des Teams. Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'is_active']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach Aktivstatus. Default: true.',
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
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = OrganizationSoftSkill::query()->where('team_id', $rootTeamId);

            $isActive = $arguments['is_active'] ?? true;
            if ($isActive !== null && $isActive !== '') {
                $q->where('is_active', (bool) $isActive);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'is_active', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'id', 'created_at'], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationSoftSkill $ss) => [
                'id'          => $ss->id,
                'uuid'        => $ss->uuid,
                'name'        => $ss->name,
                'description' => $ss->description,
                'is_active'   => $ss->is_active,
                'usage_count' => $ss->jobProfiles()->count() + $ss->persons()->count(),
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Soft-Skills: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'soft_skills', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
