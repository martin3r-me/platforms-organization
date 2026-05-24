<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationCostCenterLink;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationDimensionDefinition;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationDimensionValue;

class DimensionLinker extends Component
{
    public ?string $contextType = null;
    public ?int $contextId = null;
    public string $dimension = '';

    public string $search = '';
    public array $linkedItems = [];
    public array $availableItems = [];

    // Prozent-Editing (für multi_percent Mode)
    public array $percentages = [];

    /**
     * Dimension Registry mit Mode:
     * - single: nur 1 Link erlaubt (attach ersetzt vorherigen)
     * - multi: mehrere Links, kein Prozent
     * - multi_percent: mehrere Links mit Prozent-Verteilung
     */
    protected function getDimensionConfig(): array
    {
        return [
            'cost-centers' => [
                'model' => OrganizationCostCenter::class,
                'link_model' => OrganizationCostCenterLink::class,
                'fk' => 'cost_center_id',
                'label' => 'Kostenstellen',
                'icon' => 'heroicon-o-currency-dollar',
                'mode' => 'multi_percent',
                'generic' => false,
            ],
            'entities' => [
                'model' => OrganizationEntity::class,
                'label' => 'Organisationseinheiten',
                'icon' => 'heroicon-o-building-office',
                'mode' => 'multi',
                'generic' => true,
                'dimension_key' => 'entity',
            ],
        ];
    }

    protected function config(): ?array
    {
        return $this->getDimensionConfig()[$this->dimension] ?? null;
    }

    public function mount(?string $contextType = null, ?int $contextId = null, string $dimension = ''): void
    {
        $this->contextType = $contextType;
        $this->contextId = $contextId;
        $this->dimension = $dimension;

        if ($this->contextType && $this->contextId && $this->dimension) {
            $this->loadLinkedItems();
            $this->loadAvailableItems();
        }
    }

    public function getMode(): string
    {
        return $this->config()['mode'] ?? 'multi';
    }

    public function getLabel(): string
    {
        return $this->config()['label'] ?? $this->dimension;
    }

    public function getIcon(): string
    {
        return $this->config()['icon'] ?? 'heroicon-o-link';
    }

    public function getPercentSum(): float
    {
        return array_sum($this->percentages);
    }

    protected function isGeneric(): bool
    {
        return ($this->config()['generic'] ?? false) === true;
    }

    protected function getDefinitionId(): ?int
    {
        $cfg = $this->config();
        if (!$cfg || !$this->isGeneric()) {
            return null;
        }

        return OrganizationDimensionDefinition::where('key', $cfg['dimension_key'])->value('id');
    }

    public function loadLinkedItems(): void
    {
        $cfg = $this->config();
        if (!$cfg) {
            $this->linkedItems = [];
            return;
        }

        if ($this->isGeneric()) {
            $this->loadLinkedItemsGeneric();
            return;
        }

        $linkModel = $cfg['link_model'];
        $fk = $cfg['fk'];

        $links = $linkModel::where('linkable_type', $this->contextType)
            ->where('linkable_id', $this->contextId)
            ->get();

        $dimensionModel = $cfg['model'];
        $linksByFk = $links->keyBy($fk);
        $ids = $links->pluck($fk)->unique()->toArray();

        $items = $dimensionModel::whereIn('id', $ids)->orderBy('name')->get();

        $this->linkedItems = $items->map(function ($item) use ($linksByFk, $fk) {
            $link = $linksByFk->get($item->id);
            return [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
                'link_id' => $link?->id,
                'percentage' => $link?->percentage ? (float) $link->percentage : null,
                'is_primary' => (bool) ($link?->is_primary ?? false),
            ];
        })->toArray();

        // Prozent-Map aufbauen
        $this->percentages = [];
        foreach ($this->linkedItems as $item) {
            if ($item['percentage'] !== null) {
                $this->percentages[$item['id']] = $item['percentage'];
            }
        }
    }

