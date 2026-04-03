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

        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Reaktionszeit</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Lösungszeit</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Fehlertoleranz</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->slaContracts as $slaContract)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.sla-contracts.show', $slaContract) }}" class="font-semibold text-[var(--ui-primary)] hover:underline">
                                {{ $slaContract->name }}
                            </a>
                            @if($slaContract->description)
                                <div class="text-xs text-[var(--ui-muted)] truncate max-w-xs">{{ $slaContract->description }}</div>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($slaContract->response_time_hours)
                                <span class="text-sm text-[var(--ui-secondary)]">{{ $slaContract->response_time_hours }} h</span>
                            @else
                                <span class="text-xs text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($slaContract->resolution_time_hours)
                                <span class="text-sm text-[var(--ui-secondary)]">{{ $slaContract->resolution_time_hours }} h</span>
                            @else
                                <span class="text-xs text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($slaContract->error_tolerance_percent !== null)
                                <span class="text-sm text-[var(--ui-secondary)]">{{ $slaContract->error_tolerance_percent }}%</span>
                            @else
                                <span class="text-xs text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($slaContract->is_active)
                                <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                            @else
                                <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('organization.sla-contracts.show', $slaContract) }}">
                                    <x-ui-button variant="secondary-ghost" size="sm">
                                        @svg('heroicon-o-pencil', 'w-4 h-4')
                                    </x-ui-button>
                                </a>
                                <x-ui-confirm-button
                                    variant="danger-ghost"
                                    size="sm"
                                    wire:click="deleteSlaContract({{ $slaContract->id }})"
                                    confirm-text="SLA-Vertrag wirklich löschen?"
                                >
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </x-ui-confirm-button>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="6">
                            <div class="py-8 text-center text-sm text-[var(--ui-muted)]">
                                Keine SLA-Verträge gefunden.
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
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
