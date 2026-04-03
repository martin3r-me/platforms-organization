<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationSlaContract;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class ListSlaContractsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.sla_contracts.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/sla_contracts - Listet SLA-Verträge im Team. Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'is_active']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext. Wird auf Root/Elterteam aufgelöst.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur aktive/inaktive SLA-Verträge. Default: keine Filterung.',
                    ],
                    'include_interlinks' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Verknüpfte Relationship-Interlinks mitladen. Default: false.',
                        'default' => false,
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

            $q = OrganizationSlaContract::query()->where('team_id', $rootTeamId);

            if (array_key_exists('is_active', $arguments) && $arguments['is_active'] !== null) {
                $q->where('is_active', (bool) $arguments['is_active']);
            }

            $includeInterlinks = (bool) ($arguments['include_interlinks'] ?? false);
            if ($includeInterlinks) {
                $q->with(['relationshipInterlinks']);
            }

            $this->applyStandardFilters($q, $arguments, ['team_id', 'is_active', 'created_at']);
            $this->applyStandardSearch($q, $arguments, ['name', 'description']);
            $this->applyStandardSort($q, $arguments, ['id', 'name', 'created_at', 'response_time_hours', 'resolution_time_hours'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($q, $arguments);

            $items = $result['data']->map(function ($sla) use ($includeInterlinks) {
                $item = [
                    'id' => $sla->id,
                    'uuid' => $sla->uuid,
                    'name' => $sla->name,
                    'description' => $sla->description,
                    'response_time_hours' => $sla->response_time_hours,
                    'resolution_time_hours' => $sla->resolution_time_hours,
                    'error_tolerance_percent' => $sla->error_tolerance_percent,
                    'is_active' => (bool) $sla->is_active,
                    'created_at' => $sla->created_at?->toIso8601String(),
                ];

                if ($includeInterlinks) {
                    $item['relationship_interlinks'] = $sla->relationshipInterlinks->map(fn ($eri) => [
                        'id' => $eri->id,
                        'uuid' => $eri->uuid,
                        'entity_relationship_id' => $eri->entity_relationship_id,
                        'interlink_id' => $eri->interlink_id,
                    ])->values()->toArray();
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der SLA-Verträge: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'sla', 'contracts', 'lookup'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
