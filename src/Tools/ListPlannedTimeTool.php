<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationTimePlanned;
use Platform\Organization\Services\ContextTypeRegistry;
use Platform\Organization\Services\TimeContextResolver;
use Platform\Organization\Tools\Concerns\ResolvesTimeEntryTeamScope;

class ListPlannedTimeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesTimeEntryTeamScope;

    public function getName(): string
    {
        return 'organization.planned_time.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/planned-time - Listet Soll-Zeiteinträge (geplante Zeiten) mit flexiblen Filtern (Kontext, User, aktiv-Status). Unterstützt cross-team Abfragen.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'cross_team' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Cross-Team Abfrage. Default: true.',
                    ],
                    'user_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach User-ID.',
                    ],
                    'context_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Kontext-Typ. Kurzformen: "project", "task", "ticket", "company".',
                        'enum' => ['project', 'task', 'ticket', 'company'],
                    ],
                    'context_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Kontext-ID.',
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach aktiv-Status. Default: nur aktive.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $scope = $this->resolveTimeEntryTeamScope($arguments, $context);

            if ($scope['error']) {
                return $scope['error'];
            }

            $teamIds = $scope['team_ids'];
            $isCrossTeam = $scope['is_cross_team'];

            $query = OrganizationTimePlanned::query()
                ->whereIn('team_id', $teamIds)
                ->with(['user', 'team']);

            // Default: nur aktive Einträge (wenn nicht explizit anders)
            if (!array_key_exists('is_active', $arguments)) {
                $query->active();
            } elseif ($arguments['is_active'] !== null) {
                $query->where('is_active', (bool) $arguments['is_active']);
            }

            if (isset($arguments['user_id'])) {
                $query->where('user_id', (int) $arguments['user_id']);
            }

            $contextType = isset($arguments['context_type'])
                ? (ContextTypeRegistry::resolve($arguments['context_type']) ?? $arguments['context_type'])
                : null;

            if ($contextType && isset($arguments['context_id'])) {
                $query->forContextKey($contextType, (int) $arguments['context_id']);
            } elseif ($contextType) {
                $query->where('context_type', $contextType);
            }

            $this->applyStandardFilters($query, $arguments, ['team_id', 'user_id', 'is_active', 'context_type', 'context_id']);
            $this->applyStandardSearch($query, $arguments, ['note']);
            $this->applyStandardSort($query, $arguments, ['planned_minutes', 'created_at', 'id'], 'created_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);
            $entries = $result['data'];

            $labelResolver = app(TimeContextResolver::class);

            $items = $entries->map(function (OrganizationTimePlanned $entry) use ($labelResolver) {
                $contextLabel = null;
                if ($entry->context_type && $entry->context_id) {
                    $contextLabel = $labelResolver->resolveLabel($entry->context_type, $entry->context_id);
                }

                return [
                    'id' => $entry->id,
                    'uuid' => $entry->uuid,
                    'team_id' => $entry->team_id,
                    'team_name' => $entry->team?->name,
                    'user_id' => $entry->user_id,
                    'user_name' => $entry->user?->name,
                    'planned_minutes' => $entry->planned_minutes,
                    'hours' => $entry->hours,
                    'context_type' => $entry->context_type,
                    'context_id' => $entry->context_id,
                    'context_label' => $contextLabel,
                    'note' => $entry->note,
                    'is_active' => (bool) $entry->is_active,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ];
            })->values()->toArray();

            $totalMinutes = $entries->sum('planned_minutes');

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'summary' => [
                    'count' => count($items),
                    'total_planned_minutes' => $totalMinutes,
                    'total_planned_hours' => round($totalMinutes / 60, 2),
                ],
                'team_ids' => $teamIds,
                'cross_team' => $isCrossTeam,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Soll-Zeiteinträge: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'planned_time', 'list', 'time_planning'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
