<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'SLA-Verträge', 'href' => route('organization.sla-contracts.index')],
            ['label' => $slaContract->name],
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
                confirm-text="SLA-Vertrag wirklich löschen?"
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
                        @if($slaContract->is_active)
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">SLA-Werte</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Reaktionszeit</span>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">
                                {{ $slaContract->response_time_hours ? $slaContract->response_time_hours . ' h' : '–' }}
                            </div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Lösungszeit</span>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">
                                {{ $slaContract->resolution_time_hours ? $slaContract->resolution_time_hours . ' h' : '–' }}
                            </div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Fehlertoleranz</span>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">
                                {{ $slaContract->error_tolerance_percent !== null ? $slaContract->error_tolerance_percent . '%' : '–' }}
                            </div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Verknüpfte Interlinks</span>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->linkedInterlinks->count() }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $slaContract->created_at->format('d.m.Y H:i') }}</div>
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
                wire:click="$set('activeTab', 'interlinks')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'interlinks' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' }}"
            >
                Verknüpfte Interlinks
                <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">{{ $this->linkedInterlinks->count() }}</span>
            </button>
        </div>

        @if($activeTab === 'details')
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                <div class="space-y-4">
                    <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" />

                    <div class="grid grid-cols-3 gap-4">
                        <x-ui-input-text
                            name="response_time_hours"
                            label="Reaktionszeit (Std.)"
                            type="number"
                            wire:model.live="form.response_time_hours"
                            min="1"
                        />

                        <x-ui-input-text
                            name="resolution_time_hours"
                            label="Lösungszeit (Std.)"
                            type="number"
                            wire:model.live="form.resolution_time_hours"
                            min="1"
                        />

                        <x-ui-input-text
                            name="error_tolerance_percent"
                            label="Fehlertoleranz (%)"
                            type="number"
                            wire:model.live="form.error_tolerance_percent"
                            min="0"
                            max="100"
                        />
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                    </div>
                </div>
            </div>
        @endif

        @if($activeTab === 'interlinks')
            <div class="space-y-3">
                @forelse($this->linkedInterlinks as $pivot)
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
                                            @if($pivot->interlink)
                                                &middot; {{ $pivot->interlink->name }}
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
                            @svg('heroicon-o-shield-check', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine verknüpften Interlinks</p>
                        <p class="text-xs text-[var(--ui-muted)]">Dieser SLA-Vertrag ist noch keinem Relationship-Interlink zugeordnet.</p>
                    </div>
                @endforelse
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
