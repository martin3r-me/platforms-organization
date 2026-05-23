<?php

namespace Platform\Organization\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Organization\Models\OrganizationPerspective;
use Platform\Organization\Services\PerspectiveService;

class PerspectiveSwitcher extends Component
{
    public $show = false;
    public $perspectives = [];
    public $currentPerspective = null;
    public ?int $currentPerspectiveId = null;
    public ?string $currentPerspectiveName = null;
    public int $entitiesInViewCount = 0;

    public function mount(): void
    {
        $this->loadCurrentPerspective();
    }

    #[On('open-perspective-switcher')]
    public function openSwitcher(): void
    {
        $this->show = true;
        $this->loadPerspectives();
    }

    public function loadCurrentPerspective(): void
    {
        $user = Auth::user();
        if (!$user?->currentTeam) {
            return;
        }

        $perspective = PerspectiveService::getActive($user->currentTeam->id, $user->id);
        $this->currentPerspectiveId = $perspective->id;
        $this->currentPerspectiveName = $perspective->name;

        $service = new PerspectiveService();
        $this->entitiesInViewCount = $service->entitiesInView($perspective)->count();
    }

    public function loadPerspectives(): void
    {
        $user = Auth::user();
        if (!$user?->currentTeam) {
            return;
        }

        $this->perspectives = PerspectiveService::getForTeam($user->currentTeam->id)
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'is_default' => $p->is_default,
                'is_active' => $p->id === $this->currentPerspectiveId,
            ])
            ->toArray();
    }

    public function switchPerspective(int $perspectiveId): void
    {
        $user = Auth::user();
        if (!$user?->currentTeam) {
            return;
        }

        $perspective = PerspectiveService::switchTo($perspectiveId, $user->currentTeam->id);
        if (!$perspective) {
            return;
        }

        $this->currentPerspectiveId = $perspective->id;
        $this->currentPerspectiveName = $perspective->name;
        $this->show = false;

        $service = new PerspectiveService();
        $this->entitiesInViewCount = $service->entitiesInView($perspective)->count();

        $this->dispatch('perspective-switched', perspectiveId: $perspectiveId);
    }

    public function render()
    {
        return view('organization::livewire.perspective-switcher');
    }
}
