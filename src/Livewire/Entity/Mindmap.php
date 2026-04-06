<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;

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
                'color' => $isCenter ? '#FFFFFF' : ($this->groupColors[$groupName] ?? '#9CA3AF'),
                'val'   => $isCenter ? 12 : 4,
            ];

            if ($e->parent_entity_id) {
                $links[] = [
                    'source' => 'e' . $e->parent_entity_id,
                    'target' => 'e' . $e->id,
                ];
            }
        }

        return compact('nodes', 'links');
    }

    public function render()
    {
        return view('organization::livewire.entity.mindmap')
            ->layout('platform::layouts.app');
    }
}
