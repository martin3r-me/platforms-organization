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
            <x-ui-table-header-cell compact="true">Übergeordnet</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Erstellt</x-ui-table-header-cell>
        </x-ui-table-header>
        
        <x-ui-table-body>
            @foreach($this->entities as $entity)
                <x-ui-table-row compact="true">
                    <x-ui-table-cell compact="true">
                        <div class="flex items-center">
                            @if($entity->type->icon)
                                @svg('heroicon-o-' . str_replace('heroicons.', '', $entity->type->icon), 'w-5 h-5 text-[var(--ui-muted)] mr-3')
                            @endif
                            <div>
                                <div class="font-medium">
                                    <a href="{{ route('organization.entities.show', $entity) }}" class="link">{{ $entity->name }}</a>
                                </div>
                                @if($entity->description)
                                    <div class="text-xs text-[var(--ui-muted)]">{{ Str::limit($entity->description, 50) }}</div>
                                @endif
                            </div>
                        </div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        <div class="text-sm">{{ $entity->type->name }}</div>
                        <div class="text-xs text-[var(--ui-muted)]">{{ $entity->type->group->name }}</div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @if($entity->vsmSystem)
                            <x-ui-badge variant="secondary" size="sm">{{ $entity->vsmSystem->name }}</x-ui-badge>
                        @else
                            <span class="text-xs text-[var(--ui-muted)]">–</span>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @if($entity->parent)
                            <div class="text-sm">{{ $entity->parent->name }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">{{ $entity->parent->type->name }}</div>
                        @else
                            <span class="text-xs text-[var(--ui-muted)]">–</span>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @if($entity->is_active)
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        <span class="text-xs text-[var(--ui-muted)]">{{ $entity->created_at->format('d.m.Y') }}</span>
                    </x-ui-table-cell>
                </x-ui-table-row>
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
