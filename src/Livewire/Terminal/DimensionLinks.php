<?php

namespace Platform\Organization\Livewire\Terminal;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Core\Livewire\Terminal\Concerns\WithTerminalContext;
use Platform\Organization\Authz\DimensionLinkAuthorizer;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionValue;
use Platform\Organization\Models\OrganizationEntity;
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

    /** Suchbegriff des Add-Link-Pickers (entity-Dimension). */
    public string $search = '';

    /** Computed-Cache leeren, wenn sich der Kontext ändert. */
    protected function onContextChanged(): void
    {
        $this->search = '';
        unset($this->groups, $this->canWrite, $this->candidates);
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

        unset($this->groups, $this->candidates);
    }

    /**
     * Add-Link-Picker (entity-Dimension): Organisationseinheiten, die der User
     * beschreiben darf, optional nach Suchbegriff gefiltert, bereits verknüpfte
     * ausgeschlossen. Leere Suche → kompakte Vorschlagsliste.
     *
     * @return array<int, array{id:int, name:string, code:?string}>
     */
    #[Computed]
    public function candidates(): array
    {
        $user = auth()->user();
        if (! $user || ! $this->hasContext() || ! $this->canWrite) {
            return [];
        }

        $writable = (new DimensionLinkAuthorizer())->writableEntityIds($user);
        if ($writable === []) {
            return [];
        }

        // Bereits verknüpfte Entities ausschließen.
        $linked = (new DimensionLinkService())
            ->getLinked('entity', $this->contextType, (int) $this->contextId)
            ->pluck('entity_id')
            ->filter()
            ->all();

        $search = trim($this->search);
        $limit = $search !== '' ? 20 : 8;

        $query = OrganizationEntity::query()
            ->whereIn('id', $writable)
            ->where('is_active', true)
            ->when($linked !== [], fn ($q) => $q->whereNotIn('id', $linked))
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            }))
            ->orderBy('name')
            ->limit($limit);

        return $query->get(['id', 'name', 'code'])
            ->map(fn ($e) => ['id' => (int) $e->id, 'name' => $e->name, 'code' => $e->code])
            ->all();
    }

    /** Eine Organisationseinheit verknüpfen. Gated: Write auf Objekt + Entity. */
    public function addLink(int $entityId): void
    {
        $user = auth()->user();
        if (! $user || ! $this->hasContext() || ! $this->canWrite) {
            return;
        }

        // Nur Entities, die der User beschreiben darf.
        if (! in_array($entityId, (new DimensionLinkAuthorizer())->writableEntityIds($user), true)) {
            return;
        }

        $def = OrganizationDimensionDefinition::findByKey('entity');
        if (! $def || $def->value_source !== 'entity') {
            return;
        }

        $dimValue = OrganizationDimensionValue::where('dimension_definition_id', $def->id)
            ->where('metadata->source_entity_id', $entityId)
            ->first();
        if (! $dimValue) {
            return;
        }

        (new DimensionLinkService())->link(
            'entity',
            $this->contextType,
            (int) $this->contextId,
            $dimValue->id,
            [
                'team_id'            => $user->currentTeam?->id,
                'created_by_user_id' => $user->id,
            ],
        );

        $this->search = '';
        unset($this->groups, $this->candidates);
    }

    public function render()
    {
        return view('organization::livewire.terminal.dimension-links');
    }
}
