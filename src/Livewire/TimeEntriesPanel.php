<?php

namespace Platform\Organization\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Organization\Models\OrganizationTimeEntry;

class TimeEntriesPanel extends Component
{
    public string $contextType;
    public int $contextId;

    public ?int $teamId = null;

    public array $linkedContexts = [];

    public ?int $plannedMinutes = null;

    public $entries;

    public function mount($context, array $linkedContexts = [], ?int $plannedMinutes = null): void
    {
        $this->contextType = get_class($context);
        $this->contextId = $context->getKey();
        $this->teamId = property_exists($context, 'team_id') ? (int) ($context->team_id ?? null) : null;
        $this->linkedContexts = $linkedContexts;
        $this->plannedMinutes = $plannedMinutes;

        $this->loadEntries();
    }

    #[On('time-entry:saved')]
    public function reload(): void
    {
        $this->loadEntries();
    }

    protected function loadEntries(): void
    {
        $this->entries = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->with('user')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
    }

    public function getTotalMinutesProperty(): int
    {
        return (int) OrganizationTimeEntry::query()->forContextKey($this->contextType, $this->contextId)->sum('minutes');
    }

    public function getBilledMinutesProperty(): int
    {
        return (int) OrganizationTimeEntry::query()->forContextKey($this->contextType, $this->contextId)->where('is_billed', true)->sum('minutes');
    }

    public function getUnbilledMinutesProperty(): int
    {
        return max(0, $this->totalMinutes - $this->billedMinutes);
    }

    public function getUnbilledAmountCentsProperty(): int
    {
        return (int) OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->where('is_billed', false)
            ->sum('amount_cents');
    }

    public function openModal(): void
    {
        $payload = [
            'context_type' => $this->contextType,
            'context_id' => $this->contextId,
            'linked_contexts' => $this->linkedContexts,
        ];

        $this->dispatch('time-entry:open', $payload);
    }

    public function toggleBilled(int $entryId): void
    {
        $entry = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->findOrFail($entryId);

        $user = Auth::user();
        $team = $user?->currentTeamRelation; // Child-Team (nicht dynamisch)

        if (! $team || $entry->team_id !== $team->id) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sie haben keine Berechtigung für diesen Eintrag.',
            ]);
            return;
        }

        $entry->is_billed = ! $entry->is_billed;
        $entry->save();

        $this->loadEntries();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $entry->is_billed ? 'Eintrag als abgerechnet markiert.' : 'Eintrag wieder auf offen gesetzt.',
        ]);
    }

    public function deleteEntry(int $entryId): void
    {
        $entry = OrganizationTimeEntry::query()
            ->forContextKey($this->contextType, $this->contextId)
            ->findOrFail($entryId);

        $user = Auth::user();
        $team = $user?->currentTeamRelation; // Child-Team (nicht dynamisch)

        if (! $team || $entry->team_id !== $team->id) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sie haben keine Berechtigung für diesen Eintrag.',
            ]);
            return;
        }

        $entry->delete();
        $this->loadEntries();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Zeiteintrag gelöscht.',
        ]);
    }

    public function render()
    {
        return view('organization::livewire.time-entries-panel', [
            'entries' => $this->entries,
        ]);
    }
}

