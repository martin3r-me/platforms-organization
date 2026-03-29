<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Interlinks', 'href' => route('organization.interlinks.index')],
            ['label' => $interlink->name],
        ]">
            @if($this->isDirty)
                <x-ui-button variant="secondary-ghost" size="sm" wire:click="loadForm">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </x-ui-button>
            @endif
            <x-ui-confirm-button
                variant="danger-outline"
                size="sm"
                wire:click="delete"
                confirm-text="Interlink wirklich löschen?"
            >
                @svg('heroicon-o-trash', 'w-4 h-4')
            </x-ui-confirm-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-2">
                        @if($interlink->is_active)
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                        @endif
                        @if($interlink->is_bidirectional)
                            <x-ui-badge variant="info" size="sm">Bidirektional</x-ui-badge>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        @if($interlink->category)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Kategorie</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $interlink->category->name }}</div>
                            </div>
                        @endif
                        @if($interlink->type)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Typ</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $interlink->type->name }}</div>
                            </div>
                        @endif
                        @if($interlink->valid_from || $interlink->valid_to)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Gültigkeit</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                    {{ $interlink->valid_from?->format('d.m.Y') ?? '–' }}
                                    →
                                    {{ $interlink->valid_to?->format('d.m.Y') ?? '–' }}
                                </div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Verknüpfte Relationships</span>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->linkedRelationships->count() }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $interlink->created_at->format('d.m.Y H:i') }}</div>
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
        <!-- Tabs -->
        <div class="flex items-center gap-1 mb-6 border-b border-[var(--ui-border)]/60">
            <button
                wire:click="$set('activeTab', 'details')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'details' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
            >
                Details
            </button>
            <button
                wire:click="$set('activeTab', 'relationships')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'relationships' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
            >
                Verknüpfte Relationships
                <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $this->linkedRelationships->count() }}</span>
            </button>
        </div>

        @if($activeTab === 'details')
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                <div class="space-y-4">
                    <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" />

                    <div class="grid grid-cols-2 gap-4">
                        <x-ui-input-select
                            name="category_id"
                            label="Kategorie"
                            :options="$this->availableCategories"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="Kategorie auswählen"
                            wire:model.live="form.category_id"
                            required
                        />

                        <x-ui-input-select
                            name="type_id"
                            label="Typ"
                            :options="$this->availableTypes"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="Typ auswählen"
                            wire:model.live="form.type_id"
                            required
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">Gültig von (optional)</label>
                            <x-ui-input-text name="valid_from" type="date" wire:model.live="form.valid_from" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">Gültig bis (optional)</label>
                            <x-ui-input-text name="valid_to" type="date" wire:model.live="form.valid_to" />
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="form.is_bidirectional" id="is_bidirectional" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="is_bidirectional" class="ml-2 text-sm text-[var(--ui-secondary)]">Bidirektional</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($activeTab === 'relationships')
            <div class="space-y-3">
                @forelse($this->linkedRelationships as $pivot)
                    @php $rel = $pivot->entityRelationship; @endphp
                    @if($rel)
                        <div class="flex items-center justify-between p-4 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-lg bg-[var(--ui-primary-5)] flex items-center justify-center">
                                            @svg('heroicon-o-link', 'w-5 h-5 text-[var(--ui-primary)]')
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-[var(--ui-secondary)]">
                                            {{ $rel->fromEntity?->name ?? 'Unbekannt' }}
                                            <span class="text-[var(--ui-muted)] font-normal">{{ $rel->relationType?->name ?? '–' }}</span>
                                            {{ $rel->toEntity?->name ?? 'Unbekannt' }}
                                        </div>
                                        <div class="text-xs text-[var(--ui-muted)] mt-0.5">
                                            {{ $rel->fromEntity?->type?->name ?? '' }} → {{ $rel->toEntity?->type?->name ?? '' }}
                                            @if($pivot->note)
                                                &middot; {{ $pivot->note }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                            @svg('heroicon-o-link', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine verknüpften Relationships</p>
                        <p class="text-xs text-[var(--ui-muted)]">Dieser Interlink wird noch in keiner Entity-Relationship verwendet.</p>
                    </div>
                @endforelse
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
