<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Organization\Models\OrganizationTimeEntry;

class ListTimeEntriesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'organization.time_entries.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/time-entries - Listet Zeiteinträge mit flexiblen Filtern (Kontext, Zeitraum, User, Abrechnungsstatus). Unterstützt filters/search/sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(['team_id', 'user_id', 'is_billed', 'context_type', 'context_id', 'work_date']),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                    ],
                    'user_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach User-ID.',
                    ],
                    'context_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Kontext-Typ (morphTo Model-Klasse). Beispiel: "Platform\\Planner\\Models\\PlannerTask".',
                    ],
                    'context_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Kontext-ID. Wird zusammen mit context_type verwendet.',
                    ],
                    'root_context_type' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Root-Kontext-Typ. Beispiel: "Platform\\Planner\\Models\\PlannerProject".',
                    ],
                    'root_context_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Root-Kontext-ID.',
                    ],
                    'work_date_from' => [
                        'type' => 'string',
                        'description' => 'Optional: Zeiteinträge ab Datum (inklusiv, YYYY-MM-DD).',
                    ],
                    'work_date_to' => [
                        'type' => 'string',
                        'description' => 'Optional: Zeiteinträge bis Datum (inklusiv, YYYY-MM-DD).',
                    ],
                    'is_billed' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Filter nach Abrechnungsstatus.',
                    ],
                    'include_deleted' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Gelöschte Einträge einbeziehen. Default: false.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $team = Team::find((int) $teamId);
            if (!$team) {
                return ToolResult::error('TEAM_NOT_FOUND', 'Team nicht gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', $team->id)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team.');
            }

            $query = OrganizationTimeEntry::query()
                ->where('team_id', (int) $teamId)
                ->with(['user']);

            // Soft-Delete Handling
            if (!empty($arguments['include_deleted'])) {
                $query->withTrashed();
            }

            // User-Filter
            if (isset($arguments['user_id'])) {
                $query->where('user_id', (int) $arguments['user_id']);
            }

            // Kontext-Filter (direkt oder über Kaskade)
            if (isset($arguments['context_type']) && isset($arguments['context_id'])) {
                $query->forContext($arguments['context_type'], (int) $arguments['context_id']);
            } elseif (isset($arguments['context_type'])) {
                $query->where('context_type', $arguments['context_type']);
            }

            // Root-Kontext-Filter
            if (isset($arguments['root_context_type']) && isset($arguments['root_context_id'])) {
                $query->forRootContext($arguments['root_context_type'], (int) $arguments['root_context_id']);
            }

            // Datum-Filter
            if (isset($arguments['work_date_from'])) {
                $query->where('work_date', '>=', $arguments['work_date_from']);
            }
            if (isset($arguments['work_date_to'])) {
                $query->where('work_date', '<=', $arguments['work_date_to']);
            }

            // Abrechnungs-Filter
            if (array_key_exists('is_billed', $arguments) && $arguments['is_billed'] !== null) {
                $query->where('is_billed', (bool) $arguments['is_billed']);
            }

            // Standard-Operationen
            $this->applyStandardFilters($query, $arguments, ['team_id', 'user_id', 'is_billed', 'context_type', 'context_id', 'work_date']);
            $this->applyStandardSearch($query, $arguments, ['note']);
            $this->applyStandardSort($query, $arguments, ['work_date', 'minutes', 'created_at', 'id'], 'work_date', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);
            $entries = $result['data'];

            $items = $entries->map(function (OrganizationTimeEntry $entry) {
                return [
                    'id' => $entry->id,
                    'uuid' => $entry->uuid,
                    'user_id' => $entry->user_id,
                    'user_name' => $entry->user?->name,
                    'work_date' => $entry->work_date->format('Y-m-d'),
                    'minutes' => $entry->minutes,
                    'hours' => $entry->hours,
                    'formatted' => OrganizationTimeEntry::formatMinutes($entry->minutes),
                    'context_type' => $entry->context_type,
                    'context_id' => $entry->context_id,
                    'root_context_type' => $entry->root_context_type,
                    'root_context_id' => $entry->root_context_id,
                    'source_module' => $entry->source_module,
                    'note' => $entry->note,
                    'is_billed' => (bool) $entry->is_billed,
                    'rate_cents' => $entry->rate_cents,
                    'amount_cents' => $entry->amount_cents,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ];
            })->values()->toArray();

            $totalMinutes = $entries->sum('minutes');

            return ToolResult::success([
                'data' => $items,
                'pagination' => $result['pagination'] ?? null,
                'summary' => [
                    'count' => count($items),
                    'total_minutes' => $totalMinutes,
                    'total_formatted' => OrganizationTimeEntry::formatMinutes($totalMinutes),
                    'total_hours' => OrganizationTimeEntry::formatMinutesAsHours($totalMinutes),
                ],
                'team_id' => (int) $teamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Zeiteinträge: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'time_entries', 'list', 'time_tracking'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
