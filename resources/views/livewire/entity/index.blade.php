<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Einheiten'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Einheit</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" placeholder="Suche Organisationseinheiten..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <x-ui-input-select name="selectedGroup" label="Gruppe" :options="$this->entityTypeGroups" optionValue="id" optionLabel="name" :nullable="true" nullLabel="– Alle Gruppen –" size="sm" />
                        <x-ui-input-select name="selectedType" label="Typ" :options="$this->entityTypes->flatten()" optionValue="id" optionLabel="name" :nullable="true" nullLabel="– Alle Typen –" size="sm" />
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="showInactive" id="showInactive" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="showInactive" class="ml-2 text-sm text-[var(--ui-secondary)]">Inaktive anzeigen</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="onlyWithSignals" id="onlyWithSignals" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="onlyWithSignals" class="ml-2 text-sm text-[var(--ui-secondary)]">Nur mit Signalen</label>
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
        @php
            $rootsByGroup = $this->entities['rootsByGroup'];
            $childrenByParent = $this->entities['childrenByParent'];
        @endphp

        {{-- VSM-Klasse Legende --}}
        <div class="mb-4 flex items-center flex-wrap gap-3 px-3 py-2 bg-[var(--ui-muted-5)]/40 rounded-lg border border-[var(--ui-border)]/40">
            <span class="text-[10px] font-bold text-[var(--ui-muted)] uppercase tracking-wider">VSM-Klasse:</span>
            <div class="flex items-center gap-1.5" title="Carrier — lebensfähiges System, kann eigene Perspektive sein">
                <span class="inline-flex items-center justify-center w-4 h-4 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20">C</span>
                <span class="text-xs text-[var(--ui-secondary)]">Carrier · Perspektive möglich</span>
            </div>
            <div class="flex items-center gap-1.5" title="Actor — füllt VSM-Funktionen aus, empfängt Signale">
                <span class="inline-flex items-center justify-center w-4 h-4 rounded text-[10px] font-bold bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-600/15">A</span>
                <span class="text-xs text-[var(--ui-secondary)]">Actor · füllt Funktion aus</span>
            </div>
            <div class="flex items-center gap-1.5" title="Observed — Umwelt-Entity, wird beobachtet">
                <span class="inline-flex items-center justify-center w-4 h-4 rounded text-[10px] font-bold bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20">O</span>
                <span class="text-xs text-[var(--ui-secondary)]">Observed · Umwelt</span>
            </div>
        </div>

        @if($rootsByGroup->isEmpty())
            <div class="py-12 text-center">
                @svg('heroicon-o-building-office', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-3')
                <p class="text-sm text-[var(--ui-muted)]">Keine Einheiten gefunden.</p>
            </div>
        @else
            @foreach($rootsByGroup as $groupId => $groupEntities)
                @php $groupName = $groupEntities->first()->type->group->name ?? 'Sonstige'; @endphp
                <div class="mb-6">
                    {{-- Group Header --}}
                    <div class="flex items-center gap-2 mb-2 px-1">
                        <h3 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider">{{ $groupName }}</h3>
                        <span class="text-[10px] text-[var(--ui-muted)]">({{ $groupEntities->count() }})</span>
                    </div>

                    <x-ui-table compact="true">
                    <x-ui-table-header>
                        <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Relationen</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">VSM</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Signale</x-ui-table-header-cell>
                        <x-ui-table-header-cell compact="true">Bewegung</x-ui-table-header-cell>
                    </x-ui-table-header>

                    <x-ui-table-body>
                        @foreach($groupEntities as $entity)
                            @include('organization::livewire.entity.partials.tree-table-row', [
                                'entity' => $entity,
                                'depth' => 0,
                                'childrenByParent' => $childrenByParent,
                                'entityMovements' => $this->entityMovements,
                                'vsmSystemMap' => $this->vsmSystemMap,
                                'signalCounts' => $this->signalCounts,
                            ])
                        @endforeach
                    </x-ui-table-body>
                    </x-ui-table>
                </div>
            @endforeach
        @endif
    </x-ui-page-container>

    <!-- Create Entity Modal -->
    <x-ui-modal
        wire:model="modalShow"
        size="lg"
    >
            <x-slot name="header">
                Neue Organisationseinheit erstellen
            </x-slot>

            <div class="space-y-4">
                <form wire:submit.prevent="createEntity" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4">
                        <x-ui-input-text
                            name="name"
                            label="Name"
                            wire:model.live="newEntity.name"
                            required
                            placeholder="Name der Organisationseinheit"
                        />
                        
                        <x-ui-input-text
                            name="code"
                            label="Code (optional)"
                            wire:model.live="newEntity.code"
                            placeholder="Code oder Nummer"
                        />
                        
                        <x-ui-input-textarea
                            name="description"
                            label="Beschreibung"
                            wire:model.live="newEntity.description"
                            placeholder="Optionale Beschreibung der Einheit"
                            rows="3"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-ui-input-select
                            name="entity_type_id"
                            label="Typ"
                            :options="$this->entityTypes->flatten()"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="Typ auswählen"
                            wire:model.live="newEntity.entity_type_id"
                            required
                        />

                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <x-ui-input-select
                            name="parent_entity_id"
                            label="Übergeordnete Einheit (optional)"
                            :options="$this->parentEntities"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="Keine übergeordnete Einheit"
                            wire:model.live="newEntity.parent_entity_id"
                        />
                    </div>

                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            wire:model.live="newEntity.is_active" 
                            id="is_active"
                            class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                        />
                        <label for="is_active" class="ml-2 text-sm text-gray-700">Aktiv</label>
                    </div>
                </form>
            </div>

            <x-slot name="footer">
                <div class="d-flex justify-end gap-2">
                    <x-ui-button 
                        type="button" 
                        variant="secondary-outline" 
                        wire:click="closeCreateModal"
                    >
                        Abbrechen
                    </x-ui-button>
                    <x-ui-button type="button" variant="primary" wire:click="createEntity">
                        @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                        Erstellen
                    </x-ui-button>
                </div>
            </x-slot>
        </x-ui-modal>
</x-ui-page>
