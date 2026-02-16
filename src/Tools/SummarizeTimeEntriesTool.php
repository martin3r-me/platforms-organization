<?php

namespace Platform\Organization\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\TimeContextResolver;

class SummarizeTimeEntriesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.time_entries.SUMMARY';
    }

    public function getDescription(): string
    {
        return 'GET /organization/time-entries/summary - Gibt Zusammenfassung/Summen der getrackten Zeit zurück: pro Kontext, User, Zeitraum oder kombiniert. Ideal für Auswertungen und Reports.';
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
                'group_by' => [
                    'type' => 'string',
                    'enum' => ['context', 'user', 'work_date', 'root_context', 'source_module'],
                    'description' => 'Gruppierung der Zusammenfassung (ERFORDERLICH). "context" = pro Kontext (Task, Projekt etc.), "user" = pro Benutzer, "work_date" = pro Tag, "root_context" = pro Root-Kontext (z.B. Projekt), "source_module" = pro Modul (planner, crm etc.).',
                ],
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Einträge eines bestimmten Users.',
                ],
                'context_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Kontext-Typ.',
                ],
                'context_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach Kontext-ID (zusammen mit context_type).',
                ],
                'root_context_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Filter nach Root-Kontext-Typ.',
                ],
                'root_context_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach Root-Kontext-ID.',
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
            ],
            'required' => ['group_by'],
        ];
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

            $groupBy = $arguments['group_by'] ?? null;
            if (!$groupBy || !in_array($groupBy, ['context', 'user', 'work_date', 'root_context', 'source_module'])) {
                return ToolResult::error('VALIDATION_ERROR', 'group_by ist erforderlich. Erlaubte Werte: context, user, work_date, root_context, source_module.');
            }

            $query = OrganizationTimeEntry::query()
                ->where('team_id', (int) $teamId);

            // Filter anwenden
            if (isset($arguments['user_id'])) {
                $query->where('user_id', (int) $arguments['user_id']);
            }
            if (isset($arguments['context_type']) && isset($arguments['context_id'])) {
                $query->forContext($arguments['context_type'], (int) $arguments['context_id']);
            } elseif (isset($arguments['context_type'])) {
                $query->where('context_type', $arguments['context_type']);
            }
            if (isset($arguments['root_context_type']) && isset($arguments['root_context_id'])) {
                $query->forRootContext($arguments['root_context_type'], (int) $arguments['root_context_id']);
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

            // Gruppierung
            $groups = match ($groupBy) {
                'context' => $this->groupByContext($query),
                'user' => $this->groupByUser($query),
                'work_date' => $this->groupByWorkDate($query),
                'root_context' => $this->groupByRootContext($query),
                'source_module' => $this->groupBySourceModule($query),
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
                'team_id' => (int) $teamId,
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
