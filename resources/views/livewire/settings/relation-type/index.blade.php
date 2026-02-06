<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Relation Types" icon="heroicon-o-arrows-right-left" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Relation Types..." class="w-full" size="sm" />
                        <x-ui-button variant="secondary" size="sm" wire:click="create" class="w-full justify-start">
                            @svg('heroicon-o-plus','w-4 h-4')
                            <span class="ml-2">Neuer Relation Type</span>
                        </x-ui-button>
                    </div>
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
                <x-ui-table-header-cell compact="true">Eigenschaften</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @foreach($this->relationTypes as $relationType)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <code class="text-xs bg-[var(--ui-muted-5)] px-2 py-1 rounded">{{ $relationType->code }}</code>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.relation-types.show', $relationType) }}" class="link font-medium">
                                {{ $relationType->name }}
                            </a>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex items-center gap-1">
                                @if($relationType->is_directional)
                                    <x-ui-badge variant="info" size="xs">Direktional</x-ui-badge>
                                @endif
                                @if($relationType->is_hierarchical)
                                    <x-ui-badge variant="warning" size="xs">Hierarchisch</x-ui-badge>
                                @endif
                                @if($relationType->is_reciprocal)
                                    <x-ui-badge variant="secondary" size="xs">Reziprok</x-ui-badge>
                                @endif
                                @if(!$relationType->is_directional && !$relationType->is_hierarchical && !$relationType->is_reciprocal)
                                    <span class="text-[var(--ui-muted)]">–</span>
                                @endif
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $relationType->is_active ? 'success' : 'muted' }}">
                                {{ $relationType->is_active ? 'Aktiv' : 'Inaktiv' }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.relation-types.show', $relationType) }}" class="text-[var(--ui-primary)] hover:underline text-sm">
                                Bearbeiten
                            </a>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <!-- Create Relation Type Modal -->
    <x-ui-modal
        wire:model="modalShow"
        size="lg"
    >
        <x-slot name="header">
            Neuen Relation Type erstellen
        </x-slot>

        <div class="space-y-4">
            <form wire:submit.prevent="store" class="space-y-4">
                <x-ui-input-text
                    name="name"
                    label="Name"
                    wire:model.live="form.name"
                    required
                    placeholder="Name des Relation Types"
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
                    placeholder="z.B. heroicon-o-arrow-right"
                />

                <x-ui-input-number
                    name="sort_order"
                    label="Sortierung"
                    wire:model.live="form.sort_order"
                    min="0"
                />

                <div class="space-y-3">
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_directional" id="create_is_directional" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="create_is_directional" class="ml-2 text-sm text-[var(--ui-secondary)]">
                            Direktional
                            <span class="text-xs text-[var(--ui-muted)] block">Die Relation hat eine Richtung (von → nach)</span>
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_hierarchical" id="create_is_hierarchical" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="create_is_hierarchical" class="ml-2 text-sm text-[var(--ui-secondary)]">
                            Hierarchisch
                            <span class="text-xs text-[var(--ui-muted)] block">Die Relation bildet eine Hierarchie (Über-/Unterordnung)</span>
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_reciprocal" id="create_is_reciprocal" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="create_is_reciprocal" class="ml-2 text-sm text-[var(--ui-secondary)]">
                            Reziprok
                            <span class="text-xs text-[var(--ui-muted)] block">Die Relation gilt in beide Richtungen gleichermaßen</span>
                        </label>
                    </div>
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
