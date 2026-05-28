<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;

class Sidebar extends Component
{
    public function getRecentEntitiesProperty()
    {
        return OrganizationEntity::active()
            ->forTeam(auth()->user()->currentTeam->id)
            ->with('type')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('organization::livewire.sidebar')
            ->layout('platform::layouts.app');
    }
}