<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Organization Dashboard" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-button variant="secondary" size="sm" :href="route('organization.entities.index')" wire:navigate class="w-full">
                            <span class="flex items-center gap-2">
                                @svg('heroicon-o-building-office','w-4 h-4')
                                Organisationseinheiten
                            </span>
                        </x-ui-button>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Gesamt Entitäten</span>
                            <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->totalEntities }}</span>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Aktive Entitäten</span>
                            <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->activeEntities }}</span>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Root-Entitäten</span>
                            <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->rootEntities }}</span>
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
        <!-- Haupt-Statistiken -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-8">
            <x-ui-dashboard-tile title="Alle Entitäten" :count="$this->totalEntities" icon="building-office" variant="primary" size="lg" :href="route('organization.entities.index')" />
            <x-ui-dashboard-tile title="Aktive Entitäten" :count="$this->activeEntities" icon="check-circle" variant="success" size="lg" />
        </div>

        <!-- Verteilung nach Typen + Neueste Entitäten -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-ui-panel title="Verteilung nach Entitätstyp" subtitle="Nach Typ gruppiert">
                <div class="space-y-2">
                    @php($byType = $this->entitiesByType)
                    @forelse(($byType ?? collect())->take(5) as $row)
                        <div class="group flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 flex items-center justify-center text-xs font-semibold text-[var(--ui-secondary)]">
                                    @svg('heroicon-o-building-office','w-4 h-4')
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $row->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">Typ-ID: {{ $row->id }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $row->count }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-[var(--ui-muted)] p-4 text-center">Keine Entitäten vorhanden.</div>
                    @endforelse
                </div>
            </x-ui-panel>

            <x-ui-panel title="Neueste Entitäten" subtitle="Top 5">
                <div class="space-y-2">
                    @php($recent = $this->recentEntities)
                    @forelse(($recent ?? collect())->take(5) as $entity)
                        <div class="group flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-2 h-2 rounded-full {{ $entity->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $entity->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $entity->type?->name ?? 'Typ' }}
                                        @if($entity->vsmSystem)
                                            • {{ $entity->vsmSystem->name }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <x-ui-badge variant="secondary" size="sm">{{ $entity->created_at?->format('d.m.Y') }}</x-ui-badge>
                        </div>
                    @empty
                        <div class="text-sm text-[var(--ui-muted)] p-4 text-center">Keine Entitäten vorhanden.</div>
                    @endforelse
                </div>
            </x-ui-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>