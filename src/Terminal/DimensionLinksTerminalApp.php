<?php

namespace Platform\Organization\Terminal;

use Platform\Core\Terminal\Contracts\HasBadge;
use Platform\Core\Terminal\Contracts\TerminalApp;
use Platform\Core\Terminal\TerminalContext;
use Platform\Organization\Authz\DimensionLinkAuthorizer;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Services\DimensionLinkService;

/**
 * Terminal-App: zeigt die DimensionLink-Verknüpfungen des aktuell im Terminal
 * kontextualisierten Objekts (forward) und erlaubt deren Entfernen.
 *
 * Beigesteuert von platforms-organization über den TerminalAppRegistry — der
 * Dependency-Pfeil zeigt auf den TerminalApp-Contract in core, nie umgekehrt.
 * Nur verfügbar, wenn das Terminal auf ein konkretes Objekt gepinnt ist.
 */
class DimensionLinksTerminalApp implements TerminalApp, HasBadge
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
        // Im Kontext-Panel der erste Reiter
        return 5;
    }

    public function livewireComponent(): string
    {
        return 'organization.terminal.dimension-links';
    }

    public function isAvailable(TerminalContext $context): bool
    {
        if (! $context->hasContext()) {
            return false;
        }

        // Kontext-Panel (liefert apps): nur wenn die Seite Verknüpfungen anbietet (`links`,
        // Standard an) und nicht an organization-Modellen — dort ist der Datensatz selbst
        // die Einheit. Terminal liefert keine apps → bisheriges Verhalten.
        return $context->apps === []
            || ($context->offers('links') && ! str_starts_with((string) $context->contextType, 'Platform\\Organization\\'));
    }

    /**
     * Anzahl der Verknüpfungen fürs Kontext-Panel — gleiche Sicht wie der Reiter:
     * aktive Dimensionen, entity-basierte Links nur auf lesbare Einheiten.
     */
    public function badge(TerminalContext $context): ?string
    {
        $user = auth()->user();
        if (! $user || ! $context->hasContext()) {
            return null;
        }

        $defs = OrganizationDimensionDefinition::active()->get(['id', 'value_source'])->keyBy('id');
        if ($defs->isEmpty()) {
            return null;
        }

        $links = OrganizationDimensionLink::whereIn('dimension_definition_id', $defs->keys())
            ->where('linkable_type', DimensionLinkService::resolveContextType((string) $context->contextType))
            ->where('linkable_id', $context->contextId)
            ->with('value:id,metadata')
            ->get(['id', 'dimension_definition_id', 'dimension_value_id']);
        if ($links->isEmpty()) {
            return null;
        }

        $readable = null;
        $n = $links->filter(function ($link) use ($defs, $user, &$readable) {
            if ($defs[$link->dimension_definition_id]->value_source !== 'entity') {
                return true;
            }
            $readable ??= array_flip((new DimensionLinkAuthorizer())->readableEntityIds($user));
            $entityId = ($link->value?->metadata ?? [])['source_entity_id'] ?? null;

            return $entityId !== null && isset($readable[$entityId]);
        })->count();

        return $n > 0 ? ($n > 99 ? '99+' : (string) $n) : null;
    }
}
