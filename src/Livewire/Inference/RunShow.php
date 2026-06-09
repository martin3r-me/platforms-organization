<?php

namespace Platform\Organization\Livewire\Inference;

use Livewire\Component;
use Platform\Organization\Models\OrganizationInferenceRun;

class RunShow extends Component
{
    public OrganizationInferenceRun $run;

    public function mount(OrganizationInferenceRun $run): void
    {
        $teamId = auth()->user()?->currentTeamRelation?->id;

        if (! $teamId || (int) $run->team_id !== (int) $teamId) {
            abort(404);
        }

        $this->run = $run->load([
            'trigger',
            'synthesisReports:id,inference_run_id,title,report_type,status,period_start,period_end',
            'inquiries:id,inference_run_id,inquiry_type,context_summary,status,created_at',
            'steps:id,inference_run_id,inference_prompt_id,step_index,step_type,tool_name,arguments,result,result_ok,error_message,duration_ms,occurred_at',
            'steps.prompt:id,name,vsm_system',
        ]);
    }

    public function render()
    {
        return view('organization::livewire.inference.run-show')
            ->layout('platform::layouts.app');
    }
}
