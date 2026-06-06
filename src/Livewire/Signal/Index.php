<?php

namespace Platform\Organization\Livewire\Signal;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalFocus;

class Index extends Component
{
    public const VIEW_ACTIVE = 'active';
    public const VIEW_ARCHIVE = 'archive';

    public $search = '';
    public $statusFilter = '';
    public $severityFilter = '';
    public $sourceFilter = '';
    public bool $focusOnly = false;
    public string $view = self::VIEW_ACTIVE;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'severityFilter' => ['except' => ''],
        'sourceFilter' => ['except' => ''],
        'focusOnly' => ['except' => false],
        'view' => ['except' => self::VIEW_ACTIVE],
    ];

    public function switchView(string $view): void
    {
        if (! in_array($view, [self::VIEW_ACTIVE, self::VIEW_ARCHIVE], true)) {
            return;
        }

        $this->view = $view;
        // Reset status filter when switching — it would conflict with the view's status set
        $this->statusFilter = '';
        unset($this->signals);
    }

    protected function statusesForView(string $view): array
    {
        return match ($view) {
            self::VIEW_ARCHIVE => ['resolved', 'dismissed'],
            default => ['open', 'acknowledged'],
        };
    }

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
    public function viewCounts(): array
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return ['active' => 0, 'archive' => 0];
        }

        return [
            'active' => OrganizationSignal::forTeam($teamId)->whereIn('status', $this->statusesForView(self::VIEW_ACTIVE))->count(),
            'archive' => OrganizationSignal::forTeam($teamId)->whereIn('status', $this->statusesForView(self::VIEW_ARCHIVE))->count(),
        ];
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

        // Constrain to the current view's status set
        $allowedStatuses = $this->statusesForView($this->view);
        if ($this->statusFilter && in_array($this->statusFilter, $allowedStatuses, true)) {
            $query->where('status', $this->statusFilter);
        } else {
            $query->whereIn('status', $allowedStatuses);
        }

        if ($this->focusOnly && auth()->id()) {
            $query->focusedBy(auth()->id());
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('message', 'like', '%' . $this->search . '%')
                  ->orWhereHas('entity', fn ($e) => $e->where('name', 'like', '%' . $this->search . '%'));
            });
        }

        if ($this->severityFilter) {
            $query->where('severity', $this->severityFilter);
        }

        if ($this->sourceFilter) {
            $query->where('source', $this->sourceFilter);
        }

        // Archive sorts by resolved_at (when available), otherwise created_at — gives meaningful chronology
        $orderColumn = $this->view === self::VIEW_ARCHIVE ? 'resolved_at' : 'created_at';

        return $query->orderByRaw("COALESCE({$orderColumn}, created_at) DESC")->get();
    }

    public function render()
    {
        return view('organization::livewire.signal.index')
            ->layout('platform::layouts.app');
    }
}
