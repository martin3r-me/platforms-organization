<?php

namespace Platform\Organization\Livewire\Memory;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationMemoryEntry;

class Index extends Component
{
    public $search = '';
    public $typeFilter = '';
    public $showInactive = false;

    /** Sichtbare Zeilen; Treffer zählen alle. */
    public int $limit = self::PAGE;

    private const PAGE = 100;

    protected $queryString = [
        'search' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    #[Computed]
    public function memoryEntries()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationMemoryEntry::forTeam($teamId)
            ->with(['entity', 'inferencePrompt']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('content', 'like', '%' . $this->search . '%')
                  ->orWhereHas('entity', fn ($e) => $e->where('name', 'like', '%' . $this->search . '%'));
            });
        }

        if ($this->typeFilter) {
            $query->ofType($this->typeFilter);
        }

        if (! $this->showInactive) {
            $query->active();
        }

        return $query->orderByDesc('created_at')->get();
    }

    #[Computed]
    public function memoryTypes()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        return OrganizationMemoryEntry::forTeam($teamId)
            ->select('memory_type')
            ->distinct()
            ->pluck('memory_type')
            ->filter()
            ->sort()
            ->values();
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
        return view('organization::livewire.memory.index')
            ->layout('platform::layouts.app');
    }
}
