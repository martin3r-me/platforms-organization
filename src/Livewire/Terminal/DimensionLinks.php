<?php

namespace Platform\Organization\Livewire\Terminal;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;
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
        unset($this->groups);
    }

    /**
     * Verknüpfungen des aktuellen Objekts, gruppiert je Dimension.
     * Dimensionen ohne Links werden weggelassen.
     *
     * @return array<int, array{key:string, label:string, mode:string, items:array}>
     */
    #[Computed]
    public function groups(): array
    {
        if (! $this->hasContext()) {
            return [];
        }

        $service = new DimensionLinkService();
        $groups = [];

        foreach (DimensionLinkService::getDimensions() as $key => $cfg) {
            $linked = $service->getLinked($key, $this->contextType, (int) $this->contextId);

            if ($linked->isEmpty()) {
                continue;
            }

            $groups[] = [
                'key'   => $key,
                'label' => $cfg['label'] ?? $key,
                'mode'  => $cfg['mode'] ?? 'single',
                'items' => $linked->values()->all(),
            ];
        }

        return $groups;
    }

    /** Eine Verknüpfung entfernen. */
    public function unlink(string $dimension, int $dimValueId): void
    {
        if (! $this->hasContext()) {
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
