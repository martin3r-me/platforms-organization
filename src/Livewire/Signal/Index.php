<?php

namespace Platform\Organization\Livewire\Signal;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalFocus;

class Index extends Component
{
    public $search = '';
    public $statusFilter = '';
    public $severityFilter = '';
    public $sourceFilter = '';
    public bool $focusOnly = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'severityFilter' => ['except' => ''],
        'sourceFilter' => ['except' => ''],
        'focusOnly' => ['except' => false],
    ];

    public function toggleFocus(int $signalId): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $existing = OrganizationSignalFocus::where('signal_id', $signalId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            OrganizationSignalFocus::create([
                'signal_id' => $signalId,
                'user_id' => $userId,
                'focused_at' => now(),
            ]);
        }

        unset($this->signals, $this->focusedSignals, $this->focusedIds);
    }

    #[Computed]
    public function focusedIds(): array
    {
        $userId = auth()->id();
        if (! $userId) {
            return [];
        }

        return OrganizationSignalFocus::where('user_id', $userId)
            ->pluck('signal_id')
            ->all();
    }

    #[Computed]
    public function focusedSignals()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        $userId = auth()->id();

        if (! $teamId || ! $userId) {
            return collect();
        }

        return OrganizationSignal::forTeam($teamId)
            ->focusedBy($userId)
            ->with(['entity', 'definition', 'inferencePrompt', 'focuses'])
            ->withCount('comments')
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function signals()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationSignal::forTeam($teamId)
            ->with(['entity', 'definition', 'inferencePrompt'])
            ->withCount('comments');

        if ($this->focusOnly && auth()->id()) {
            $query->focusedBy(auth()->id());
        }

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
