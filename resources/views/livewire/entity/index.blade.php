<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Organisationseinheiten" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-input-text name="search" placeholder="Suche Organisationseinheiten..." class="w-full" size="sm" />
                        <x-ui-button variant="secondary" size="sm" wire:click="openCreateModal" class="w-full justify-start">
                            @svg('heroicon-o-plus','w-4 h-4')
                            <span class="ml-2">Neue Einheit</span>
                        </x-ui-button>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <x-ui-input-select name="selectedGroup" label="Gruppe" :options="$this->entityTypeGroups" optionValue="id" optionLabel="name" :nullable="true" nullLabel="– Alle Gruppen –" size="sm" />
                        <x-ui-input-select name="selectedType" label="Typ" :options="$this->entityTypes->flatten()" optionValue="id" optionLabel="name" :nullable="true" nullLabel="– Alle Typen –" size="sm" />
                        <x-ui-input-select name="vsmSystem" label="VSM System" :options="$this->vsmSystems" optionValue="id" optionLabel="name" :nullable="true" nullLabel="– Alle Systeme –" size="sm" />
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
        <x-ui-table compact="true">
        <x-ui-table-header>
            <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">VSM System</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Kostenstelle</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Übergeordnet</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Relationen</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Erstellt</x-ui-table-header-cell>
        </x-ui-table-header>
        
        <x-ui-table-body>
            {{-- Root Entities (ohne Parent) --}}
            @if($this->entities['root']->count() > 0)
                @foreach($this->entities['root'] as $entity)
                    @include('organization::livewire.entity.partials.table-row', ['entity' => $entity])
                @endforeach
            @endif
            
            {{-- Child Entities gruppiert nach Entity-Typ --}}
            @foreach($this->entities['byType'] as $entityTypeId => $entities)
                @php
                    $firstEntity = $entities->first();
                    $entityType = $firstEntity->type;
                @endphp
                <x-ui-table-row compact="true" class="bg-[var(--ui-muted-5)]/30">
                    <x-ui-table-cell compact="true" colspan="8">
                        <div class="flex items-center gap-2 py-2">
                            @if($entityType->icon)
                                @php
                                    $iconName = str_replace('heroicons.', '', $entityType->icon);
                                    $iconMap = [
                                        'user-check' => 'user',
                                        'folder-kanban' => 'folder',
                                        'briefcase-globe' => 'briefcase',
                                        'server-cog' => 'server',
                                        'package-check' => 'package',
                                        'badge-check' => 'badge',
                                    ];
                                    $iconName = $iconMap[$iconName] ?? $iconName;
                                @endphp
                                @svg('heroicon-o-' . $iconName, 'w-4 h-4 text-[var(--ui-muted)]')
                            @endif
                            <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $entityType->name }}</span>
                            <span class="text-xs text-[var(--ui-muted)]">({{ $entityType->group->name }})</span>
                            <span class="text-xs text-[var(--ui-muted)]">– {{ $entities->count() }} {{ $entities->count() === 1 ? 'Entity' : 'Entities' }}</span>
                        </div>
                    </x-ui-table-cell>
                </x-ui-table-row>
                @foreach($entities as $entity)
                    @include('organization::livewire.entity.partials.table-row', ['entity' => $entity])
                @endforeach
            @endforeach
        </x-ui-table-body>
        </x-ui-table>
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

                        <x-ui-input-select
                            name="vsm_system_id"
                            label="VSM System (optional)"
                            :options="$this->vsmSystems"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="Kein VSM System"
                            wire:model.live="newEntity.vsm_system_id"
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
