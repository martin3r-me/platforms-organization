<?php

namespace Platform\Organization\Terminal;

use Platform\Core\Terminal\Contracts\TerminalApp;
use Platform\Core\Terminal\TerminalContext;

/**
 * Terminal-App: zeigt die DimensionLink-Verknüpfungen des aktuell im Terminal
 * kontextualisierten Objekts (forward) und erlaubt deren Entfernen.
 *
 * Beigesteuert von platforms-organization über den TerminalAppRegistry — der
 * Dependency-Pfeil zeigt auf den TerminalApp-Contract in core, nie umgekehrt.
 * Nur verfügbar, wenn das Terminal auf ein konkretes Objekt gepinnt ist.
 */
class DimensionLinksTerminalApp implements TerminalApp
{
    public function key(): string
    {
        return 'dimension-links';
    }

    public function label(): string
    {
        return 'Verknüpfungen';
    }

    public function icon(): string
    {
        return 'link';
    }

    public function group(): string
    {
        return 'module';
    }

    public function order(): int
    {
        return 50;
    }

    public function livewireComponent(): string
    {
        return 'organization.terminal.dimension-links';
    }

    public function isAvailable(TerminalContext $context): bool
    {
        return $context->hasContext();
    }
}
