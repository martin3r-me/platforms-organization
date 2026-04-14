<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'SLA-Verträge'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer SLA-Vertrag</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" placeholder="Suche SLA-Verträge..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="showInactive" id="showInactive" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="showInactive" class="ml-2 text-sm text-[var(--ui-secondary)]">Inaktive anzeigen</label>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Gesamt</span>
                            <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->stats['total'] }}</span>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Aktiv</span>
                            <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->stats['active'] }}</span>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Inaktiv</span>
                            <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->stats['inactive'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-[var(--ui-success-10)] border border-[var(--ui-success)]/30 text-[var(--ui-success)] rounded-lg">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 p-4 bg-[var(--ui-danger-10)] border border-[var(--ui-danger)]/30 text-[var(--ui-danger)] rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        @php $tree = $this->itemTree; @endphp

        @if(count($tree) === 0 && $this->slaContracts->isEmpty())
            <div class="text-center text-[var(--ui-muted)] py-12">Keine SLA-Verträge gefunden.</div>
        @else
            <div class="space-y-1">
                @foreach($tree as $node)
                    @include('organization::livewire.partials.entity-tree-node', ['node' => $node, 'itemPartial' => 'organization::livewire.sla-contract.partials.tree-item'])
                @endforeach
            </div>
        @endif
    </x-ui-page-container>

    <!-- Create SLA Contract Modal -->
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">
            Neuen SLA-Vertrag erstellen
        </x-slot>

        <div class="space-y-4">
            <form wire:submit.prevent="createSlaContract" class="space-y-4">
                <x-ui-input-text
                    name="name"
                    label="Name"
                    wire:model.live="newSlaContract.name"
                    required
                    placeholder="Name des SLA-Vertrags"
                />

                <x-ui-input-textarea
                    name="description"
                    label="Beschreibung"
                    wire:model.live="newSlaContract.description"
                    placeholder="Optionale Beschreibung"
                    rows="3"
                />

                <div class="grid grid-cols-3 gap-4">
                    <x-ui-input-text
                        name="response_time_hours"
                        label="Reaktionszeit (Std.)"
                        type="number"
                        wire:model.live="newSlaContract.response_time_hours"
                        placeholder="z.B. 4"
                        min="1"
                    />

                    <x-ui-input-text
                        name="resolution_time_hours"
                        label="Lösungszeit (Std.)"
                        type="number"
                        wire:model.live="newSlaContract.resolution_time_hours"
                        placeholder="z.B. 24"
                        min="1"
                    />

                    <x-ui-input-text
                        name="error_tolerance_percent"
                        label="Fehlertoleranz (%)"
                        type="number"
                        wire:model.live="newSlaContract.error_tolerance_percent"
                        placeholder="z.B. 5"
                        min="0"
                        max="100"
                    />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">Owner (Entity)</label>
                    <select wire:model.live="newSlaContract.owner_entity_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">– Kein Owner –</option>
                        @foreach($this->availableEntities as $entity)
                            <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" wire:model.live="newSlaContract.is_active" id="is_active_new" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                    <label for="is_active_new" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                </div>
            </form>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeCreateModal">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="createSlaContract">
                    @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                    Erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
