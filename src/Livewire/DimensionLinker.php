<?php

namespace Platform\Organization\Livewire;

use Livewire\Component;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Models\OrganizationCostCenterLink;
use Platform\Organization\Models\OrganizationCustomer;
use Platform\Organization\Models\OrganizationCustomerLink;
use Platform\Organization\Models\OrganizationPerson;
use Platform\Organization\Models\OrganizationPersonLink;

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
            ],
            'customers' => [
                'model' => OrganizationCustomer::class,
                'link_model' => OrganizationCustomerLink::class,
                'fk' => 'customer_id',
                'label' => 'Kunden',
                'icon' => 'heroicon-o-user-group',
                'mode' => 'single',
            ],
            'persons' => [
                'model' => OrganizationPerson::class,
                'link_model' => OrganizationPersonLink::class,
                'fk' => 'person_id',
                'label' => 'Personen',
                'icon' => 'heroicon-o-user',
                'mode' => 'multi',
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

    public function loadLinkedItems(): void
    {
        $cfg = $this->config();
        if (!$cfg) {
            $this->linkedItems = [];
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

    public function detach(int $id): void
    {
        $cfg = $this->config();
        if (!$cfg) {
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

    public function savePercentage(int $itemId, $value): void
    {
        $cfg = $this->config();
        if (!$cfg || $cfg['mode'] !== 'multi_percent') {
            return;
        }

        $linkModel = $cfg['link_model'];
        $fk = $cfg['fk'];
        $percentage = $value !== '' && $value !== null ? round((float) $value, 2) : null;

        $linkModel::where($fk, $itemId)
            ->where('linkable_type', $this->contextType)
            ->where('linkable_id', $this->contextId)
            ->update(['percentage' => $percentage]);

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
