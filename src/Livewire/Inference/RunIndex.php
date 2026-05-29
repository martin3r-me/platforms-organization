<?php

namespace Platform\Organization\Livewire\Inference;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationInferenceRun;

class RunIndex extends Component
{
    public $search = '';
    public $statusFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    #[Computed]
    public function runs()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationInferenceRun::forTeam($teamId)
            ->with('trigger');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('summary', 'like', '%' . $this->search . '%')
                  ->orWhere('llm_model', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function render()
    {
        return view('organization::livewire.inference.run-index')
            ->layout('platform::layouts.app');
    }
}
