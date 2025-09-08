<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        return view('organization::livewire.dashboard')
            ->layout('platform::layouts.app');
    }
}