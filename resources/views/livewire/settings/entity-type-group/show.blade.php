<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$entityTypeGroup->name" icon="heroicon-o-rectangle-group" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <!-- Action Buttons -->
                <div class="pb-4 border-b border-[var(--ui-border)]/40 space-y-2">
                    @if($this->isDirty)
                        <div class="flex space-x-2">
                            <x-ui-button variant="secondary-outline" wire:click="loadForm" size="sm" class="flex-1">
                                @svg('heroicon-o-x-mark', 'w-4 h-4 mr-2')
                                Abbrechen
                            </x-ui-button>
                            <x-ui-button variant="primary" wire:click="save" size="sm" class="flex-1">
                                @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                                Speichern
                            </x-ui-button>
                        </div>
                    @endif
                    <x-ui-confirm-button
                        variant="danger-outline"
                        size="sm"
                        wire:click="delete"
                        confirm-text="Entity Type Group wirklich löschen?"
                        class="w-full justify-center"
                    >
                        @svg('heroicon-o-trash', 'w-4 h-4 mr-2')
                        Löschen
                    </x-ui-confirm-button>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-3">
                        @if($entityTypeGroup->is_active)
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        @if($entityTypeGroup->description)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Beschreibung</span>
                                <div class="text-sm text-[var(--ui-secondary)]">{{ $entityTypeGroup->description }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entityTypeGroup->created_at->format('d.m.Y H:i') }}</div>
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
        <div class="space-y-6">
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                <div class="space-y-4">
                    <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" />
                    <x-ui-input-number name="sort_order" label="Sortierung" wire:model.live="form.sort_order" min="0" />
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Zugehörige Entity Types</h2>
                @if($this->entityTypes->count() > 0)
                    <x-ui-table compact="true">
                        <x-ui-table-header>
                            <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                            <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                        </x-ui-table-header>
                        <x-ui-table-body>
                            @foreach($this->entityTypes as $entityType)
                                <x-ui-table-row compact="true">
                                    <x-ui-table-cell compact="true">
                                        <a href="{{ route('organization.settings.entity-types.show', $entityType) }}" class="link font-medium">
                                            {{ $entityType->name }}
                                        </a>
                                    </x-ui-table-cell>
                                    <x-ui-table-cell compact="true">
                                        <x-ui-badge variant="{{ $entityType->is_active ? 'success' : 'muted' }}">
                                            {{ $entityType->is_active ? 'Aktiv' : 'Inaktiv' }}
                                        </x-ui-badge>
                                    </x-ui-table-cell>
                                </x-ui-table-row>
                            @endforeach
                        </x-ui-table-body>
                    </x-ui-table>
                @else
                    <p class="text-sm text-[var(--ui-muted)]">Keine Entity Types in dieser Gruppe.</p>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
