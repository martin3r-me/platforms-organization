<?php

namespace Platform\Organization\Livewire\Signal;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationInferencePromptStat;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Models\OrganizationSignal;
use Platform\Organization\Models\OrganizationSignalAction;
use Platform\Organization\Models\OrganizationSignalComment;
use Platform\Organization\Models\OrganizationSignalFocus;

class Show extends Component
{
    public OrganizationSignal $signal;
    public string $newNote = '';
    public string $actionReason = '';
    public string $pendingAction = '';

    // Per-Action decisions
    public ?int $activeActionId = null;
    public string $activeActionDecision = '';
    public string $activeActionReason = '';

    // Comments
    public string $newComment = '';
    public ?int $replyingTo = null;
    public string $replyBody = '';

    // Snooze
    public bool $showSnoozeModal = false;
    public string $snoozeDuration = '3d';
    public string $snoozeCustomDate = '';

    public function mount(OrganizationSignal $signal)
    {
        $this->signal = $signal->load([
            'definition',
            'entity.type',
            'resolvedByUser:id,name',
            'assignee:id,name',
            'actions.decidedByUser:id,name',
            'focuses',
        ]);
    }

    #[Computed]
    public function isFocused(): bool
    {
        return $this->signal->isFocusedBy(auth()->user());
    }

    public function toggleFocus(): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $existing = OrganizationSignalFocus::where('signal_id', $this->signal->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            OrganizationSignalFocus::create([
                'signal_id' => $this->signal->id,
                'user_id' => $userId,
                'focused_at' => now(),
            ]);
        }

