<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntitySnapshot;
use Platform\Organization\Models\OrganizationTeamSnapshot;

class Mindmap extends Component
{
    public OrganizationEntity $entity;

    public ?string $snapshotDate = null;

    public function updatedSnapshotDate(): void
    {
        unset($this->graphData);
        $this->dispatch('graph-data-updated', data: $this->graphData);
    }

    /**
     * Maximal unterscheidbare Farben — werden der Reihe nach vergeben.
     * Kein Hardcoding von Gruppen/Link-Typen, alles dynamisch.
     */
    protected array $colorPool = [
        '#3B82F6', '#8B5CF6', '#F59E0B', '#10B981', '#EF4444',
        '#6366F1', '#EC4899', '#22D3EE', '#FB923C', '#84CC16',
        '#F43F5E', '#A855F7', '#14B8A6', '#FACC15', '#06B6D4',
        '#F97316', '#E879F9', '#34D399', '#FCA5A5', '#7DD3FC',
    ];

    protected array $assignedColors = [];
    protected int $colorPoolIndex = 0;

    protected function colorFor(string $key): string
    {
        if (!isset($this->assignedColors[$key])) {
            $this->assignedColors[$key] = $this->colorPool[$this->colorPoolIndex % count($this->colorPool)];
            $this->colorPoolIndex++;
        }

        return $this->assignedColors[$key];
    }

    public function mount(OrganizationEntity $entity)
    {
        $this->entity = $entity->load(['type.group']);
    }

    #[Computed]
    public function availableDates(): array
    {
        return OrganizationTeamSnapshot::forTeam($this->entity->team_id)
            ->orderBy('snapshot_date')
            ->orderBy('snapshot_period')
            ->get(['snapshot_date', 'snapshot_period'])
            ->map(function ($s) {
                $date = $s->snapshot_date->toDateString();
                $period = $s->snapshot_period;
                return [
                    'key'    => $date . '_' . $period,
                    'date'   => $date,
                    'period' => $period,
                    'label'  => $date . ' · ' . ($period === 'morning' ? 'AM' : 'PM'),
                ];
            })
            ->values()
            ->all();
    }

    #[Computed]
    public function graphData(): array
    {
        if ($this->snapshotDate) {
            return $this->graphDataFromSnapshot($this->snapshotDate);
        }

        return $this->graphDataLive();
    }

