<?php

namespace Platform\Organization\Livewire\Perspective;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityHierarchy;
use Platform\Organization\Models\OrganizationPerspective;
use Platform\Organization\Services\EntityHierarchyResolver;

class HierarchyEditor extends Component
{
    public OrganizationPerspective $perspective;

    public ?int $addEntityId = null;
    public ?int $addParentEntityId = null;

    public function mount(OrganizationPerspective $perspective)
    {
        $this->perspective = $perspective;
    }

    #[Computed]
    public function hierarchyEntries()
    {
        return OrganizationEntityHierarchy::where('perspective_id', $this->perspective->id)
            ->with(['entity.type', 'parentEntity.type'])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function availableEntities()
    {
        $existingIds = OrganizationEntityHierarchy::where('perspective_id', $this->perspective->id)
            ->pluck('entity_id');

        return OrganizationEntity::forTeam($this->perspective->team_id)
            ->active()
            ->whereNotIn('id', $existingIds)
            ->with('type')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function entitiesInPerspective()
    {
        return OrganizationEntityHierarchy::where('perspective_id', $this->perspective->id)
            ->with('entity')
            ->get()
            ->pluck('entity')
            ->filter()
            ->sortBy('name');
    }

    public function addEntity(): void
    {
        if (!$this->addEntityId) {
            return;
        }

        $entity = OrganizationEntity::findOrFail($this->addEntityId);

        // Validate parent if set
        if ($this->addParentEntityId) {
            $resolver = resolve(EntityHierarchyResolver::class);
            $resolver->validateNoCircularHierarchy(
                $entity->id,
                $this->addParentEntityId,
                $this->perspective
            );
        }

        OrganizationEntityHierarchy::updateOrCreate(
            [
                'perspective_id' => $this->perspective->id,
                'entity_id' => $entity->id,
            ],
            [
                'parent_entity_id' => $this->addParentEntityId ?: null,
                'team_id' => $this->perspective->team_id,
            ]
        );

        $this->addEntityId = null;
        $this->addParentEntityId = null;
        unset($this->hierarchyEntries, $this->availableEntities, $this->entitiesInPerspective);
        $this->dispatch('toast', message: 'Entity zur Perspektive hinzugefuegt');
    }

    public function updateParent(int $hierarchyId, ?int $newParentId): void
    {
        $entry = OrganizationEntityHierarchy::findOrFail($hierarchyId);

        if ($newParentId) {
            $resolver = resolve(EntityHierarchyResolver::class);
            try {
                $resolver->validateNoCircularHierarchy(
                    $entry->entity_id,
                    $newParentId,
                    $this->perspective
                );
            } catch (\InvalidArgumentException $e) {
                $this->dispatch('toast', message: $e->getMessage(), variant: 'danger');
                return;
            }
        }

        $entry->update(['parent_entity_id' => $newParentId ?: null]);
        unset($this->hierarchyEntries);
        $this->dispatch('toast', message: 'Parent aktualisiert');
    }

    public function removeEntity(int $hierarchyId): void
    {
        $entry = OrganizationEntityHierarchy::findOrFail($hierarchyId);

        // Also remove any children that reference this entity as parent
        OrganizationEntityHierarchy::where('perspective_id', $this->perspective->id)
            ->where('parent_entity_id', $entry->entity_id)
            ->update(['parent_entity_id' => null]);

        $entry->delete();
        unset($this->hierarchyEntries, $this->availableEntities, $this->entitiesInPerspective);
        $this->dispatch('toast', message: 'Entity aus Perspektive entfernt');
    }

    public function render()
    {
        return view('organization::livewire.perspective.hierarchy-editor');
    }
}
