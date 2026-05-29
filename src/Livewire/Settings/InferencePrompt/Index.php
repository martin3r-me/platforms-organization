<?php

namespace Platform\Organization\Livewire\Settings\InferencePrompt;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;

class Index extends Component
{
    public $search = '';
    public $showInactive = false;
    public $vsmSystemFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'showInactive' => ['except' => false],
        'vsmSystemFilter' => ['except' => ''],
    ];

    #[Computed]
    public function inferencePrompts()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationSignalInferencePrompt::forTeam($teamId);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if (! $this->showInactive) {
            $query->active();
        }

        if ($this->vsmSystemFilter) {
            $query->forVsmSystem($this->vsmSystemFilter);
        }

        return $query->orderBy('name')->get();
    }

    public function render()
    {
        return view('organization::livewire.settings.inference-prompt.index')
            ->layout('platform::layouts.app');
    }
}
