<?php

namespace Platform\Organization\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\ContextTypeRegistry;
use Platform\Organization\Services\TimeContextResolver;
use Platform\Organization\Tools\Concerns\ResolvesTimeEntryTeamScope;

class SummarizeTimeEntriesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesTimeEntryTeamScope;

    public function getName(): string
    {
        return 'organization.time_entries.SUMMARY';
    }

    public function getDescription(): string
    {
        return 'GET /organization/time-entries/summary - Gibt Zusammenfassung/Summen der getrackten Zeit zurück: pro Kontext, User, Zeitraum oder kombiniert. Unterstützt cross-team Abfragen: Im Parent-Team werden automatisch alle Child-Teams einbezogen (wie in der UI). Ideal für Auswertungen und Reports.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'cross_team' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Cross-Team Abfrage (Root-Team + alle Child-Teams). Default: true. Bei false werden nur Einträge des angegebenen Teams zusammengefasst.',
                ],
                'group_by' => [
                    'type' => 'string',
                    'enum' => ['context', 'user', 'work_date', 'root_context', 'source_module', 'team'],
                    'description' => 'Gruppierung der Zusammenfassung (ERFORDERLICH). "context" = pro Kontext (Task, Projekt etc.), "user" = pro Benutzer, "work_date" = pro Tag, "root_context" = pro Root-Kontext (z.B. Projekt), "source_module" = pro Modul (planner, crm etc.), "team" = pro Team (sinnvoll bei cross-team Abfragen).',
                ],
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Einträge eines bestimmten Users. Funktioniert auch cross-team.',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Kontext-Typ. Kurzformen: "project", "task", "ticket", "company" (oder vollqualifizierter Klassenname).',
                    'enum' => ['project', 'task', 'ticket', 'company'],
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach Kontext-ID (zusammen mit context_type).',
                ],
                'root_context_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Root-Kontext-Typ. Kurzformen: "project", "task", "ticket", "company" (oder vollqualifizierter Klassenname).',
                    'enum' => ['project', 'task', 'ticket', 'company'],
                ],
                'root_context_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach Root-Kontext-ID.',
                ],
                'date_from' => [
                    'type' => 'string',
                    'description' => 'Optional: Ab Datum (inklusiv, YYYY-MM-DD). Alias für work_date_from.',
                ],
                'date_to' => [
                    'type' => 'string',
                    'description' => 'Optional: Bis Datum (inklusiv, YYYY-MM-DD). Alias für work_date_to.',
                ],
                'work_date_from' => [
                    'type' => 'string',
                    'description' => 'Optional: Ab Datum (inklusiv, YYYY-MM-DD).',
                ],
                'work_date_to' => [
                    'type' => 'string',
                    'description' => 'Optional: Bis Datum (inklusiv, YYYY-MM-DD).',
                ],
                'is_billed' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Nur abgerechnete/nicht abgerechnete Einträge.',
                ],
                'filters' => [
                    'type' => 'array',
                    'description' => 'Optional: Array von Filtern. Jeder Filter: {"field": "...", "op": "eq|ne|gt|gte|lt|lte|like|in|not_in|is_null|is_not_null", "value": ...}. Erlaubte Felder: team_id, user_id, context_type, context_id, root_context_type, root_context_id, work_date, is_billed, source_module.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'op' => ['type' => 'string', 'enum' => ['eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'like', 'in', 'not_in', 'is_null', 'is_not_null']],
                            'value' => [],
                        ],
                        'required' => ['field', 'op'],
                    ],
                ],
            ],
            'required' => ['group_by'],
        ];
    }

    /**
     * Erlaubte Felder für das filters-Array.
     */
    protected const ALLOWED_FILTER_FIELDS = [
        'team_id', 'user_id', 'context_type', 'context_id',
        'root_context_type', 'root_context_id', 'work_date',
        'is_billed', 'source_module',
    ];

    /**
     * Erlaubte Operatoren für das filters-Array.
     */
    protected const ALLOWED_FILTER_OPS = [
        'eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'like', 'in', 'not_in', 'is_null', 'is_not_null',
    ];

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $scope = $this->resolveTimeEntryTeamScope($arguments, $context);

            if ($scope['error']) {
                return $scope['error'];
            }

            $teamIds = $scope['team_ids'];
            $isCrossTeam = $scope['is_cross_team'];

            $groupBy = $arguments['group_by'] ?? null;
            if (!$groupBy || !in_array($groupBy, ['context', 'user', 'work_date', 'root_context', 'source_module', 'team'])) {
                return ToolResult::error('VALIDATION_ERROR', 'group_by ist erforderlich. Erlaubte Werte: context, user, work_date, root_context, source_module, team.');
            }

            // date_from/date_to als Aliase für work_date_from/work_date_to
            if (isset($arguments['date_from']) && !isset($arguments['work_date_from'])) {
                $arguments['work_date_from'] = $arguments['date_from'];
            }
            if (isset($arguments['date_to']) && !isset($arguments['work_date_to'])) {
                $arguments['work_date_to'] = $arguments['date_to'];
            }

            $query = OrganizationTimeEntry::query()
                ->whereIn('team_id', $teamIds);

            // Kurzformen auflösen
            $contextType = isset($arguments['context_type'])
                ? (ContextTypeRegistry::resolve($arguments['context_type']) ?? $arguments['context_type'])
                : null;
            $rootContextType = isset($arguments['root_context_type'])
                ? (ContextTypeRegistry::resolve($arguments['root_context_type']) ?? $arguments['root_context_type'])
                : null;

            // Top-Level Filter anwenden
            if (isset($arguments['user_id'])) {
                $query->where('user_id', (int) $arguments['user_id']);
            }
            if ($contextType && isset($arguments['context_id'])) {
                $query->forContext($contextType, (int) $arguments['context_id']);
            } elseif ($contextType) {
                $query->where('context_type', $contextType);
            }
            if ($rootContextType && isset($arguments['root_context_id'])) {
                $query->forRootContext($rootContextType, (int) $arguments['root_context_id']);
            }
            if (isset($arguments['work_date_from'])) {
                $query->where('work_date', '>=', $arguments['work_date_from']);
            }
            if (isset($arguments['work_date_to'])) {
                $query->where('work_date', '<=', $arguments['work_date_to']);
            }
            if (array_key_exists('is_billed', $arguments) && $arguments['is_billed'] !== null) {
                $query->where('is_billed', (bool) $arguments['is_billed']);
            }

            // filters-Array anwenden (Parität mit GET)
            $this->applySummaryFilters($query, $arguments);

            // Gruppierung
            $groups = match ($groupBy) {
                'context' => $this->groupByContext($query),
                'user' => $this->groupByUser($query),
                'work_date' => $this->groupByWorkDate($query),
                'root_context' => $this->groupByRootContext($query),
                'source_module' => $this->groupBySourceModule($query),
                'team' => $this->groupByTeam($query),
            };

            // Gesamtsumme berechnen
            $totalMinutes = collect($groups)->sum('total_minutes');

            return ToolResult::success([
                'group_by' => $groupBy,
                'groups' => $groups,
                'total' => [
                    'groups_count' => count($groups),
                    'total_minutes' => $totalMinutes,
                    'total_formatted' => OrganizationTimeEntry::formatMinutes($totalMinutes),
                    'total_hours' => OrganizationTimeEntry::formatMinutesAsHours($totalMinutes),
                ],
                'team_ids' => $teamIds,
                'cross_team' => $isCrossTeam,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Zeitauswertung: ' . $e->getMessage());
        }
    }

    protected function groupByContext($query): array
    {
        $results = (clone $query)
            ->select('context_type', 'context_id', DB::raw('SUM(minutes) as total_minutes'), DB::raw('COUNT(*) as entry_count'))
            ->groupBy('context_type', 'context_id')
            ->orderByDesc('total_minutes')
            ->limit(100)
            ->get();

        $resolver = app(TimeContextResolver::class);

        return $results->map(function ($row) use ($resolver) {
            $label = null;
            if ($row->context_type && $row->context_id) {
                $label = $resolver->resolveLabel($row->context_type, $row->context_id);
            }
            return [
                'context_type' => $row->context_type,
                'context_id' => $row->context_id,
                'context_label' => $label ?? '(Freie Zeiterfassung)',
                'entry_count' => (int) $row->entry_count,
                'total_minutes' => (int) $row->total_minutes,
                'total_formatted' => OrganizationTimeEntry::formatMinutes((int) $row->total_minutes),
                'total_hours' => OrganizationTimeEntry::formatMinutesAsHours((int) $row->total_minutes),
            ];
        })->values()->toArray();
    }

    protected function groupByUser($query): array
    {
        $results = (clone $query)
            ->select('user_id', DB::raw('SUM(minutes) as total_minutes'), DB::raw('COUNT(*) as entry_count'))
            ->groupBy('user_id')
            ->orderByDesc('total_minutes')
            ->limit(100)
            ->get();

        return $results->map(function ($row) {
            $user = \Platform\Core\Models\User::find($row->user_id);
            return [
                'user_id' => $row->user_id,
                'user_name' => $user?->name ?? '(Unbekannt)',
                'entry_count' => (int) $row->entry_count,
                'total_minutes' => (int) $row->total_minutes,
                'total_formatted' => OrganizationTimeEntry::formatMinutes((int) $row->total_minutes),
                'total_hours' => OrganizationTimeEntry::formatMinutesAsHours((int) $row->total_minutes),
            ];
        })->values()->toArray();
    }

    protected function groupByWorkDate($query): array
    {
        $results = (clone $query)
            ->select('work_date', DB::raw('SUM(minutes) as total_minutes'), DB::raw('COUNT(*) as entry_count'))
            ->groupBy('work_date')
            ->orderBy('work_date', 'desc')
            ->limit(100)
            ->get();

        return $results->map(function ($row) {
            $date = $row->work_date instanceof \Carbon\Carbon ? $row->work_date->format('Y-m-d') : (string) $row->work_date;
            return [
                'work_date' => $date,
                'entry_count' => (int) $row->entry_count,
                'total_minutes' => (int) $row->total_minutes,
                'total_formatted' => OrganizationTimeEntry::formatMinutes((int) $row->total_minutes),
                'total_hours' => OrganizationTimeEntry::formatMinutesAsHours((int) $row->total_minutes),
            ];
        })->values()->toArray();
    }

    protected function groupByRootContext($query): array
    {
        $results = (clone $query)
            ->select('root_context_type', 'root_context_id', DB::raw('SUM(minutes) as total_minutes'), DB::raw('COUNT(*) as entry_count'))
            ->groupBy('root_context_type', 'root_context_id')
            ->orderByDesc('total_minutes')
            ->limit(100)
            ->get();

        $resolver = app(TimeContextResolver::class);

        return $results->map(function ($row) use ($resolver) {
            $label = null;
            if ($row->root_context_type && $row->root_context_id) {
                $label = $resolver->resolveRootName($row->root_context_type, $row->root_context_id);
            }
            return [
                'root_context_type' => $row->root_context_type,
                'root_context_id' => $row->root_context_id,
                'root_context_label' => $label ?? '(Ohne Root-Kontext)',
                'entry_count' => (int) $row->entry_count,
                'total_minutes' => (int) $row->total_minutes,
                'total_formatted' => OrganizationTimeEntry::formatMinutes((int) $row->total_minutes),
                'total_hours' => OrganizationTimeEntry::formatMinutesAsHours((int) $row->total_minutes),
            ];
        })->values()->toArray();
    }

    protected function groupBySourceModule($query): array
    {
        // Gruppierung nach Modul (aus context_type abgeleitet)
        $entries = (clone $query)->get();

        $grouped = $entries->groupBy(function (OrganizationTimeEntry $entry) {
            return $entry->source_module ?? '_none';
        });

        return $grouped->map(function ($entries, $module) {
            $totalMinutes = $entries->sum('minutes');
            $moduleTitle = $module === '_none' ? '(Freie Zeiterfassung)' : ucfirst($module);

            // Versuche Modul-Titel aus Registry
            if ($module !== '_none') {
                $mod = \Platform\Core\PlatformCore::getModule($module);
                if ($mod && isset($mod['title'])) {
                    $moduleTitle = $mod['title'];
                }
            }

            return [
                'source_module' => $module === '_none' ? null : $module,
                'source_module_title' => $moduleTitle,
                'entry_count' => $entries->count(),
                'total_minutes' => $totalMinutes,
                'total_formatted' => OrganizationTimeEntry::formatMinutes($totalMinutes),
                'total_hours' => OrganizationTimeEntry::formatMinutesAsHours($totalMinutes),
            ];
        })->sortByDesc('total_minutes')->values()->toArray();
    }

    protected function groupByTeam($query): array
    {
        $results = (clone $query)
            ->select('team_id', DB::raw('SUM(minutes) as total_minutes'), DB::raw('COUNT(*) as entry_count'))
            ->groupBy('team_id')
            ->orderByDesc('total_minutes')
            ->limit(100)
            ->get();

        return $results->map(function ($row) {
            $team = Team::find($row->team_id);
            return [
                'team_id' => $row->team_id,
                'team_name' => $team?->name ?? '(Unbekannt)',
                'entry_count' => (int) $row->entry_count,
                'total_minutes' => (int) $row->total_minutes,
                'total_formatted' => OrganizationTimeEntry::formatMinutes((int) $row->total_minutes),
                'total_hours' => OrganizationTimeEntry::formatMinutesAsHours((int) $row->total_minutes),
            ];
        })->values()->toArray();
    }

    /**
     * Wendet das filters-Array auf die Query an.
     * Unterstützt dieselben Filter-Felder und Operatoren wie GET.
     */
    protected function applySummaryFilters($query, array $arguments): void
    {
        $filters = $arguments['filters'] ?? null;

        if (!is_array($filters) || empty($filters)) {
            return;
        }

        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $field = $filter['field'] ?? null;
            $op = $filter['op'] ?? 'eq';
            $value = $filter['value'] ?? null;

            if (!$field || !in_array($field, self::ALLOWED_FILTER_FIELDS, true)) {
                continue;
            }
            if (!in_array($op, self::ALLOWED_FILTER_OPS, true)) {
                continue;
            }

            // context_type Kurzformen auflösen
            if (in_array($field, ['context_type', 'root_context_type'], true) && $value !== null) {
                $resolved = ContextTypeRegistry::resolve($value);
                if ($resolved) {
                    $value = $resolved;
                }
            }

            match ($op) {
                'eq' => $query->where($field, '=', $value),
                'ne' => $query->where($field, '!=', $value),
                'gt' => $query->where($field, '>', $value),
                'gte' => $query->where($field, '>=', $value),
                'lt' => $query->where($field, '<', $value),
                'lte' => $query->where($field, '<=', $value),
                'like' => $query->where($field, 'LIKE', $value),
                'in' => $query->whereIn($field, is_array($value) ? $value : [$value]),
                'not_in' => $query->whereNotIn($field, is_array($value) ? $value : [$value]),
                'is_null' => $query->whereNull($field),
                'is_not_null' => $query->whereNotNull($field),
            };
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'read',
            'tags' => ['organization', 'time_entries', 'summary', 'report', 'time_tracking'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
