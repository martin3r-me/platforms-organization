<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Settings'],
            ['label' => 'Entity Types'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Entity Type</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Entity Types..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Gruppe</label>
                            <select 
                                name="selectedGroup"
                                wire:model.live="selectedGroup"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="">Alle Gruppen</option>
                                @foreach($this->entityTypeGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="showInactive" id="showInactive" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="showInactive" class="ml-2 text-sm text-[var(--ui-secondary)]">Inaktive anzeigen</label>
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
                <x-ui-table-header-cell compact="true">Code</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Gruppe</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @foreach($this->entityTypes as $entityType)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <code class="text-xs bg-[var(--ui-muted-5)] px-2 py-1 rounded">{{ $entityType->code }}</code>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.entity-types.show', $entityType) }}" class="link font-medium">
                                {{ $entityType->name }}
                            </a>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($entityType->group)
                                <x-ui-badge variant="info">{{ $entityType->group->name }}</x-ui-badge>
                            @else
                                <span class="text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $entityType->is_active ? 'success' : 'muted' }}">
                                {{ $entityType->is_active ? 'Aktiv' : 'Inaktiv' }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.entity-types.show', $entityType) }}" class="text-[var(--ui-primary)] hover:underline text-sm">
                                Bearbeiten
                            </a>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <!-- Create Entity Type Modal -->
    <x-ui-modal
        wire:model="modalShow"
        size="lg"
    >
        <x-slot name="header">
            Neuen Entity Type erstellen
        </x-slot>

        <div class="space-y-4">
            <form wire:submit.prevent="store" class="space-y-4">
                <x-ui-input-text
                    name="name"
                    label="Name"
                    wire:model.live="form.name"
                    required
                    placeholder="Name des Entity Types"
                />

                <x-ui-input-text
                    name="code"
                    label="Code"
                    wire:model.live="form.code"
                    required
                    placeholder="Eindeutiger Code"
                />

                <x-ui-input-textarea
                    name="description"
                    label="Beschreibung"
                    wire:model.live="form.description"
                    placeholder="Optionale Beschreibung"
                    rows="3"
                />

                <x-ui-input-text
                    name="icon"
                    label="Icon"
                    wire:model.live="form.icon"
                    placeholder="z.B. heroicon-o-cube"
                />

                <x-ui-input-number
                    name="sort_order"
                    label="Sortierung"
                    wire:model.live="form.sort_order"
                    min="0"
                />

                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Gruppe</label>
                    <select
                        name="entity_type_group_id"
                        wire:model.live="form.entity_type_group_id"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                    >
                        <option value="">Keine Gruppe</option>
                        @foreach($this->entityTypeGroups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" wire:model.live="form.is_active" id="create_is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                    <label for="create_is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                </div>
            </form>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button
                    type="button"
                    variant="secondary-outline"
                    wire:click="$set('modalShow', false)"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="store">
                    @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                    Erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>

