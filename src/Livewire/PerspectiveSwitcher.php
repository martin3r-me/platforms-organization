<?php

namespace Platform\Organization\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Organization\Services\PerspectiveService;

/**
 * Perspektive = aktive Carrier-Entity in der Session.
 * Kein eigenes Perspective-Modell mehr.
 */
class PerspectiveSwitcher extends Component
{
    public $show = false;
    public array $carriers = [];
    public ?int $activeEntityId = null;
    public ?string $activeEntityName = null;

    public function mount(): void
    {
        $this->load();
    }

    #[On('open-perspective-switcher')]
    public function openSwitcher(): void
    {
        $this->show = true;
        $this->load();
    }

    public function load(): void
    {
        $user = Auth::user();
        if (!$user?->currentTeam) {
            return;
        }

        $active = PerspectiveService::getActiveEntity($user->currentTeam->id, $user->id);
        $this->activeEntityId = $active?->id;
        $this->activeEntityName = $active?->name;

        $this->carriers = PerspectiveService::getCarriersForTeam($user->currentTeam->id)
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'code' => $e->code,
                'type_name' => $e->type?->name,
                'is_active' => $e->id === $this->activeEntityId,
                'is_root' => $e->parent_entity_id === null,
            ])
            ->values()
            ->toArray();
    }

    public function switchPerspective(int $entityId): void
    {
        $user = Auth::user();
        if (!$user?->currentTeam) {
            return;
        }

        $entity = PerspectiveService::setActiveEntity($entityId, $user->currentTeam->id);
        if (!$entity) {
            return;
        }

        $this->activeEntityId = $entity->id;
        $this->activeEntityName = $entity->name;
        $this->show = false;

        $this->load();

        $this->dispatch('perspective-switched', perspectiveEntityId: $entityId);
    }

    public function render()
    {
        return view('organization::livewire.perspective-switcher');
    }
}
