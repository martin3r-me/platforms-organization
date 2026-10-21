<?php

namespace Platform\Organization\Livewire\Synthesis;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSynthesisReport;

class Index extends Component
{
    public $statusFilter = '';
    public $typeFilter = '';

    /** Sichtbare Zeilen; Treffer zählen alle. */
    public int $limit = self::PAGE;

    private const PAGE = 100;

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

        // Ohne content/structured_summary: der Langtext wird erst auf der Detailseite gebraucht.
        $query = OrganizationSynthesisReport::forTeam($teamId)->select([
            'id', 'uuid', 'team_id', 'report_type', 'period_start', 'period_end', 'title',
            'signals_included', 'status', 'published_at', 'created_at', 'updated_at',
        ]);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter) {
            $query->where('report_type', $this->typeFilter);
        }

        return $query->orderByDesc('created_at')->get();
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
        return view('organization::livewire.synthesis.index')
            ->layout('platform::layouts.app');
    }
}
