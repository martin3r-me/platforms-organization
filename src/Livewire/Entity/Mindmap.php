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

class Mindmap extends Component
{
    public OrganizationEntity $entity;

    /**
     * Farbpalette - wird dynamisch an Gruppen vergeben.
     * Bekannte Gruppen bekommen stabile Farben, alles andere aus dem Pool.
     */
    protected array $knownColors = [
        'Organisationseinheiten' => '#3B82F6',
        'Personen'               => '#8B5CF6',
        'Rollen'                 => '#F59E0B',
        'Gruppen'                => '#10B981',
        'Externe'                => '#EF4444',
        'Technische Systeme'     => '#6366F1',
    ];

    protected array $colorPool = [
        '#F97316', '#06B6D4', '#84CC16', '#EC4899', '#A855F7',
        '#14B8A6', '#F43F5E', '#8B5CF6', '#22D3EE', '#FB923C',
    ];

    protected int $colorPoolIndex = 0;

    protected function colorForGroup(string $group): string
    {
        if (isset($this->knownColors[$group])) {
            return $this->knownColors[$group];
        }

        // Dynamisch aus dem Pool vergeben und merken
        if (!isset($this->knownColors[$group])) {
            $this->knownColors[$group] = $this->colorPool[$this->colorPoolIndex % count($this->colorPool)];
            $this->colorPoolIndex++;
        }

        return $this->knownColors[$group];
    }

    protected array $linkTypeColors = [
        'planner_project' => '#10B981',
        'canvas'          => '#EC4899',
        'planner_task'    => '#14B8A6',
        'helpdesk_ticket' => '#F59E0B',
    ];

    public function mount(OrganizationEntity $entity)
    {
        $this->entity = $entity->load(['type.group']);
    }

    #[Computed]
    public function graphData(): array
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
            $baseColor = $this->colorForGroup($groupName);
            $color = $isCenter ? '#111827' : $this->lightenByDepth($baseColor, $depth);

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
                    'color'  => 'rgba(156,163,175,0.7)',
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
                'source' => 'e' . $rel->from_entity_id,
                'target' => 'e' . $rel->to_entity_id,
                'color'  => $relationColors[$code] ?? '#F59E0B',
                'width'  => 2,
                'ltype'  => 'relation',
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
                $nameCol = $columns->first(fn ($c) => in_array($c, ['name', 'title']));
                $labelExpr = $nameCol
                    ? DB::raw("COALESCE({$nameCol}, CONCAT('#', id)) as label")
                    : DB::raw("CONCAT('#', id) as label");
                $rows = DB::table($table)->whereIn('id', $ids)->select('id', $labelExpr)->get();
                foreach ($rows as $row) {
                    $labelMap[$row->id] = $row->label;
                }
            }

            // Dynamische Farbe & Label für unbekannte Typen
            $typeColor = $this->linkTypeColors[$morphType]
                ?? $this->colorPool[crc32($morphType) % count($this->colorPool)];
            $typeName = $this->humanMorphType($morphType);

            foreach ($typeLinks as $link) {
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

        // Filter categories - dynamisch aus den Daten
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

        // Entity sub-groups - dynamisch
        $entityGroups = [];
        foreach ($nodes as $n) {
            if (($n['category'] ?? '') !== 'entity') continue;
            $g = $n['group'] ?? 'Sonstige';
            if (!isset($entityGroups[$g])) {
                $entityGroups[$g] = ['count' => 0, 'color' => $this->colorForGroup($g)];
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
