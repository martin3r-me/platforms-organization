<?php

namespace Platform\Organization\Livewire\Synthesis;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSynthesisReport;

class Index extends Component
{
    public $statusFilter = '';
    public $typeFilter = '';

    protected $queryString = [
        'statusFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
    ];

    #[Computed]
    public function reports()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationSynthesisReport::forTeam($teamId);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter) {
            $query->where('report_type', $this->typeFilter);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function render()
    {
        return view('organization::livewire.synthesis.index')
            ->layout('platform::layouts.app');
    }
}
