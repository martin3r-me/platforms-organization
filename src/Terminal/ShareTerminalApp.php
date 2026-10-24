<?php

namespace Platform\Organization\Terminal;

use Platform\Core\Terminal\Contracts\HasBadge;
use Platform\Core\Terminal\Contracts\TerminalApp;
use Platform\Core\Terminal\TerminalContext;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityShare;

/**
 * Kontext-Panel-Reiter „Freigabe" auf einer Einheit: Knoten nach außen teilen
 * (Kunden-Tor) — URL, Eingeladene, was sichtbar ist.
 */
class ShareTerminalApp implements TerminalApp, HasBadge
{
    public function key(): string
    {
        return 'share';
    }

    public function label(): string
    {
        return 'Freigabe';
    }

    public function icon(): string
    {
        return 'share';
    }

    public function group(): string
    {
        return 'module';
    }

    public function order(): int
    {
        return 45;
    }

    public function livewireComponent(): string
    {
        return 'organization.terminal.share';
    }

    public function isAvailable(TerminalContext $context): bool
    {
        return $context->hasContext() && $context->apps !== []
            && $context->contextType === OrganizationEntity::class;
    }

    /** Aktive Freigaben dieses Knotens. */
    public function badge(TerminalContext $context): ?string
    {
        if (! $this->isAvailable($context)) {
            return null;
        }
        $n = OrganizationEntityShare::where('entity_id', $context->contextId)->active()->count();

        return $n > 0 ? (string) $n : null;
    }
}
