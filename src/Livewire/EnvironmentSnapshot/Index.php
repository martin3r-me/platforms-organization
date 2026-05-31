<?php

namespace Platform\Organization\Livewire\EnvironmentSnapshot;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEnvironmentSnapshot;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Models\OrganizationMemoryEntry;

class Index extends Component
{
    public $sourceFilter = '';
    public $expandedId = null;

    protected $queryString = [
        'sourceFilter' => ['except' => ''],
    ];

    #[Computed]
    public function snapshots()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationEnvironmentSnapshot::where('team_id', $teamId)
            ->with('source');

        if ($this->sourceFilter) {
            $query->where('source_id', $this->sourceFilter);
        }

        return $query->orderByDesc('snapshot_date')->limit(100)->get();
    }

    #[Computed]
    public function sources()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        return OrganizationEnvironmentSource::forTeam($teamId)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function sourceRelevanceMemories()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        return OrganizationMemoryEntry::forTeam($teamId)
            ->ofType('source_relevance')
            ->active()
            ->valid()
            ->get()
            ->keyBy(fn ($m) => $m->structured_data['source_id'] ?? null);
    }

    public function toggleExpand(int $id)
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function render()
    {
        return view('organization::livewire.environment-snapshot.index')
            ->layout('platform::layouts.app');
    }
}
