<?php

namespace Platform\Organization\Terminal;

use Platform\Core\Terminal\Contracts\TerminalApp;
use Platform\Core\Terminal\TerminalContext;
use Platform\Organization\Support\ParlanContext;

/**
 * Kontext-Panel-Reiter „Gespräch": springt in den Parlan-Kontext-Raum des Objekts. Im ERP wird
 * nicht gechattet — nur ausgelöst und der Ref gehalten. Ausgeblendet ohne Parlan-Konfiguration.
 */
class ParlanConversationTerminalApp implements TerminalApp
{
    public function key(): string
    {
        return 'parlan-conversation';
    }

    public function label(): string
    {
        return 'Gespräch';
    }

    public function icon(): string
    {
        return 'chat-bubble-left-right';
    }

    public function group(): string
    {
        return 'module';
    }

    public function order(): int
    {
        return 6;
    }

    public function livewireComponent(): string
    {
        return 'organization.terminal.parlan-conversation';
    }

    public function isAvailable(TerminalContext $context): bool
    {
        // Nur im Kontext-Panel (liefert apps), nicht im alten Terminal mit eigenem Chat.
        return $context->hasContext() && $context->apps !== [] && ParlanContext::enabled()
            && ParlanContext::alias((string) $context->contextType) !== null;
    }
}