    protected function graphDataFromSnapshot(string $key): array
    {
        // key format: "YYYY-MM-DD_period"
        [$date, $period] = array_pad(explode('_', $key, 2), 2, null);

        $query = OrganizationTeamSnapshot::forTeam($this->entity->team_id)
            ->where('snapshot_date', $date);

        if ($period) {
            $query->where('snapshot_period', $period);
        } else {
            $query->orderByDesc('snapshot_period');
        }

        $snapshot = $query->first();

        if (!$snapshot || !$snapshot->structure) {
            return ['nodes' => [], 'links' => [], 'categories' => [], 'entityGroups' => []];
        }

        $structure = $snapshot->structure;
        $entities = collect($structure['entities'] ?? []);
        $relationships = $structure['relationships'] ?? [];
        $entityLinks = collect($structure['entity_links'] ?? []);

        $nodes = [];
        $links = [];

        $depthMap = $this->buildDepthMapFromStructure($entities);
        $parentIds = $entities->pluck('parent_entity_id')->filter()->unique()->flip();

        // Load matching entity metric snapshots for this date
        $entityIds = $entities->pluck('id');
        $metricSnapshots = OrganizationEntitySnapshot::query()
            ->whereIn('entity_id', $entityIds)
            ->where('snapshot_date', $date)
            ->orderByDesc('snapshot_period')
            ->get()
            ->unique('entity_id')
            ->keyBy('entity_id');

        foreach ($entities as $e) {
            $e = (object) $e;
            $groupName = $e->group_name ?? 'Sonstige';
            $isCenter = $e->id === $this->entity->id;
            $snap = $metricSnapshots[$e->id] ?? null;
            $metrics = $snap?->metrics ?? [];
            $depth = $depthMap[$e->id] ?? 0;

            $baseVal = match(true) {
                $isCenter => 25,
                $depth === 0 => 12,
                $depth === 1 => 8,
                $depth === 2 => 5,
                default => 4,
            };

            $baseColor = $this->colorFor($groupName);
            $color = $this->lightenByDepth($baseColor, $depth);

            $nodes[] = [
                'id'       => 'e' . $e->id,
                'name'     => $e->name,
                'group'    => $groupName,
                'category' => 'entity',
                'parentId' => $e->parent_entity_id ? 'e' . $e->parent_entity_id : null,
                'color'    => $color,
                'val'      => $baseVal,
                'depth'    => $depth,
                'isSun'    => $depth === 0 && $parentIds->has($e->id),
                'metrics'  => [
                    'items_total'   => $metrics['items_total'] ?? 0,
                    'items_done'    => $metrics['items_done'] ?? 0,
                    'links_count'   => $metrics['links_count'] ?? 0,
                    'time_h'        => round(($metrics['time_total_minutes'] ?? 0) / 60, 1),
                    'time_billed_h' => round(($metrics['time_billed_minutes'] ?? 0) / 60, 1),
                ],
            ];

            if ($e->parent_entity_id) {
                $links[] = [
                    'source' => 'e' . $e->parent_entity_id,
                    'target' => 'e' . $e->id,
                    'color'  => 'rgba(156,163,175,0.35)',
                    'width'  => 1,
                    'ltype'  => 'hierarchy',
                ];
            }
        }

        // Relationships from snapshot
        $relationColors = [
            'manages'             => '#8B5CF6',
            'is_part_of'          => '#6366F1',
            'contains'            => '#3B82F6',
            'works_for'           => '#10B981',
            'provides_service_to' => '#F97316',
        ];

        foreach ($relationships as $rel) {
            $rel = (object) $rel;
            $code = $rel->relation_type_code ?? '';
            $links[] = [
                'source'    => 'e' . $rel->from_entity_id,
                'target'    => 'e' . $rel->to_entity_id,
                'color'     => $relationColors[$code] ?? '#F59E0B',
                'width'     => 2,
                'ltype'     => 'relation',
                'rel_label' => $rel->relation_type_name ?? $code,
            ];
        }

        // Entity links from snapshot (names already resolved)
        $linkedNodes = [];
        $groupedLinks = $entityLinks->groupBy('linkable_type');

        foreach ($groupedLinks as $morphType => $typeLinks) {
            $typeColor = $this->colorFor('link:' . $morphType);
            $typeName = $this->humanMorphType($morphType);

            foreach ($typeLinks as $link) {
                $link = (object) $link;
                $nodeId = $morphType . '-' . $link->linkable_id;

                if (!isset($linkedNodes[$nodeId])) {
                    $linkedNodes[$nodeId] = true;
                    $nodes[] = [
                        'id'       => $nodeId,
                        'name'     => $link->linkable_name ?? "#{$link->linkable_id}",
                        'color'    => $typeColor,
                        'val'      => 6,
                        'type'     => $typeName,
                        'category' => $morphType,
                    ];
                }

                $links[] = [
                    'source' => 'e' . $link->entity_id,
                    'target' => $nodeId,
                    'color'  => $typeColor,
                    'width'  => 1,
                    'ltype'  => 'entity_link',
                ];
            }
        }

        return $this->buildCategoriesAndGroups($nodes, $links);
    }

    protected function buildDepthMapFromStructure($entities): array
    {
        $parentMap = [];
        foreach ($entities as $e) {
            $e = (object) $e;
            $parentMap[$e->id] = $e->parent_entity_id;
        }

        $depths = [];
        foreach ($entities as $e) {
            $e = (object) $e;
            $depth = 0;
            $current = $e->id;
            $visited = [];
            while (isset($parentMap[$current]) && $parentMap[$current] !== null) {
                if (in_array($current, $visited)) break;
                $visited[] = $current;
                $current = $parentMap[$current];
                $depth++;
            }
            $depths[$e->id] = $depth;
        }

        return $depths;
    }

