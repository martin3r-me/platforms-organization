<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$relationType->name" icon="heroicon-o-arrows-right-left" />
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
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-3">
                        @if($relationType->is_active)
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Eigenschaften</h3>
                    <div class="space-y-3">
                        <div class="flex items-center gap-1 flex-wrap">
                            @if($relationType->is_directional)
                                <x-ui-badge variant="info" size="sm">Direktional</x-ui-badge>
                            @endif
                            @if($relationType->is_hierarchical)
                                <x-ui-badge variant="warning" size="sm">Hierarchisch</x-ui-badge>
                            @endif
                            @if($relationType->is_reciprocal)
                                <x-ui-badge variant="secondary" size="sm">Reziprok</x-ui-badge>
                            @endif
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Code</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                <code>{{ $relationType->code }}</code>
                            </div>
                        </div>
                        @if($relationType->description)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Beschreibung</span>
                                <div class="text-sm text-[var(--ui-secondary)]">{{ $relationType->description }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $relationType->created_at->format('d.m.Y H:i') }}</div>
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
                    <x-ui-input-text name="code" label="Code" wire:model.live="form.code" required />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" />
                    <x-ui-input-text name="icon" label="Icon" wire:model.live="form.icon" placeholder="z.B. heroicon-o-arrow-right" />
                    <x-ui-input-number name="sort_order" label="Sortierung" wire:model.live="form.sort_order" min="0" />
                </div>
            </div>

            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Eigenschaften</h2>
                <div class="space-y-4">
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_directional" id="is_directional" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_directional" class="ml-2 text-sm text-[var(--ui-secondary)]">
                            Direktional
                            <span class="text-xs text-[var(--ui-muted)] block">Die Relation hat eine Richtung (von → nach)</span>
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_hierarchical" id="is_hierarchical" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_hierarchical" class="ml-2 text-sm text-[var(--ui-secondary)]">
                            Hierarchisch
                            <span class="text-xs text-[var(--ui-muted)] block">Die Relation bildet eine Hierarchie (Über-/Unterordnung)</span>
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_reciprocal" id="is_reciprocal" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_reciprocal" class="ml-2 text-sm text-[var(--ui-secondary)]">
                            Reziprok
                            <span class="text-xs text-[var(--ui-muted)] block">Die Relation gilt in beide Richtungen gleichermaßen</span>
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