        $this->signal->load('focuses');
        unset($this->isFocused);
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
    public function signalComments()
    {
        return $this->signal->comments()
            ->rootComments()
            ->with(['user:id,name', 'replies.user:id,name'])
            ->orderBy('created_at')
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

    // --- Actions ---

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
        $this->signal->update([
            'status' => 'acknowledged',
            'acknowledged_at' => $this->signal->acknowledged_at ?? now(),
        ]);
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
            'resolution_summary' => $reason ?: null,
            'acknowledged_at' => $this->signal->acknowledged_at ?? now(),
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
            'acknowledged_at' => $this->signal->acknowledged_at ?? now(),
        ]);
        $this->processSignalFeedback('dismiss', $reason);
        $this->signal->refresh();
        unset($this->signalActivities);
    }

    // --- Per-Action decisions ---

    public function startActionDecision(int $actionId, string $decision): void
    {
        if (! in_array($decision, ['applied', 'dismissed'], true)) {
            return;
        }

        $this->activeActionId = $actionId;
        $this->activeActionDecision = $decision;
        $this->activeActionReason = '';
    }

    public function cancelActionDecision(): void
    {
        $this->activeActionId = null;
        $this->activeActionDecision = '';
        $this->activeActionReason = '';
    }

    public function confirmActionDecision(): void
    {
        if (! $this->activeActionId || ! in_array($this->activeActionDecision, ['applied', 'dismissed'], true)) {
            return;
        }

        $action = OrganizationSignalAction::where('id', $this->activeActionId)
            ->where('signal_id', $this->signal->id)
            ->first();

        if (! $action || ! $action->isPending()) {
            $this->cancelActionDecision();
            return;
        }

        $reason = trim($this->activeActionReason);

        // Dismiss requires a reason
        if ($this->activeActionDecision === 'dismissed' && $reason === '') {
            $this->addError('activeActionReason', 'Bitte gib eine Begründung an.');
            return;
        }

        $action->update([
            'status' => $this->activeActionDecision,
            'decision_reason' => $reason ?: null,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        $this->processActionFeedback($action);
        $this->logActionSystemComment($action);
        $this->cancelActionDecision();
        $this->maybeDeriveSignalStatus();

        $this->signal->load(['actions.decidedByUser:id,name']);
        unset($this->signalActivities);
        unset($this->signalComments);
    }

    protected function processActionFeedback(OrganizationSignalAction $action): void
    {
        if ($this->signal->source !== 'inference' || ! $this->signal->inference_prompt_id) {
            return;
        }

        $reason = $action->decision_reason ?: '';
        $verb = $action->status === 'applied' ? 'umgesetzt' : 'abgelehnt';
        $content = "Handlungsempfehlung «{$action->title}» {$verb}"
            . ($reason !== '' ? ": {$reason}" : '.');

        try {
            OrganizationMemoryEntry::create([
                'team_id' => (int) $this->signal->team_id,
                'entity_id' => $this->signal->entity_id,
                'inference_prompt_id' => $this->signal->inference_prompt_id,
                'memory_type' => 'prompt_experience',
                'content' => mb_substr($content, 0, 800),
                'structured_data' => [
                    'signal_id' => $this->signal->id,
                    'action_id' => $action->id,
                    'action_position' => $action->position,
                    'decision' => $action->status,
                    'reason' => $reason ?: null,
                ],
                'confidence' => 0.85,
                'source_type' => 'signal_feedback',
                'source_id' => $action->id,
                'valid_until' => null,
                'is_active' => true,
            ]);
        } catch (\Throwable) {
            // Memory should never block the decision
        }
    }

    protected function logActionSystemComment(OrganizationSignalAction $action): void
    {
        $verb = $action->status === 'applied' ? 'umgesetzt' : 'verworfen';
        $reason = $action->decision_reason ?: '';
        $body = "Handlungsempfehlung «{$action->title}» {$verb}"
            . ($reason !== '' ? ": {$reason}" : '.');

        try {
            OrganizationSignalComment::create([
                'signal_id' => $this->signal->id,
                'user_id' => auth()->id(),
                'author_context' => 'system',
                'content' => $body,
            ]);
        } catch (\Throwable) {
            // System comment should never block the decision
        }
    }

    protected function maybeDeriveSignalStatus(): void
    {
        $this->signal->load('actions');
        $derived = $this->signal->deriveStatusFromActions();

        if (! $derived) {
            return;
        }

        // Don't re-derive if already in a terminal state matching
        if ($this->signal->status === $derived) {
            return;
        }

        $reasons = $this->signal->actions
            ->whereIn('status', ['dismissed', 'applied'])
            ->map(fn ($a) => trim((string) $a->decision_reason))
            ->filter(fn ($r) => $r !== '')
            ->values()
            ->implode('; ');

        // For resolved auto-derive: build a "what was done" summary from applied actions
        $appliedSummary = $this->signal->actions
            ->where('status', 'applied')
            ->map(function ($a) {
                $line = '• ' . $a->title;
                $note = trim((string) $a->decision_reason);
                return $note !== '' ? $line . ': ' . $note : $line;
            })
            ->values()
            ->implode("\n");

        $ackAt = $this->signal->acknowledged_at ?? now();

        match ($derived) {
            'resolved' => $this->signal->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
                'resolution_summary' => $appliedSummary ?: null,
                'acknowledged_at' => $ackAt,
            ]),
            'dismissed' => $this->signal->update([
                'status' => 'dismissed',
                'dismissed_reason' => $reasons ?: null,
                'acknowledged_at' => $ackAt,
            ]),
            'acknowledged' => $this->signal->update([
                'status' => 'acknowledged',
                'acknowledged_at' => $ackAt,
            ]),
        };

        $derivedFeedbackAction = match ($derived) {
            'resolved' => 'resolve',
            'dismissed' => 'dismiss',
            'acknowledged' => 'acknowledge',
        };

        $this->processSignalFeedback($derivedFeedbackAction, $reasons);
        $this->signal->refresh();
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

    // --- Notes (Activity Log) ---

    public function addNote(): void
    {
        $this->validate(['newNote' => 'required|string|max:1000']);
        $this->signal->logActivity($this->newNote);
        $this->newNote = '';
        unset($this->signalActivities);
    }

    // --- Comments ---

    public function addComment(): void
    {
        $content = trim($this->newComment);
        if ($content === '') {
            return;
        }

        OrganizationSignalComment::create([
            'signal_id' => $this->signal->id,
            'user_id' => auth()->id(),
            'author_context' => 'user',
            'content' => $content,
        ]);

        $this->newComment = '';
        unset($this->signalComments);
    }

    public function startReply(int $commentId): void
    {
        $this->replyingTo = $commentId;
        $this->replyBody = '';
    }

    public function cancelReply(): void
    {
        $this->replyingTo = null;
        $this->replyBody = '';
    }

    public function submitReply(): void
    {
        $content = trim($this->replyBody);
        if ($content === '' || ! $this->replyingTo) {
            return;
        }

        // Verify parent comment belongs to this signal
        $parent = OrganizationSignalComment::where('id', $this->replyingTo)
            ->where('signal_id', $this->signal->id)
            ->first();

        if (! $parent) {
            return;
        }

        OrganizationSignalComment::create([
            'signal_id' => $this->signal->id,
            'parent_id' => $this->replyingTo,
            'user_id' => auth()->id(),
            'author_context' => 'user',
            'content' => $content,
        ]);

        $this->replyingTo = null;
        $this->replyBody = '';
        unset($this->signalComments);
    }

    // --- Snooze ---

    public function openSnooze(): void
    {
        $this->showSnoozeModal = true;
        $this->snoozeDuration = '3d';
        $this->snoozeCustomDate = '';
    }

    public function cancelSnooze(): void
    {
        $this->showSnoozeModal = false;
    }

    public function confirmSnooze(): void
    {
        if ($this->snoozeDuration === 'custom' && $this->snoozeCustomDate) {
            $snoozeUntil = \Carbon\Carbon::parse($this->snoozeCustomDate)->endOfDay();
        } else {
            $snoozeUntil = match ($this->snoozeDuration) {
                '1d' => now()->addDay(),
                '3d' => now()->addDays(3),
                '1w' => now()->addWeek(),
                '2w' => now()->addWeeks(2),
                '1m' => now()->addMonth(),
                default => now()->addDays(3),
            };
        }

        $this->signal->update(['snooze_until' => $snoozeUntil]);

        // System comment
        OrganizationSignalComment::create([
            'signal_id' => $this->signal->id,
            'user_id' => auth()->id(),
            'author_context' => 'system',
            'content' => 'Wiedervorlage: ' . $snoozeUntil->format('d.m.Y H:i'),
        ]);

        $this->showSnoozeModal = false;
        $this->signal->refresh();
        unset($this->signalComments);
    }

    public function cancelSnoozeActive(): void
    {
        $this->signal->update(['snooze_until' => null]);

        OrganizationSignalComment::create([
            'signal_id' => $this->signal->id,
            'user_id' => auth()->id(),
            'author_context' => 'system',
            'content' => 'Wiedervorlage aufgehoben.',
        ]);

        $this->signal->refresh();
        unset($this->signalComments);
    }

    public function render()
    {
        return view('organization::livewire.signal.show')
            ->layout('platform::layouts.app');
    }
}
