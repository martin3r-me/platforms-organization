<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityLink;
use Platform\Organization\Models\OrganizationEntityRelationship;

class Mindmap extends Component
{
    public OrganizationEntity $entity;

    /**
     * Farben pro EntityTypeGroup
     */
    protected array $groupColors = [
        'Organisationseinheiten' => '#3B82F6', // blue
        'Personen'               => '#8B5CF6', // purple
        'Rollen'                 => '#F59E0B', // amber
        'Gruppen'                => '#10B981', // green
        'Externe'                => '#EF4444', // red
        'Technische Systeme'     => '#6366F1', // indigo
        'Erweiterte Kontexte'    => '#F97316', // orange
    ];

    /**
     * Farben für verlinkte Module (Entity Links)
     */
    protected array $linkTypeColors = [
        'project'         => '#10B981', // green
        'planner_project' => '#10B981', // green
        'canvas'          => '#EC4899', // pink
        'planner_task'    => '#14B8A6', // teal
        'helpdesk_ticket' => '#F59E0B', // amber
    ];

    public function mount(OrganizationEntity $entity)
    {
        $this->entity = $entity->load([
            'type.group',
            'parent.type.group',
        ]);
    }

    #[Computed]
    public function graphData(): array
    {
        $teamId = $this->entity->team_id;
        $nodes = [];
        $links = [];
        $entityMap = [];

        // 1. Alle Entities des Teams laden
        $entities = OrganizationEntity::forTeam($teamId)
            ->active()
            ->with(['type.group', 'costCenter', 'vsmSystem'])
            ->get();

        foreach ($entities as $e) {
            $groupName = $e->type?->group?->name ?? 'Sonstige';
            $color = $this->groupColors[$groupName] ?? '#9CA3AF';
            $isCenter = $e->id === $this->entity->id;

            $nodes[] = [
                'id'        => 'entity-' . $e->id,
                'label'     => $e->name,
                'group'     => $groupName,
                'type'      => 'entity',
                'typeName'  => $e->type?->name ?? '',
                'typeCode'  => $e->type?->code ?? '',
                'icon'      => $e->type?->icon ?? 'building-office',
                'color'     => $isCenter ? '#FFFFFF' : $color,
                'size'      => $isCenter ? 12 : ($e->isRoot() ? 8 : 5),
                'isCenter'  => $isCenter,
                'entityId'  => $e->id,
                'code'      => $e->code,
            ];
            $entityMap[$e->id] = true;

            // 2. Parent-Child Hierarchie-Kanten
            if ($e->parent_entity_id && isset($entityMap[$e->parent_entity_id])) {
                // Link wird ggf. doppelt erzeugt, dedup später
            }
        }

        // Parent-child links (nach dem Loop, damit alle IDs vorhanden)
        foreach ($entities as $e) {
            if ($e->parent_entity_id) {
                $links[] = [
                    'source'   => 'entity-' . $e->parent_entity_id,
                    'target'   => 'entity-' . $e->id,
                    'type'     => 'hierarchy',
                    'label'    => '',
                    'color'    => 'rgba(156,163,175,0.3)',
                    'width'    => 1,
                ];
            }
        }

        // 3. Entity Relationships laden
        $relationships = OrganizationEntityRelationship::query()
            ->whereIn('from_entity_id', $entities->pluck('id'))
            ->orWhereIn('to_entity_id', $entities->pluck('id'))
            ->with('relationType')
            ->get();

        $relationColors = [
            'manages'              => '#8B5CF6',
            'is_part_of'           => '#6366F1',
            'contains'             => '#3B82F6',
            'works_for'            => '#10B981',
            'provides_service_to'  => '#F97316',
        ];

        foreach ($relationships as $rel) {
            $code = $rel->relationType?->code ?? '';
            $links[] = [
                'source'   => 'entity-' . $rel->from_entity_id,
                'target'   => 'entity-' . $rel->to_entity_id,
                'type'     => 'relation',
                'label'    => $rel->relationType?->name ?? '',
                'color'    => $relationColors[$code] ?? '#F59E0B',
                'width'    => 2,
            ];
        }

        // 4. Entity Links (Projekte, Canvases etc.)
        $entityLinks = OrganizationEntityLink::query()
            ->whereIn('entity_id', $entities->pluck('id'))
            ->where('team_id', $teamId)
            ->get();

        // Linked Items nach Morph-Type gruppieren und per Batch laden
        $linkedItemNodes = [];
        $groupedLinks = $entityLinks->groupBy('linkable_type');

        foreach ($groupedLinks as $morphType => $typeLinks) {
            $ids = $typeLinks->pluck('linkable_id')->unique()->values()->all();

            // Morph-Alias zur echten Klasse auflösen
            $modelClass = Relation::getMorphedModel($morphType) ?? $morphType;
            $labelMap = [];

            if (class_exists($modelClass)) {
                $table = (new $modelClass)->getTable();
                // Name oder Title als Label holen
                $rows = DB::table($table)
                    ->whereIn('id', $ids)
                    ->select('id', DB::raw("COALESCE(name, title, CONCAT('#', id)) as label"))
                    ->get();
                foreach ($rows as $row) {
                    $labelMap[$row->id] = $row->label;
                }
            }

            foreach ($typeLinks as $link) {
                $morphId = $link->linkable_id;
                $nodeId = $morphType . '-' . $morphId;

                if (!isset($linkedItemNodes[$nodeId])) {
                    $label = $labelMap[$morphId] ?? "#{$morphId}";

                    $linkedItemNodes[$nodeId] = [
                        'id'        => $nodeId,
                        'label'     => $label,
                        'group'     => $this->humanMorphType($morphType),
                        'type'      => 'linked',
                        'typeName'  => $this->humanMorphType($morphType),
                        'typeCode'  => $morphType,
                        'icon'      => $this->morphTypeIcon($morphType),
                        'color'     => $this->linkTypeColors[$morphType] ?? '#9CA3AF',
                        'size'      => 3,
                        'isCenter'  => false,
                        'entityId'  => null,
                        'code'      => null,
                    ];
                }

                $links[] = [
                    'source'   => 'entity-' . $link->entity_id,
                    'target'   => $nodeId,
                    'type'     => 'entity_link',
                    'label'    => '',
                    'color'    => $this->linkTypeColors[$morphType] ?? 'rgba(156,163,175,0.4)',
                    'width'    => 1,
                ];
            }
        }

        $nodes = array_merge($nodes, array_values($linkedItemNodes));

        return [
            'nodes' => $nodes,
            'links' => $links,
        ];
    }

    protected function humanMorphType(string $type): string
    {
        return match ($type) {
            'project', 'planner_project' => 'Projekt',
            'planner_task'               => 'Task',
            'canvas'                     => 'Canvas',
            'helpdesk_ticket'            => 'Ticket',
            default                      => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    protected function morphTypeIcon(string $type): string
    {
        return match ($type) {
            'project', 'planner_project' => 'folder-kanban',
            'planner_task'               => 'check-square',
            'canvas'                     => 'layout-dashboard',
            'helpdesk_ticket'            => 'ticket',
            default                      => 'link',
        };
    }

    public function render()
    {
        return view('organization::livewire.entity.mindmap')
            ->layout('platform::layouts.app');
    }
}
