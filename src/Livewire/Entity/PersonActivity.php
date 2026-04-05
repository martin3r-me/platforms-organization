<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\PersonActivityRegistry;

class PersonActivity extends Component
{
    public OrganizationEntity $entity;

    #[Computed]
    public function vitalSigns(): array
    {
        if (!$this->entity->linked_user_id) {
            return [];
        }

        $registry = resolve(PersonActivityRegistry::class);
        $teamId = auth()->user()->currentTeam->id;

        return $registry->allVitalSigns($this->entity->linked_user_id, $teamId);
    }

    #[Computed]
    public function responsibilities(): array
    {
        if (!$this->entity->linked_user_id) {
            return [];
        }

        $registry = resolve(PersonActivityRegistry::class);
        $teamId = auth()->user()->currentTeam->id;

        return $registry->allResponsibilities($this->entity->linked_user_id, $teamId);
    }

    #[Computed]
    public function sectionConfigs(): array
    {
        return resolve(PersonActivityRegistry::class)->allSectionConfigs();
    }

    public function render()
    {
        return view('organization::livewire.entity.person-activity');
    }
}
