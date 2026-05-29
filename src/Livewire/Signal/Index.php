<?php

namespace Platform\Organization\Livewire\Signal;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSignal;

class Index extends Component
{
    public $search = '';
    public $statusFilter = '';
    public $severityFilter = '';
    public $sourceFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'severityFilter' => ['except' => ''],
        'sourceFilter' => ['except' => ''],
    ];

    #[Computed]
    public function signals()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationSignal::forTeam($teamId)
            ->with(['entity', 'definition', 'inferencePrompt']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('message', 'like', '%' . $this->search . '%')
                  ->orWhereHas('entity', fn ($e) => $e->where('name', 'like', '%' . $this->search . '%'));
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->severityFilter) {
            $query->where('severity', $this->severityFilter);
        }

        if ($this->sourceFilter) {
            $query->where('source', $this->sourceFilter);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function render()
    {
        return view('organization::livewire.signal.index')
            ->layout('platform::layouts.app');
    }
}
