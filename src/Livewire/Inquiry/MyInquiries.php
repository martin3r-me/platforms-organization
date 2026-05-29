<?php

namespace Platform\Organization\Livewire\Inquiry;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationInquiryRecipient;

class MyInquiries extends Component
{
    public $statusFilter = '';

    protected $queryString = [
        'statusFilter' => ['except' => ''],
    ];

    #[Computed]
    public function recipients()
    {
        $userId = auth()->id();
        if (! $userId) {
            return collect();
        }

        $query = OrganizationInquiryRecipient::where('recipient_user_id', $userId)
            ->with(['inquiry.entity', 'inquiry.inferencePrompt']);

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function render()
    {
        return view('organization::livewire.inquiry.my-inquiries')
            ->layout('platform::layouts.app');
    }
}
