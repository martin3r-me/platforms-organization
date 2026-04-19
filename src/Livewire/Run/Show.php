<?php

namespace Platform\Organization\Livewire\Run;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Organization\Enums\RunStatus;
use Platform\Organization\Enums\RunStepStatus;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Models\OrganizationProcessRun;
use Platform\Organization\Models\OrganizationProcessRunStep;

class Show extends Component
{
    public OrganizationProcess $process;
    public OrganizationProcessRun $run;

    public function mount(OrganizationProcess $process, OrganizationProcessRun $run)
    {
        abort_if($run->process_id !== $process->id, 404);

        $this->process = $process;
        $this->run = $run->load(['process', 'runSteps.processStep', 'user']);
    }

    #[Computed]
    public function runSteps()
    {
        return $this->run->runSteps()->with('processStep')->orderBy('position')->get();
    }

    #[Computed]
    public function progress(): array
    {
        $steps = $this->runSteps;
        $total = $steps->count();
        $done = $steps->filter(fn ($s) => in_array($s->status, [RunStepStatus::COMPLETED, RunStepStatus::SKIPPED]))->count();
        $percent = $total > 0 ? round(($done / $total) * 100) : 0;

        return ['done' => $done, 'total' => $total, 'percent' => $percent];
    }

    #[Computed]
    public function totalActive(): int
    {
        return $this->runSteps->sum('active_duration_minutes') ?? 0;
    }

    #[Computed]
    public function totalWait(): int
    {
        return $this->runSteps->sum('wait_duration_minutes') ?? 0;
    }

    #[Computed]
    public function targetActive(): int
    {
        return $this->runSteps->sum(fn ($rs) => $rs->processStep?->duration_target_minutes ?? 0);
    }

    #[Computed]
    public function targetWait(): int
    {
        return $this->runSteps->sum(fn ($rs) => $rs->processStep?->wait_target_minutes ?? 0);
    }

    #[Computed]
    public function isActive(): bool
    {
        return $this->run->status === RunStatus::ACTIVE;
    }

    public function completeStep(int $runStepId, ?int $activeDuration = null, ?int $waitOverride = null): void
    {
        $runStep = OrganizationProcessRunStep::with('run')->find($runStepId);
        if (! $runStep || $runStep->run_id !== $this->run->id) return;
        if ($runStep->status !== RunStepStatus::PENDING) return;

        $updates = [
            'status'     => 'completed',
            'checked_at' => now(),
            'active_duration_minutes' => $activeDuration,
        ];

        if ($waitOverride !== null) {
            $updates['wait_duration_minutes'] = $waitOverride;
            $updates['wait_override'] = true;
        } else {
            $previousStep = $this->run->runSteps()
                ->where('position', '<', $runStep->position)
                ->whereNotNull('checked_at')
                ->orderByDesc('position')
                ->first();

            if ($previousStep && $previousStep->checked_at) {
                $totalMinutesSince = (int) $previousStep->checked_at->diffInMinutes(now());
                $waitMinutes = max(0, $totalMinutesSince - ($activeDuration ?? 0));
                $updates['wait_duration_minutes'] = $waitMinutes;
            }
        }

        $runStep->update($updates);
        $this->checkRunAutoComplete();
        $this->invalidateCaches();
    }

    public function skipStep(int $runStepId): void
    {
        $runStep = OrganizationProcessRunStep::with('run')->find($runStepId);
        if (! $runStep || $runStep->run_id !== $this->run->id) return;
        if ($runStep->status !== RunStepStatus::PENDING) return;

        $runStep->update([
            'status'     => 'skipped',
            'checked_at' => now(),
        ]);

        $this->checkRunAutoComplete();
        $this->invalidateCaches();
    }

    public function cancelRun(): void
    {
        if ($this->run->status !== RunStatus::ACTIVE) return;

        $this->run->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->run->refresh();
        $this->dispatch('toast', message: 'Durchlauf abgebrochen');
    }

    public function deleteRun(): void
    {
        $processId = $this->process->id;
        $this->run->delete();

        $this->dispatch('toast', message: 'Durchlauf gelöscht');

        $this->redirect(route('organization.processes.show', $processId) . '?tab=runs', navigate: true);
    }

    private function checkRunAutoComplete(): void
    {
        $allSteps = $this->run->runSteps()->get();
        $allDone = $allSteps->every(fn ($s) => in_array($s->status->value, ['completed', 'skipped']));

        if ($allDone) {
            $this->run->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
            $this->run->refresh();
            $this->dispatch('toast', message: 'Durchlauf abgeschlossen');
        }
    }

    private function invalidateCaches(): void
    {
        unset($this->runSteps, $this->progress, $this->totalActive, $this->totalWait, $this->targetActive, $this->targetWait, $this->isActive);
        $this->run->refresh();
    }

    public function render()
    {
        return view('organization::livewire.run.show')
            ->layout('platform::layouts.app');
    }
}
