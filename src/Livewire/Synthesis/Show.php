<?php

namespace Platform\Organization\Livewire\Synthesis;

use Livewire\Component;
use Platform\Organization\Models\OrganizationSynthesisReport;

class Show extends Component
{
    public OrganizationSynthesisReport $report;

    public function mount(OrganizationSynthesisReport $report)
    {
        $this->report = $report;
    }

    public function publish(): void
    {
        $this->report->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->report->refresh();
    }

    public function archive(): void
    {
        $this->report->update([
            'status' => 'archived',
        ]);
        $this->report->refresh();
    }

    public function render()
    {
        return view('organization::livewire.synthesis.show')
            ->layout('platform::layouts.app');
    }
}