    protected function loadLinkedItemsGeneric(): void
    {
        $defId = $this->getDefinitionId();
        if (!$defId) {
            $this->linkedItems = [];
            return;
        }

        $links = OrganizationDimensionLink::where('dimension_definition_id', $defId)
            ->where('linkable_type', $this->contextType)
            ->where('linkable_id', $this->contextId)
            ->with('value')
            ->get();

        // Resolve source entities from dimension values
        $entityIds = $links->map(fn ($l) => $l->value?->metadata['source_entity_id'] ?? null)->filter()->unique()->toArray();
        $entities = OrganizationEntity::whereIn('id', $entityIds)->orderBy('name')->get()->keyBy('id');

        $linksByEntityId = [];
        foreach ($links as $link) {
            $eid = $link->value?->metadata['source_entity_id'] ?? null;
            if ($eid) {
                $linksByEntityId[$eid] = $link;
            }
        }

        $this->linkedItems = $entities->map(function ($entity) use ($linksByEntityId) {
            $link = $linksByEntityId[$entity->id] ?? null;
            return [
                'id' => $entity->id,
                'code' => $entity->code,
                'name' => $entity->name,
                'link_id' => $link?->id,
                'percentage' => $link?->percentage ? (float) $link->percentage : null,
                'is_primary' => (bool) ($link?->is_primary ?? false),
            ];
        })->values()->toArray();

        $this->percentages = [];
        foreach ($this->linkedItems as $item) {
            if ($item['percentage'] !== null) {
                $this->percentages[$item['id']] = $item['percentage'];
            }
        }
    }

