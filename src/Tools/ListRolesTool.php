<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationRole;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListRolesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.roles.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/roles - Listet Rollen-Katalog (z.B. "Projektleiter", "Scrum Master") im Root/Elterteam.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'status', 'slug']),
            [
                'properties' => [
                    'team_id' => ['type' => 'integer'],
                    'status'  => ['type' => 'string', 'description' => 'Optional: active/archived. Default: active.'],
                    'slug'    => ['type' => 'string', 'description' => 'Optional: Exakter slug-Filter.'],
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

            $q = OrganizationRole::query()->where('team_id', $rootTeamId);

            $status = $arguments['status'] ?? 'active';
            if ($status !== null && $status !== '') {
                $q->where('status', $status);
            }
            if (! empty($arguments['slug'])) {
                $q->where('slug', (string) $arguments['slug']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'status', 'slug', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'slug', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'slug', 'id', 'created_at'], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);
            $items = $result['data']->map(fn (OrganizationRole $r) => [
                'id'          => $r->id,
                'uuid'        => $r->uuid,
                'name'        => $r->name,
                'slug'        => $r->slug,
                'description' => $r->description,
                'status'      => $r->status,
                'team_id'     => $r->team_id,
            ])->values()->toArray();

            return ToolResult::success([
                'data'         => $items,
                'pagination'   => $result['pagination'] ?? null,
                'team_id'      => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Rollen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'roles', 'lookup'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
