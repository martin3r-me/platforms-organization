<?php

namespace Platform\Organization\Livewire\Terminal;

use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;

/**
 * Kontext-Panel: „In Parlan besprechen" — ein normaler Link auf organization.parlan.jump, der
 * beim Klick einen frischen, signierten Absprung erzeugt (5 Min, nur für diesen Nutzer) und
 * nach Parlan weiterleitet. Kein Chat im ERP.
 */
class ParlanConversation extends Component
{
    use WithTerminalContext;

    public function render()
    {
        return view('organization::livewire.terminal.parlan-conversation', [
            'jump' => $this->hasContext() ? route('organization.parlan.jump', array_filter([
                'type' => $this->contextType,
                'id' => $this->contextId,
                'title' => $this->contextSubject,
                'url' => $this->contextUrl,
            ])) : null,
        ]);
    }
}
