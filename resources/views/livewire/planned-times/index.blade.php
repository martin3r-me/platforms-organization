<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Geplante Zeiten" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text wire:model.live="search" name="search" placeholder="Suche nach Notizen, Benutzer, Team..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <x-ui-input-select wire:model.live="selectedTeamId" name="selectedTeamId" label="Team" :options="$this->availableTeams->pluck('name', 'id')->toArray()" :nullable="true" nullLabel="– Alle Teams –" size="sm" />
                        <x-ui-input-select wire:model.live="selectedUserId" name="selectedUserId" label="Benutzer" :options="$this->availableUsers->pluck('name', 'id')->toArray()" :nullable="true" nullLabel="– Alle Benutzer –" size="sm" />
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="showInactiveOnly" id="showInactiveOnly" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="showInactiveOnly" class="ml-2 text-sm text-[var(--ui-secondary)]">Nur inaktive</label>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-[var(--ui-muted)]">Gesamt geplant</span>
                                <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ number_format($this->totalPlannedMinutes / 60, 2, ',', '.') }}h</span>
                            </div>
                            <div class="text-xs text-[var(--ui-muted)]">{{ number_format($this->totalPlannedMinutes, 0, ',', '.') }} Minuten</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[var(--ui-muted)]">Einträge</span>
                                <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->plannedTimes->count() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Benutzer</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Team</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Kontext</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Geplante Zeit</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Notiz</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Erstellt</x-ui-table-header-cell>
            </x-ui-table-header>
            
            <x-ui-table-body>
                @forelse($this->plannedTimes as $planned)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <div class="flex items-center gap-2">
                                @if($planned->user && $planned->user->avatar)
                                    <img src="{{ $planned->user->avatar }}" alt="{{ $planned->user->name ?? 'User' }}" class="w-8 h-8 rounded-full object-cover" />
                                @else
                                    <div class="w-8 h-8 rounded-full bg-[var(--ui-primary-5)] flex items-center justify-center text-xs font-medium text-[var(--ui-primary)]">
                                        {{ strtoupper(substr($planned->user->name ?? 'U', 0, 1)) }}
                                    </div>
                                @endif
                                <div>
                                    <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                        {{ $planned->user->name ?? 'Unbekannt' }}
                                    </div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $planned->user->email ?? '' }}
                                    </div>
                                </div>
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm text-[var(--ui-secondary)]">
                                {{ $planned->team->name ?? 'Unbekannt' }}
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($planned->context)
                                <div class="text-sm text-[var(--ui-secondary)]">
                                    {{ class_basename($planned->context_type) }}
                                </div>
                                <div class="text-xs text-[var(--ui-muted)]">
                                    {{ $planned->context->name ?? $planned->context->title ?? 'Unbekannt' }}
                                </div>
                            @else
                                <span class="text-xs text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                {{ number_format($planned->planned_minutes / 60, 2, ',', '.') }}h
                            </div>
                            <div class="text-xs text-[var(--ui-muted)]">
                                {{ number_format($planned->planned_minutes, 0, ',', '.') }} Min
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($planned->is_active)
                                <x-ui-badge variant="success" size="xs">Aktiv</x-ui-badge>
                            @else
                                <x-ui-badge variant="secondary" size="xs">Inaktiv</x-ui-badge>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($planned->note)
                                <div class="text-sm text-[var(--ui-secondary)] max-w-xs truncate" title="{{ $planned->note }}">
                                    {{ Str::limit($planned->note, 50) }}
                                </div>
                            @else
                                <span class="text-xs text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="text-sm text-[var(--ui-secondary)]">
                                {{ $planned->created_at->format('d.m.Y') }}
                            </div>
                            <div class="text-xs text-[var(--ui-muted)]">
                                {{ $planned->created_at->format('H:i') }}
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="7" class="text-center py-8">
                            <div class="text-sm text-[var(--ui-muted)]">
                                Keine geplanten Zeiten gefunden.
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>

