<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;

class Mindmap extends Component
{
    public OrganizationEntity $entity;

    protected array $groupColors = [
        'Organisationseinheiten' => '#3B82F6',
        'Personen'               => '#8B5CF6',
        'Rollen'                 => '#F59E0B',
        'Gruppen'                => '#10B981',
        'Externe'                => '#EF4444',
        'Technische Systeme'     => '#6366F1',
        'Erweiterte Kontexte'    => '#F97316',
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

        foreach ($entities as $e) {
            $groupName = $e->type?->group?->name ?? 'Sonstige';
            $isCenter = $e->id === $this->entity->id;

            $nodes[] = [
                'id'    => 'e' . $e->id,
                'name'  => $e->name,
                'color' => $isCenter ? '#111827' : ($this->groupColors[$groupName] ?? '#9CA3AF'),
                'val'   => $isCenter ? 25 : 8,
            ];

            if ($e->parent_entity_id) {
                $links[] = [
                    'source' => 'e' . $e->parent_entity_id,
                    'target' => 'e' . $e->id,
                    'color'  => 'rgba(156,163,175,0.3)',
                    'width'  => 1,
                ];
            }
        }

        // Relationships (manages, works_for, etc.)
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
            ];
        }

        return compact('nodes', 'links');
    }

    public function render()
    {
        return view('organization::livewire.entity.mindmap')
            ->layout('platform::layouts.app');
    }
}