    protected function graphDataLive(): array
    {
        $entities = OrganizationEntity::forTeam($this->entity->team_id)
            ->active()
            ->with(['type.group'])
            ->get();

        $nodes = [];
        $links = [];

        // Build depth map and parent set from hierarchy
        $depthMap = $this->buildDepthMap($entities);
        $parentIds = $entities->pluck('parent_entity_id')->filter()->unique()->flip();

        // Latest snapshots
        $latestSnapshots = OrganizationEntitySnapshot::query()
            ->whereIn('entity_id', $entities->pluck('id'))
            ->where('snapshot_date', '>=', now()->subDays(3))
            ->orderByDesc('snapshot_date')
            ->orderByDesc('snapshot_period')
            ->get()
            ->unique('entity_id')
            ->keyBy('entity_id');

        foreach ($entities as $e) {
            $groupName = $e->type?->group?->name ?? 'Sonstige';
            $isCenter = $e->id === $this->entity->id;
            $snap = $latestSnapshots[$e->id] ?? null;
            $metrics = $snap?->metrics ?? [];
            $depth = $depthMap[$e->id] ?? 0;

            // Scale val by depth: root=12, depth1=8, depth2=5, depth3+=4
            $baseVal = match(true) {
                $isCenter => 25,
                $depth === 0 => 12,
                $depth === 1 => 8,
                $depth === 2 => 5,
                default => 4,
            };

            // Lighten color by depth
            $baseColor = $this->colorFor($groupName);
            $color = $this->lightenByDepth($baseColor, $depth);

            $nodes[] = [
                'id'       => 'e' . $e->id,
                'name'     => $e->name,
                'group'    => $groupName,
                'category' => 'entity',
                'parentId' => $e->parent_entity_id ? 'e' . $e->parent_entity_id : null,
                'color'    => $color,
                'val'      => $baseVal,
                'depth'    => $depth,
                'isSun'    => $depth === 0 && $parentIds->has($e->id),
                'metrics'  => [
                    'items_total'   => $metrics['items_total'] ?? 0,
                    'items_done'    => $metrics['items_done'] ?? 0,
                    'links_count'   => $metrics['links_count'] ?? 0,
                    'time_h'        => round(($metrics['time_total_minutes'] ?? 0) / 60, 1),
                    'time_billed_h' => round(($metrics['time_billed_minutes'] ?? 0) / 60, 1),
                ],
            ];

            if ($e->parent_entity_id) {
                $links[] = [
                    'source' => 'e' . $e->parent_entity_id,
                    'target' => 'e' . $e->id,
                    'color'  => 'rgba(156,163,175,0.35)',
                    'width'  => 1,
                    'ltype'  => 'hierarchy',
                ];
            }
        }

        // Relationships
        $entityIds = $entities->pluck('id');
        $relationships = OrganizationEntityRelationship::query()
            ->where(function ($q) use ($entityIds) {
                $q->whereIn('from_entity_id', $entityIds)
                  ->whereIn('to_entity_id', $entityIds);
            })
            ->with('relationType')
            ->get();

        $relationColors = [
            'manages'             => '#8B5CF6',
            'is_part_of'          => '#6366F1',
            'contains'            => '#3B82F6',
            'works_for'           => '#10B981',
            'provides_service_to' => '#F97316',
        ];

        foreach ($relationships as $rel) {
            $code = $rel->relationType?->code ?? '';
            $links[] = [
                'source'    => 'e' . $rel->from_entity_id,
                'target'    => 'e' . $rel->to_entity_id,
                'color'     => $relationColors[$code] ?? '#F59E0B',
                'width'     => 2,
                'ltype'     => 'relation',
                'rel_label' => $rel->relationType?->name ?? $code,
            ];
        }

        // EntityLinks - dynamisch, egal welcher Morph-Type kommt
        $entityLinks = OrganizationEntityLink::query()
            ->whereIn('entity_id', $entityIds)
            ->where('team_id', $this->entity->team_id)
            ->get();

        $linkedNodes = [];
        $grouped = $entityLinks->groupBy('linkable_type');

        foreach ($grouped as $morphType => $typeLinks) {
            $ids = $typeLinks->pluck('linkable_id')->unique()->values()->all();
            $modelClass = Relation::getMorphedModel($morphType) ?? $morphType;
            $labelMap = [];

            if (class_exists($modelClass)) {
                $table = (new $modelClass)->getTable();
                $columns = collect(DB::getSchemaBuilder()->getColumnListing($table));
                $nameCol = $columns->first(fn ($c) => in_array($c, ['name', 'title', 'subject', 'label']));
                $labelExpr = $nameCol
                    ? DB::raw("COALESCE({$nameCol}, CONCAT('#', id)) as label")
                    : DB::raw("CONCAT('#', id) as label");
                $query = DB::table($table)->whereIn('id', $ids);
                if ($columns->contains('deleted_at')) {
                    $query->whereNull('deleted_at');
                }
                $rows = $query->select('id', $labelExpr)->get();
                foreach ($rows as $row) {
                    $labelMap[$row->id] = $row->label;
                }
            }

            // Farbe aus gemeinsamem Pool — keine Duplikate mit Entity-Gruppen
            $typeColor = $this->colorFor('link:' . $morphType);
            $typeName = $this->humanMorphType($morphType);

            foreach ($typeLinks as $link) {
                // Skip if row doesn't exist (deleted/missing)
                if (class_exists($modelClass) && !isset($labelMap[$link->linkable_id])) {
                    continue;
                }

                $nodeId = $morphType . '-' . $link->linkable_id;

                if (!isset($linkedNodes[$nodeId])) {
                    $linkedNodes[$nodeId] = true;
                    $nodes[] = [
                        'id'       => $nodeId,
                        'name'     => $labelMap[$link->linkable_id] ?? "#{$link->linkable_id}",
                        'color'    => $typeColor,
                        'val'      => 6,
                        'type'     => $typeName,
                        'category' => $morphType,
                    ];
                }

                $links[] = [
                    'source' => 'e' . $link->entity_id,
                    'target' => $nodeId,
                    'color'  => $typeColor,
                    'width'  => 1,
                    'ltype'  => 'entity_link',
                ];
            }
        }

        return $this->buildCategoriesAndGroups($nodes, $links);
    }

