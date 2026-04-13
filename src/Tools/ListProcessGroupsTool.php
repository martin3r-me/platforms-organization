<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationProcessGroup;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListProcessGroupsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_groups.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/process-groups - Listet Prozess-Gruppen im Team. Gruppen clustern Prozesse thematisch (z.B. "Entwicklung", "Administration").';
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
                        'description' => 'Optional: Nur aktive/inaktive Gruppen.',
                    ],
                    'include_process_count' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Anzahl der Prozesse pro Gruppe mitzählen. Default: true.',
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
            $rootTeamId = (int) $resolved['root_team_id'];

            $q = OrganizationProcessGroup::query()->where('team_id', $rootTeamId);

            if (array_key_exists('is_active', $arguments) && $arguments['is_active'] !== null) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }

            $includeCount = (bool) ($arguments['include_process_count'] ?? true);
            if ($includeCount) {
                $q->withCount('processes');
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'is_active']);
            $this->applyStandardSearch($q, $arguments, ['name', 'code', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'code', 'sort_order', 'id', 'created_at'], 'sort_order', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(function ($group) use ($includeCount) {
                $item = [
                    'id' => $group->id,
                    'uuid' => $group->uuid,
                    'name' => $group->name,
                    'code' => $group->code,
                    'description' => $group->description,
                    'icon' => $group->icon,
                    'sort_order' => $group->sort_order,
                    'is_active' => (bool) $group->is_active,
                    'metadata' => $group->metadata,
                    'created_at' => $group->created_at?->toIso8601String(),
                ];

                if ($includeCount) {
                    $item['processes_count'] = $group->processes_count ?? 0;
                }

                return $item;
            })->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Prozess-Gruppen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'processes', 'groups', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
