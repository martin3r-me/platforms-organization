<?php

namespace Platform\Organization\Livewire\Synthesis;

use Livewire\Component;
use Illuminate\Database\Eloquent\Model;
use Platform\Core\Livewire\Concerns\HasPageContext;
use Platform\Organization\Models\OrganizationSynthesisReport;

class Show extends Component
{
    use HasPageContext;

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

    protected function pageContext(): ?Model
    {
        return $this->report;
    }

    protected function pageContextOptions(): array
    {
        return [
            'url' => route('organization.synthesis-reports.show', $this->report),
            'source' => 'organization.synthesis-report.view',
        ];
    }

    public function render()
    {
        return view('organization::livewire.synthesis.show')
            ->layout('platform::layouts.app');
    }
}
