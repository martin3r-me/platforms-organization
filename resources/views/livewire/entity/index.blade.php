<div>
    <div class="h-full overflow-y-auto p-6">
        <!-- Header mit Datum -->
        <div class="mb-6">
            <div class="d-flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Organisationseinheiten</h1>
                    <p class="text-gray-600">{{ now()->format('l') }}, {{ now()->format('d.m.Y') }}</p>
                </div>
                <x-ui-button variant="primary" wire:click="openCreateModal">
                    @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                    Neue Einheit
                </x-ui-button>
            </div>
        </div>

        <!-- Haupt-Statistiken (4x1 Grid) -->
        <div class="grid grid-cols-4 gap-4 mb-8">
            <x-ui-dashboard-tile
                title="Gesamt"
                :count="$this->stats['total']"
                icon="building-office"
                variant="primary"
                size="lg"
            />
            
            <x-ui-dashboard-tile
                title="Aktiv"
                :count="$this->stats['active']"
                icon="check-circle"
                variant="success"
                size="lg"
            />
            
            <x-ui-dashboard-tile
                title="Inaktiv"
                :count="$this->stats['inactive']"
                icon="x-circle"
                variant="danger"
                size="lg"
            />
            
            <x-ui-dashboard-tile
                title="Typen"
                :count="$this->entityTypes->flatten()->count()"
                icon="squares-2x2"
                variant="secondary"
                size="lg"
            />
        </div>

        <!-- Filter und Suche -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Suche -->
                    <div>
                        <x-ui-label for="search" text="Suche" />
                        <x-ui-input-text 
                            name="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Name oder Beschreibung..." 
                            class="w-full"
                        />
                    </div>

                    <!-- Entity Type Group Filter -->
                    <div>
                        <x-ui-label for="group" text="Gruppe" />
                        <x-ui-input-select name="selectedGroup" wire:model.live="selectedGroup" class="w-full">
                            <option value="">Alle Gruppen</option>
                            @foreach($this->entityTypeGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </x-ui-input-select>
                    </div>

                    <!-- Entity Type Filter -->
                    <div>
                        <x-ui-label for="type" text="Typ" />
                        <x-ui-input-select name="selectedType" wire:model.live="selectedType" class="w-full">
                            <option value="">Alle Typen</option>
                            @foreach($this->entityTypes as $groupName => $types)
                                <optgroup label="{{ $groupName }}">
                                    @foreach($types as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </x-ui-input-select>
                    </div>

                    <!-- Inaktive anzeigen -->
                    <div class="flex items-end">
                        <input 
                            type="checkbox" 
                            wire:model.live="showInactive" 
                            id="showInactive"
                            class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                        />
                        <label for="showInactive" class="ml-2 text-sm text-gray-700">
                            Inaktive anzeigen
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Entities Tabelle -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-500 border-b border-gray-200 text-xs uppercase tracking-wide">
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Typ</th>
                            <th class="px-6 py-3">VSM System</th>
                            <th class="px-6 py-3">Übergeordnet</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Erstellt</th>
                            <th class="px-6 py-3 text-right">Aktionen</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse ($this->entities as $entity)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        @if($entity->type->icon)
                                            @svg('heroicon-o-' . str_replace('heroicons.', '', $entity->type->icon), 'w-5 h-5 text-gray-400 mr-3')
                                        @endif
                                        <div>
                                            <div class="font-medium text-gray-900">
                                                {{ $entity->name }}
                                            </div>
                                            @if($entity->description)
                                                <div class="text-sm text-gray-500">
                                                    {{ Str::limit($entity->description, 50) }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">{{ $entity->type->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $entity->type->group->name }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($entity->vsmSystem)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $entity->vsmSystem->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($entity->parent)
                                        <div class="text-sm text-gray-900">{{ $entity->parent->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $entity->parent->type->name }}</div>
                                    @else
                                        <span class="text-gray-400">–</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if($entity->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Aktiv
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Inaktiv
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $entity->created_at->format('d.m.Y') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <!-- Edit button wird später implementiert -->
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        @svg('heroicon-o-building-office', 'w-12 h-12 text-gray-300 mb-4')
                                        <p class="text-lg font-medium">Keine Organisationseinheiten gefunden</p>
                                        <p class="text-sm">Erstellen Sie Ihre erste Einheit um zu beginnen.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create Entity Modal -->
        <x-ui-modal
            wire:model="createModalShow"
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
    </div>
</div>