    protected function buildCategoriesAndGroups(array $nodes, array $links): array
    {
        $categories = [];
        foreach ($nodes as $n) {
            $cat = $n['category'] ?? 'entity';
            if (!isset($categories[$cat])) {
                $categories[$cat] = [
                    'label' => $cat === 'entity' ? 'Entities' : $this->humanMorphType($cat),
                    'count' => 0,
                    'color' => $n['color'],
                ];
            }
            $categories[$cat]['count']++;
        }

        $entityGroups = [];
        foreach ($nodes as $n) {
            if (($n['category'] ?? '') !== 'entity') continue;
            $g = $n['group'] ?? 'Sonstige';
            if (!isset($entityGroups[$g])) {
                $entityGroups[$g] = ['count' => 0, 'color' => $this->colorFor($g)];
            }
            $entityGroups[$g]['count']++;
        }

        return compact('nodes', 'links', 'categories', 'entityGroups');
    }

    protected function buildDepthMap($entities): array
    {
        $parentMap = [];
        foreach ($entities as $e) {
            $parentMap[$e->id] = $e->parent_entity_id;
        }

        $depths = [];
        foreach ($entities as $e) {
            $depth = 0;
            $current = $e->id;
            $visited = [];
            while (isset($parentMap[$current]) && $parentMap[$current] !== null) {
                if (in_array($current, $visited)) break; // cycle guard
                $visited[] = $current;
                $current = $parentMap[$current];
                $depth++;
            }
            $depths[$e->id] = $depth;
        }

        return $depths;
    }

    protected function lightenByDepth(string $hex, int $depth): string
    {
        if ($depth <= 0) return $hex;

        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Each depth level blends 20% toward white (max 60% blend at depth 3+)
        $factor = min($depth * 0.20, 0.60);
        $r = (int) round($r + (255 - $r) * $factor);
        $g = (int) round($g + (255 - $g) * $factor);
        $b = (int) round($b + (255 - $b) * $factor);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    protected function humanMorphType(string $type): string
    {
        return match ($type) {
            'planner_project' => 'Projekt',
            'planner_task'    => 'Task',
            'canvas'          => 'Canvas',
            'helpdesk_ticket' => 'Ticket',
            default           => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function render()
    {
        return view('organization::livewire.entity.mindmap')
            ->layout('platform::layouts.app');
    }
}
