<?php

namespace Platform\Organization\Livewire\Signal;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSignal;

class Show extends Component
{
    public OrganizationSignal $signal;
    public string $newNote = '';

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

    public function acknowledgeSignal(): void
    {
        $this->signal->update(['status' => 'acknowledged']);
        $this->signal->refresh();
        unset($this->signalActivities);
    }

    public function resolveSignal(): void
    {
        $this->signal->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);
        $this->signal->refresh();
        unset($this->signalActivities);
    }

    public function dismissSignal(): void
    {
        $this->signal->update(['status' => 'dismissed']);
        $this->signal->refresh();
        unset($this->signalActivities);
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
