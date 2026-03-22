<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationReportType;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListReportTypesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.report_types.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/report_types - Listet Berichtstypen im Team. Unterstützt filters/search/sort/limit/offset. Berichtstypen definieren Hülle, Anforderungen und Module für automatische Berichtsgenerierung.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'is_active', 'frequency']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Wird auf Root/Elterteam aufgelöst.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: aktive/inaktive Berichtstypen. Default: alle.',
                    ],
                    'frequency' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Frequenz (daily/weekly/monthly/manual).',
                    ],
                    'module' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Modul (enthält dieses Modul in modules-Array).',
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

            $q = OrganizationReportType::query()->where('team_id', $rootTeamId);

            if (array_key_exists('is_active', $arguments) && $arguments['is_active'] !== null) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }

            if (!empty($arguments['frequency'])) {
                $q->where('frequency', (string) $arguments['frequency']);
            }

            if (!empty($arguments['module'])) {
                $q->whereJsonContains('modules', (string) $arguments['module']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'is_active', 'frequency', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'key', 'description']);
            $this->applyStandardSort($q, $arguments, ['name', 'key', 'id', 'created_at', 'frequency'], 'name', 'asc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(function ($rt) {
                return [
                    'id' => $rt->id,
                    'uuid' => $rt->uuid,
                    'name' => $rt->name,
                    'key' => $rt->key,
                    'description' => $rt->description,
                    'hull' => $rt->hull,
                    'requirements' => $rt->requirements,
                    'modules' => $rt->modules,
                    'include_time_entries' => (bool) $rt->include_time_entries,
                    'frequency' => $rt->frequency,
                    'output_channel' => $rt->output_channel,
                    'obsidian_folder' => $rt->obsidian_folder,
                    'is_active' => (bool) $rt->is_active,
                    'created_at' => $rt->created_at?->toIso8601String(),
                ];
            })->values()->toArray();

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'team_id' => $resolved['team_id'],
                'root_team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Berichtstypen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'report_types', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
