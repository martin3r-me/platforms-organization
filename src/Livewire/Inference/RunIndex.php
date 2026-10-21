<?php

namespace Platform\Organization\Livewire\Inference;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationInferenceRun;

class RunIndex extends Component
{
    public $search = '';
    public $statusFilter = '';

    /** Sichtbare Zeilen; "Weitere laden" erhöht um PAGE. */
    public int $limit = self::PAGE;

    private const PAGE = 100;

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

        // Ein Datensatz mehr als sichtbar: verrät, ob es weitere gibt.
        return $query->orderByDesc('created_at')->limit($this->limit + 1)->get();
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE;
    }

    public function updated($property): void
    {
        if ($property !== 'limit') {
            $this->limit = self::PAGE;
        }
    }

    public function render()
    {
        return view('organization::livewire.inference.run-index')
            ->layout('platform::layouts.app');
    }
}
