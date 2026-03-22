<?php

namespace Platform\Organization\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationContext;
use Platform\Organization\Models\OrganizationTimeEntry;
use Platform\Organization\Services\ContextTypeRegistry;
use Platform\Organization\Services\EntityTimeResolver;
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
        return 'GET /organization/time-entries/summary - Gibt Zusammenfassung/Summen der getrackten Zeit zurück: pro Kontext, User, Zeitraum, Entity oder kombiniert. Unterstützt cross-team Abfragen. Ideal für Auswertungen und Reports.';
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
                    'description' => 'Optional: Cross-Team Abfrage (Root-Team + alle Child-Teams). Default: true.',
                ],
                'group_by' => [
                    'type' => 'string',
                    'enum' => ['context', 'user', 'work_date', 'entity', 'source_module', 'team'],
                    'description' => 'Gruppierung der Zusammenfassung (ERFORDERLICH). "context" = pro Kontext (Task, Projekt etc.), "user" = pro Benutzer, "work_date" = pro Tag, "entity" = pro Organization Entity, "source_module" = pro Modul (planner, crm etc.), "team" = pro Team.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Filter nach Organization Entity-ID. Sammelt automatisch alle zugehörigen Kontexte.',
                ],
                'include_child_entities' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bei entity_id auch Child-Entities einbeziehen. Default: false.',
                ],
                'user_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Nur Einträge eines bestimmten Users.',
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
                    'description' => 'Optional: Array von Filtern. Jeder Filter: {"field": "...", "op": "eq|ne|gt|gte|lt|lte|like|in|not_in|is_null|is_not_null", "value": ...}. Erlaubte Felder: team_id, user_id, context_type, context_id, work_date (oder "date" als Alias), is_billed, source_module.',
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

    protected const ALLOWED_FILTER_FIELDS = [
        'team_id', 'user_id', 'context_type', 'context_id',
        'work_date', 'is_billed', 'source_module',
    ];

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
            if (!$groupBy || !in_array($groupBy, ['context', 'user', 'work_date', 'entity', 'source_module', 'team'])) {
                return ToolResult::error('VALIDATION_ERROR', 'group_by ist erforderlich. Erlaubte Werte: context, user, work_date, entity, source_module, team.');
            }

            // date_from/date_to als Aliase
            if (isset($arguments['date_from']) && !isset($arguments['work_date_from'])) {
                $arguments['work_date_from'] = $arguments['date_from'];
            }
            if (isset($arguments['date_to']) && !isset($arguments['work_date_to'])) {
                $arguments['work_date_to'] = $arguments['date_to'];
            }

            // Entity-basierter Filter
            if (isset($arguments['entity_id'])) {
                $entity = \Platform\Organization\Models\OrganizationEntity::find((int) $arguments['entity_id']);
                if (!$entity) {
                    return ToolResult::error('NOT_FOUND', 'Organization Entity nicht gefunden.');
                }
                $resolver = app(EntityTimeResolver::class);
                $includeChildren = (bool) ($arguments['include_child_entities'] ?? false);
                $query = $resolver->buildTimeEntryQuery($entity, $includeChildren)
                    ->whereIn('team_id', $teamIds);
            } else {
                $query = OrganizationTimeEntry::query()
                    ->whereIn('team_id', $teamIds);
            }

            // Kurzformen auflösen
            $contextType = isset($arguments['context_type'])
                ? (ContextTypeRegistry::resolve($arguments['context_type']) ?? $arguments['context_type'])
                : null;

            // Top-Level Filter anwenden
            if (isset($arguments['user_id'])) {
                $query->where('user_id', (int) $arguments['user_id']);
            }
            if ($contextType && isset($arguments['context_id'])) {
                $query->forContextKey($contextType, (int) $arguments['context_id']);
            } elseif ($contextType) {
                $query->where('context_type', $contextType);
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

            // filters-Array anwenden
            $this->applySummaryFilters($query, $arguments);

            // Gruppierung
            $groups = match ($groupBy) {
                'context' => $this->groupByContext($query),
                'user' => $this->groupByUser($query),
                'work_date' => $this->groupByWorkDate($query),
                'entity' => $this->groupByEntity($query, $teamIds),
                'source_module' => $this->groupBySourceModule($query),
                'team' => $this->groupByTeam($query),
            };

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

    /**
     * Gruppiert nach Organization Entity.
     * Baut eine Reverse-Map: (context_type, context_id) → Entity
     */
    protected function groupByEntity($query, array $teamIds): array
    {
        $entries = (clone $query)->get();

        if ($entries->isEmpty()) {
            return [];
        }

        // Sammle alle context_type/context_id Paare
        $contextPairs = $entries->map(fn($e) => $e->context_type . ':' . $e->context_id)->unique()->values();

        // Lade alle OrganizationContexts für diese contextable Paare
        $contexts = OrganizationContext::query()
            ->where('is_active', true)
            ->with('organizationEntity')
            ->get();

        // Baue Reverse-Map: "contextable_type:contextable_id" → Entity
        $contextToEntity = [];
        foreach ($contexts as $ctx) {
            $key = $ctx->contextable_type . ':' . $ctx->contextable_id;
            if ($ctx->organizationEntity) {
                $contextToEntity[$key] = $ctx->organizationEntity;
            }

            // Auch Children-Relations berücksichtigen
            if (!empty($ctx->include_children_relations) && $ctx->contextable_type && $ctx->contextable_id) {
                $this->mapChildRelationsToEntity($ctx, $contextToEntity);
            }
        }

        // Gruppiere Entries nach Entity
        $grouped = [];
        $unlinkedMinutes = 0;
        $unlinkedCount = 0;

        foreach ($entries as $entry) {
            $key = $entry->context_type . ':' . $entry->context_id;
            $entity = $contextToEntity[$key] ?? null;

            if ($entity) {
                $entityId = $entity->id;
                if (!isset($grouped[$entityId])) {
                    $grouped[$entityId] = [
                        'entity_id' => $entity->id,
                        'entity_name' => $entity->name,
                        'entity_type' => $entity->type?->name ?? null,
                        'entry_count' => 0,
                        'total_minutes' => 0,
                    ];
                }
                $grouped[$entityId]['entry_count']++;
                $grouped[$entityId]['total_minutes'] += $entry->minutes;
            } else {
                $unlinkedMinutes += $entry->minutes;
                $unlinkedCount++;
            }
        }

        $result = collect($grouped)->map(function ($g) {
            return [
                ...$g,
                'total_formatted' => OrganizationTimeEntry::formatMinutes($g['total_minutes']),
                'total_hours' => OrganizationTimeEntry::formatMinutesAsHours($g['total_minutes']),
            ];
        })->sortByDesc('total_minutes')->values()->toArray();

        if ($unlinkedCount > 0) {
            $result[] = [
                'entity_id' => null,
                'entity_name' => '(Nicht verknüpft)',
                'entity_type' => null,
                'entry_count' => $unlinkedCount,
                'total_minutes' => $unlinkedMinutes,
                'total_formatted' => OrganizationTimeEntry::formatMinutes($unlinkedMinutes),
                'total_hours' => OrganizationTimeEntry::formatMinutesAsHours($unlinkedMinutes),
            ];
        }

        return $result;
    }

    /**
     * Mappt Child-Relations eines OrganizationContext auf die Entity.
     */
    protected function mapChildRelationsToEntity(OrganizationContext $ctx, array &$map): void
    {
        if (!class_exists($ctx->contextable_type)) {
            return;
        }

        $model = $ctx->contextable_type::find($ctx->contextable_id);
        if (!$model || !$ctx->organizationEntity) {
            return;
        }

        foreach ($ctx->include_children_relations as $relationPath) {
            $this->resolveRelationPathForMapping($model, $relationPath, $ctx->organizationEntity, $map);
        }
    }

    protected function resolveRelationPathForMapping($model, string $path, $entity, array &$map): void
    {
        $segments = explode('.', $path);
        $currentModels = collect([$model]);

        foreach ($segments as $segment) {
            $nextModels = collect();
            foreach ($currentModels as $currentModel) {
                if (!method_exists($currentModel, $segment)) {
                    continue;
                }
                $related = $currentModel->{$segment};
                if ($related instanceof \Illuminate\Database\Eloquent\Collection) {
                    $nextModels = $nextModels->merge($related);
                } elseif ($related instanceof \Illuminate\Database\Eloquent\Model) {
                    $nextModels->push($related);
                }
            }
            $currentModels = $nextModels;
        }

        foreach ($currentModels as $leafModel) {
            $key = get_class($leafModel) . ':' . $leafModel->id;
            $map[$key] = $entity;
        }
    }

    protected function groupBySourceModule($query): array
    {
        $entries = (clone $query)->get();

        $grouped = $entries->groupBy(function (OrganizationTimeEntry $entry) {
            return $entry->source_module ?? '_none';
        });

        return $grouped->map(function ($entries, $module) {
            $totalMinutes = $entries->sum('minutes');
            $moduleTitle = $module === '_none' ? '(Freie Zeiterfassung)' : ucfirst($module);

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

            if ($field === 'date') {
                $field = 'work_date';
            }

            if (!$field || !in_array($field, self::ALLOWED_FILTER_FIELDS, true)) {
                continue;
            }
            if (!in_array($op, self::ALLOWED_FILTER_OPS, true)) {
                continue;
            }

            // context_type Kurzformen auflösen
            if ($field === 'context_type' && $value !== null) {
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
