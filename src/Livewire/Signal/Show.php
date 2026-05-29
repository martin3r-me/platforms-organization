<?php

namespace Platform\Organization\Livewire\Signal;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationInferencePromptStat;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Models\OrganizationSignal;

class Show extends Component
{
    public OrganizationSignal $signal;
    public string $newNote = '';
    public string $actionReason = '';
    public string $pendingAction = '';

    public function mount(OrganizationSignal $signal)
    {
        $this->signal = $signal->load([
            'definition',
            'entity.type',
            'resolvedByUser:id,name',
        ]);
    }

    #[Computed]
    public function signalActivities()
    {
        return $this->signal->activities()
            ->with('user:id,name,profile_photo_path')
            ->latest()
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function historicalSignals()
    {
        return OrganizationSignal::where('signal_definition_id', $this->signal->signal_definition_id)
            ->where('entity_id', $this->signal->entity_id)
            ->where('id', '!=', $this->signal->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function startAction(string $action): void
    {
        $this->pendingAction = $action;
        $this->actionReason = '';
    }

    public function cancelAction(): void
    {
        $this->pendingAction = '';
        $this->actionReason = '';
    }

    public function confirmAction(): void
    {
        $reason = trim($this->actionReason);

        match ($this->pendingAction) {
            'acknowledge' => $this->executeAcknowledge($reason),
            'resolve' => $this->executeResolve($reason),
            'dismiss' => $this->executeDismiss($reason),
        };

        $this->pendingAction = '';
        $this->actionReason = '';
    }

    protected function executeAcknowledge(string $reason): void
    {
        $this->signal->update(['status' => 'acknowledged']);
        $this->processSignalFeedback('acknowledge', $reason);
        $this->signal->refresh();
        unset($this->signalActivities);
    }

    protected function executeResolve(string $reason): void
    {
        $this->signal->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);
        $this->processSignalFeedback('resolve', $reason);
        $this->signal->refresh();
        unset($this->signalActivities);
    }

    protected function executeDismiss(string $reason): void
    {
        $this->signal->update([
            'status' => 'dismissed',
            'dismissed_reason' => $reason ?: null,
        ]);
        $this->processSignalFeedback('dismiss', $reason);
        $this->signal->refresh();
        unset($this->signalActivities);
    }

    protected function processSignalFeedback(string $action, string $reason): void
    {
        if ($this->signal->source !== 'inference' || ! $this->signal->inference_prompt_id) {
            return;
        }

        $teamId = (int) $this->signal->team_id;

        try {
            match ($action) {
                'acknowledge' => OrganizationMemoryEntry::create([
                    'team_id' => $teamId,
                    'entity_id' => $this->signal->entity_id,
                    'inference_prompt_id' => $this->signal->inference_prompt_id,
                    'memory_type' => 'baseline',
                    'content' => ($reason ? "Signal bestätigt ({$reason}): " : 'Signal bestätigt: ') . mb_substr($this->signal->message, 0, 500),
                    'structured_data' => [
                        'signal_id' => $this->signal->id,
                        'severity' => $this->signal->severity,
                        'trigger_metrics' => $this->signal->trigger_metrics,
                        'reason' => $reason ?: null,
                    ],
                    'confidence' => 0.9,
                    'source_type' => 'signal_feedback',
                    'source_id' => $this->signal->id,
                    'valid_until' => null,
                    'is_active' => true,
                ]),
                'dismiss' => OrganizationMemoryEntry::create([
                    'team_id' => $teamId,
                    'entity_id' => $this->signal->entity_id,
                    'inference_prompt_id' => $this->signal->inference_prompt_id,
                    'memory_type' => 'suppression',
                    'content' => $reason
                        ? "Signal verworfen: {$reason}"
                        : 'Signal verworfen (ohne Begründung): ' . mb_substr($this->signal->message, 0, 300),
                    'structured_data' => [
                        'signal_id' => $this->signal->id,
                        'severity' => $this->signal->severity,
                        'reason' => $reason ?: null,
                    ],
                    'confidence' => 0.9,
                    'source_type' => 'signal_feedback',
                    'source_id' => $this->signal->id,
                    'valid_until' => null,
                    'is_active' => true,
                ]),
                'resolve' => OrganizationMemoryEntry::create([
                    'team_id' => $teamId,
                    'entity_id' => $this->signal->entity_id,
                    'inference_prompt_id' => $this->signal->inference_prompt_id,
                    'memory_type' => 'baseline',
                    'content' => ($reason ? "Signal gelöst ({$reason}): " : 'Signal gelöst: ') . mb_substr($this->signal->message, 0, 500),
                    'structured_data' => [
                        'signal_id' => $this->signal->id,
                        'severity' => $this->signal->severity,
                        'resolved' => true,
                        'reason' => $reason ?: null,
                    ],
                    'confidence' => 0.9,
                    'source_type' => 'signal_feedback',
                    'source_id' => $this->signal->id,
                    'valid_until' => null,
                    'is_active' => true,
                ]),
            };
        } catch (\Throwable) {
            // Memory creation should never block signal action
        }

        // Update prompt precision stats
        try {
            $period = now()->startOfMonth()->toDateString();
            $stat = OrganizationInferencePromptStat::firstOrCreate(
                ['inference_prompt_id' => $this->signal->inference_prompt_id, 'period' => $period],
                ['signals_created' => 0, 'signals_acknowledged' => 0, 'signals_dismissed' => 0, 'signals_resolved' => 0, 'precision' => 0.0]
            );

            match ($action) {
                'acknowledge' => $stat->increment('signals_acknowledged'),
                'dismiss' => $stat->increment('signals_dismissed'),
                'resolve' => $stat->increment('signals_resolved'),
            };

            $stat->refresh();
            $total = $stat->signals_acknowledged + $stat->signals_dismissed;
            $stat->precision = $total > 0 ? round($stat->signals_acknowledged / $total, 3) : 0.0;
            $stat->save();
        } catch (\Throwable) {
            // Stats should never block signal action
        }
    }

    public function addNote(): void
    {
        $this->validate(['newNote' => 'required|string|max:1000']);
        $this->signal->logActivity($this->newNote);
        $this->newNote = '';
        unset($this->signalActivities);
    }

    public function render()
    {
        return view('organization::livewire.signal.show')
            ->layout('platform::layouts.app');
    }
}