    public function loadAvailableItems(): void
    {
        $cfg = $this->config();
        if (!$cfg) {
            $this->availableItems = [];
            return;
        }

        // Im single-Mode: keine Available Items wenn schon einer verlinkt ist
        if ($cfg['mode'] === 'single' && count($this->linkedItems) > 0 && $this->search === '') {
            $this->availableItems = [];
            return;
        }

        $teamId = auth()->user()->currentTeam->id;
        $dimensionModel = $cfg['model'];

        $linkedIds = collect($this->linkedItems)->pluck('id')->toArray();

        $query = $dimensionModel::where('team_id', $teamId)
            ->where('is_active', true)
            ->whereNotIn('id', $linkedIds);

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        $this->availableItems = $query->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'code' => $item->code,
                'name' => $item->name,
            ])->toArray();
    }

    public function attach(int $id): void
    {
        $cfg = $this->config();
        if (!$cfg) {
            return;
        }

        if ($this->isGeneric()) {
            $this->attachGeneric($id);
            return;
        }

        $linkModel = $cfg['link_model'];
        $fk = $cfg['fk'];

        // Single-Mode: vorherigen Link entfernen
        if ($cfg['mode'] === 'single') {
            $linkModel::where('linkable_type', $this->contextType)
                ->where('linkable_id', $this->contextId)
                ->delete();
        }

        // Duplikat-Check
        $exists = $linkModel::where($fk, $id)
            ->where('linkable_type', $this->contextType)
            ->where('linkable_id', $this->contextId)
            ->exists();

        if (!$exists) {
            $linkModel::create([
                $fk => $id,
                'linkable_type' => $this->contextType,
                'linkable_id' => $this->contextId,
                'team_id' => auth()->user()->currentTeam->id,
                'created_by_user_id' => auth()->id(),
            ]);
        }

        $this->search = '';
        $this->loadLinkedItems();
        $this->loadAvailableItems();
    }

    protected function attachGeneric(int $entityId): void
    {
        $defId = $this->getDefinitionId();
        if (!$defId) {
            return;
        }

        $cfg = $this->config();

        // Single-Mode: vorherigen Link entfernen
        if ($cfg['mode'] === 'single') {
            OrganizationDimensionLink::where('dimension_definition_id', $defId)
                ->where('linkable_type', $this->contextType)
                ->where('linkable_id', $this->contextId)
                ->delete();
        }

        // Get or create dimension value for entity
        $dvId = \Platform\Organization\Services\EntityDimensionBridge::dimValueId($entityId);
        if (!$dvId) {
            // Auto-create
            $entity = OrganizationEntity::find($entityId);
            if (!$entity) {
                return;
            }
            $dv = OrganizationDimensionValue::create([
                'dimension_definition_id' => $defId,
                'code' => $entity->code ?? "entity-{$entityId}",
                'name' => $entity->name,
                'team_id' => $entity->team_id,
                'is_active' => true,
                'metadata' => ['source_entity_id' => $entityId],
            ]);
            $dvId = $dv->id;
            \Platform\Organization\Services\EntityDimensionBridge::flush();
        }

        // Duplikat-Check
        $exists = OrganizationDimensionLink::where('dimension_definition_id', $defId)
            ->where('dimension_value_id', $dvId)
            ->where('linkable_type', $this->contextType)
            ->where('linkable_id', $this->contextId)
            ->exists();

        if (!$exists) {
            OrganizationDimensionLink::create([
                'dimension_definition_id' => $defId,
                'dimension_value_id' => $dvId,
                'linkable_type' => $this->contextType,
                'linkable_id' => $this->contextId,
                'team_id' => auth()->user()->currentTeam->id,
                'created_by_user_id' => auth()->id(),
            ]);
        }

        $this->search = '';
        $this->loadLinkedItems();
        $this->loadAvailableItems();
    }

    public function detach(int $id): void
    {
        $cfg = $this->config();
        if (!$cfg) {
            return;
        }

        if ($this->isGeneric()) {
            $this->detachGeneric($id);
            return;
        }

        $linkModel = $cfg['link_model'];
        $fk = $cfg['fk'];

        $linkModel::where($fk, $id)
            ->where('linkable_type', $this->contextType)
            ->where('linkable_id', $this->contextId)
            ->delete();

        unset($this->percentages[$id]);

        $this->loadLinkedItems();
        $this->loadAvailableItems();
    }

    protected function detachGeneric(int $entityId): void
    {
        $defId = $this->getDefinitionId();
        if (!$defId) {
            return;
        }

        $dvId = \Platform\Organization\Services\EntityDimensionBridge::dimValueId($entityId);
        if ($dvId) {
            OrganizationDimensionLink::where('dimension_definition_id', $defId)
                ->where('dimension_value_id', $dvId)
                ->where('linkable_type', $this->contextType)
                ->where('linkable_id', $this->contextId)
                ->delete();
        }

        unset($this->percentages[$entityId]);

        $this->loadLinkedItems();
        $this->loadAvailableItems();
    }

    public function savePercentage(int $itemId, $value): void
    {
        $cfg = $this->config();
        if (!$cfg || $cfg['mode'] !== 'multi_percent') {
            return;
        }

        $percentage = $value !== '' && $value !== null ? round((float) $value, 2) : null;

        if ($this->isGeneric()) {
            $defId = $this->getDefinitionId();
            $dvId = \Platform\Organization\Services\EntityDimensionBridge::dimValueId($itemId);
            if ($defId && $dvId) {
                OrganizationDimensionLink::where('dimension_definition_id', $defId)
                    ->where('dimension_value_id', $dvId)
                    ->where('linkable_type', $this->contextType)
                    ->where('linkable_id', $this->contextId)
                    ->update(['percentage' => $percentage]);
            }
        } else {
            $linkModel = $cfg['link_model'];
            $fk = $cfg['fk'];
            $linkModel::where($fk, $itemId)
                ->where('linkable_type', $this->contextType)
                ->where('linkable_id', $this->contextId)
                ->update(['percentage' => $percentage]);
        }

        if ($percentage !== null) {
            $this->percentages[$itemId] = $percentage;
        } else {
            unset($this->percentages[$itemId]);
        }
    }

    public function togglePrimary(int $itemId): void
    {
        $cfg = $this->config();
        if (!$cfg) {
            return;
        }

        if ($this->isGeneric()) {
            $defId = $this->getDefinitionId();
            if (!$defId) {
                return;
            }

            // Erst alle is_primary auf false für diesen Kontext
            OrganizationDimensionLink::where('dimension_definition_id', $defId)
                ->where('linkable_type', $this->contextType)
                ->where('linkable_id', $this->contextId)
                ->update(['is_primary' => false]);

            // Dann den gewählten auf true
            $dvId = \Platform\Organization\Services\EntityDimensionBridge::dimValueId($itemId);
            if ($dvId) {
                OrganizationDimensionLink::where('dimension_definition_id', $defId)
                    ->where('dimension_value_id', $dvId)
                    ->where('linkable_type', $this->contextType)
                    ->where('linkable_id', $this->contextId)
                    ->update(['is_primary' => true]);
            }
        } else {
            $linkModel = $cfg['link_model'];
            $fk = $cfg['fk'];

            // Erst alle is_primary auf false für diesen Kontext
            $linkModel::where('linkable_type', $this->contextType)
                ->where('linkable_id', $this->contextId)
                ->update(['is_primary' => false]);

            // Dann den gewählten auf true
            $linkModel::where($fk, $itemId)
                ->where('linkable_type', $this->contextType)
                ->where('linkable_id', $this->contextId)
                ->update(['is_primary' => true]);
        }

        $this->loadLinkedItems();
    }

    public function updatedSearch(): void
    {
        $this->loadAvailableItems();
    }

    public function render()
    {
        return view('organization::livewire.dimension-linker');
    }
}
