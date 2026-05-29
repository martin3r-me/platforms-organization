<?php

namespace Platform\Organization\Livewire\Inquiry;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationInquiry;

class Index extends Component
{
    public $search = '';
    public $statusFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    #[Computed]
    public function inquiries()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationInquiry::forTeam($teamId)
            ->with(['entity', 'inferencePrompt', 'recipients']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('context_summary', 'like', '%' . $this->search . '%')
                  ->orWhere('inquiry_type', 'like', '%' . $this->search . '%')
                  ->orWhereHas('entity', fn ($e) => $e->where('name', 'like', '%' . $this->search . '%'));
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function render()
    {
        return view('organization::livewire.inquiry.index')
            ->layout('platform::layouts.app');
    }
}
