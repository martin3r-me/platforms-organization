<?php

namespace Platform\Organization\Livewire\Settings\SynthesisPrompt;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Models\OrganizationSynthesisPromptDefinition;

class Index extends Component
{
    public string $search = '';
    public string $reportTypeFilter = '';
    public bool $showInactive = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'reportTypeFilter' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    public function createNew(): void
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return;
        }

        $definition = OrganizationSynthesisPromptDefinition::create([
            'team_id' => $teamId,
            'name' => 'Neuer Synthesis-Prompt',
            'report_type' => 'weekly',
            'system_prompt' => OrganizationSynthesisPromptDefinition::DEFAULT_SYSTEM_PROMPT,
            'user_message_template' => OrganizationSynthesisPromptDefinition::DEFAULT_USER_TEMPLATE,
            'max_signals' => 100,
            'max_tokens' => 8192,
            'is_active' => false,
        ]);

        $this->redirect(route('organization.settings.synthesis-prompts.show', $definition), navigate: true);
    }

    public function toggleActive(int $definitionId): void
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return;
        }

        $def = OrganizationSynthesisPromptDefinition::forTeam($teamId)->find($definitionId);
        if (! $def) {
            return;
        }

        $def->update(['is_active' => ! $def->is_active]);
        unset($this->definitions);
    }

    public function delete(int $definitionId): void
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return;
        }

        $def = OrganizationSynthesisPromptDefinition::forTeam($teamId)->find($definitionId);
        if (! $def) {
            return;
        }

        $def->delete();
        unset($this->definitions);
    }

    #[Computed]
    public function definitions()
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;
        if (! $teamId) {
            return collect();
        }

        $query = OrganizationSynthesisPromptDefinition::forTeam($teamId);

        if (! $this->showInactive) {
            $query->active();
        }

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->reportTypeFilter !== '') {
            $query->forReportType($this->reportTypeFilter);
        }

        return $query->orderBy('report_type')->orderBy('name')->get();
    }

    public function render()
    {
        return view('organization::livewire.settings.synthesis-prompt.index')
            ->layout('platform::layouts.app');
    }
}
