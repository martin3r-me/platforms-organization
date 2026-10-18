<?php

namespace Platform\Organization\Livewire\Terminal;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;
use Platform\Organization\Authz\DimensionLinkAuthorizer;
use Platform\Organization\Services\DimensionLinkService;

/**
 * Terminal-Panel: DimensionLink-Verknüpfungen des aktuellen Kontext-Objekts.
 *
 * Bekommt contextType (FQCN) + contextId über das Trait WithTerminalContext
 * (Event 'terminal-context-changed' bzw. Mount-Parameter vom Terminal-Shell).
 * v1: Forward-Lesen je Dimension + Unlink. Add-Link-Flow folgt in v2.
 */
class DimensionLinks extends Component
{
    use WithTerminalContext;

    /** Computed-Cache leeren, wenn sich der Kontext ändert. */
    protected function onContextChanged(): void
    {
        unset($this->groups, $this->canWrite);
    }

    /**
     * Darf der aktuelle User die Verknüpfungen dieses Objekts verändern?
     * Maßgeblich ist Write auf das Kontext-Objekt (wie die update()-Policy).
     */
    #[Computed]
    public function canWrite(): bool
    {
        $user = auth()->user();
        if (! $user || ! $this->hasContext()) {
            return false;
        }

        return (new DimensionLinkAuthorizer())
            ->canWriteContext($user, $this->contextType, (int) $this->contextId);
    }

    /**
     * Verknüpfungen des aktuellen Objekts, gruppiert je Dimension.
     * Entity-Links werden auf Entities gefiltert, die der User sehen darf.
     * Dimensionen ohne (sichtbare) Links werden weggelassen.
     *
     * @return array<int, array{key:string, label:string, mode:string, items:array}>
     */
    #[Computed]
    public function groups(): array
    {
        $user = auth()->user();
        if (! $user || ! $this->hasContext()) {
            return [];
        }

        // Nur Entities, die der User lesen darf (Read-Filter). Als Set für O(1).
        $readable = array_flip((new DimensionLinkAuthorizer())->readableEntityIds($user));

        $service = new DimensionLinkService();
        $groups = [];

        foreach (DimensionLinkService::getDimensions() as $key => $cfg) {
            $items = $service->getLinked($key, $this->contextType, (int) $this->contextId)
                ->filter(function ($item) use ($readable) {
                    // Entity-basierte Links: nur zeigen, wenn Entity lesbar.
                    // Nicht-entity-Dimensionen (Enum-Werte) haben kein entity_id.
                    if (! array_key_exists('entity_id', $item)) {
                        return true;
                    }
                    $entityId = $item['entity_id'];

                    return $entityId !== null && isset($readable[$entityId]);
                })
                ->values();

            if ($items->isEmpty()) {
                continue;
            }

            $groups[] = [
                'key'   => $key,
                'label' => $cfg['label'] ?? $key,
                'mode'  => $cfg['mode'] ?? 'single',
                'items' => $items->all(),
            ];
        }

        return $groups;
    }

    /** Eine Verknüpfung entfernen. Gated: Write auf das Kontext-Objekt. */
    public function unlink(string $dimension, int $dimValueId): void
    {
        if (! $this->hasContext() || ! $this->canWrite) {
            return;
        }

        (new DimensionLinkService())->unlink(
            $dimension,
            $this->contextType,
            (int) $this->contextId,
            $dimValueId,
        );

        unset($this->groups);
    }

    public function render()
    {
        return view('organization::livewire.terminal.dimension-links');
    }
}
