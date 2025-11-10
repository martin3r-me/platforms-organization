<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Ist-Zeiten" />
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
                        <x-ui-input-date wire:model.live="dateFrom" name="dateFrom" label="Von" size="sm" />
                        <x-ui-input-date wire:model.live="dateTo" name="dateTo" label="Bis" size="sm" />
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="showBilledOnly" id="showBilledOnly" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="showBilledOnly" class="ml-2 text-sm text-[var(--ui-secondary)]">Nur abgerechnete</label>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Statistiken</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-[var(--ui-muted)]">Gesamt Stunden</span>
                                <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ number_format($this->totalMinutes / 60, 2, ',', '.') }}h</span>
                            </div>
                            <div class="text-xs text-[var(--ui-muted)]">{{ number_format($this->totalMinutes / 480, 2, ',', '.') }} Tage</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-[var(--ui-muted)]">Abgerechnet</span>
                                <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ number_format($this->totalBilledMinutes / 60, 2, ',', '.') }}h</span>
                            </div>
                            <div class="text-xs text-[var(--ui-muted)]">{{ number_format($this->totalBilledMinutes / 480, 2, ',', '.') }} Tage</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-[var(--ui-muted)]">Gesamt Betrag</span>
                                <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ number_format($this->totalAmountCents / 100, 2, ',', '.') }} €</span>
                            </div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-[var(--ui-muted)]">Abgerechnet Betrag</span>
                                <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ number_format($this->totalBilledAmountCents / 100, 2, ',', '.') }} €</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        {{-- Zeitraum Anzeige --}}
        <div class="mb-6 p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    @svg('heroicon-o-calendar', 'w-5 h-5 text-[var(--ui-muted)]')
                    <span class="text-sm font-semibold text-[var(--ui-secondary)]">Zeitraum:</span>
                    <span class="text-sm text-[var(--ui-secondary)]">
                        {{ \Carbon\Carbon::parse($dateFrom)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d.m.Y') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Team Dashboard Tiles --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            @foreach($this->timeEntriesGroupedByTeamAndRoot as $teamGroup)
                @php
                    $teamBilledMinutes = collect($teamGroup['root_groups'])->flatMap(function($rootGroup) {
                        return $rootGroup['entries']->where('is_billed', true);
                    })->sum('minutes');
                    $teamUnbilledMinutes = $teamGroup['total_minutes'] - $teamBilledMinutes;
                    $teamBilledAmountCents = collect($teamGroup['root_groups'])->flatMap(function($rootGroup) {
                        return $rootGroup['entries']->where('is_billed', true);
                    })->sum('amount_cents');
                @endphp
                <div class="p-6 bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-xl border border-[var(--ui-border)]/60 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-[var(--ui-primary-10)] rounded-lg flex items-center justify-center">
                                @svg('heroicon-o-user-group', 'w-5 h-5 text-[var(--ui-primary)]')
                            </div>
                            <h3 class="text-lg font-bold text-[var(--ui-secondary)]">{{ $teamGroup['team_name'] }}</h3>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-[var(--ui-muted)] uppercase tracking-wide">Gesamt</span>
                            <div class="text-right">
                                <div class="text-xl font-bold text-[var(--ui-secondary)]">{{ number_format($teamGroup['total_minutes'] / 60, 2, ',', '.') }}h</div>
                                <div class="text-xs text-[var(--ui-muted)]">{{ number_format($teamGroup['total_minutes'] / 480, 2, ',', '.') }} Tage</div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between pt-2 border-t border-[var(--ui-border)]/40">
                            <span class="text-xs font-semibold text-[var(--ui-success)] uppercase tracking-wide">Abgerechnet</span>
                            <div class="text-right">
                                <div class="text-lg font-bold text-[var(--ui-success)]">{{ number_format($teamBilledMinutes / 60, 2, ',', '.') }}h</div>
                                <div class="text-xs text-[var(--ui-success)]/70">{{ number_format($teamBilledMinutes / 480, 2, ',', '.') }} Tage</div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between pt-2 border-t border-[var(--ui-border)]/40">
                            <span class="text-xs font-semibold text-[var(--ui-warning)] uppercase tracking-wide">Offen</span>
                            <div class="text-right">
                                <div class="text-lg font-bold text-[var(--ui-warning)]">{{ number_format($teamUnbilledMinutes / 60, 2, ',', '.') }}h</div>
                                <div class="text-xs text-[var(--ui-warning)]/70">{{ number_format($teamUnbilledMinutes / 480, 2, ',', '.') }} Tage</div>
                            </div>
                        </div>
                        
                        @if($teamGroup['total_amount_cents'] > 0)
                            <div class="flex items-center justify-between pt-2 border-t border-[var(--ui-border)]/40">
                                <span class="text-xs font-semibold text-[var(--ui-primary)] uppercase tracking-wide">Betrag</span>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-[var(--ui-primary)]">{{ number_format($teamGroup['total_amount_cents'] / 100, 2, ',', '.') }} €</div>
                                    <div class="text-xs text-[var(--ui-primary)]/70">{{ number_format($teamBilledAmountCents / 100, 2, ',', '.') }} € abgerechnet</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @forelse($this->timeEntriesGroupedByTeamAndRoot as $teamGroup)
            <div class="mb-12">
                {{-- Team Header --}}
                <div class="mb-6 p-5 bg-[var(--ui-primary-5)] rounded-lg border-2 border-[var(--ui-primary)]/60">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h2 class="text-xl font-bold text-[var(--ui-primary)] mb-1">
                                {{ $teamGroup['team_name'] }}
                            </h2>
                            <div class="text-sm text-[var(--ui-muted)]">
                                {{ $teamGroup['root_groups']->count() }} {{ $teamGroup['root_groups']->count() === 1 ? 'Projekt' : 'Projekte' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-[var(--ui-primary)]">
                                {{ number_format($teamGroup['total_minutes'] / 60, 2, ',', '.') }}h
                            </div>
                            <div class="text-sm text-[var(--ui-muted)]">
                                {{ number_format($teamGroup['total_minutes'] / 480, 2, ',', '.') }} Tage
                            </div>
                            @if($teamGroup['total_amount_cents'] > 0)
                                <div class="text-lg font-bold text-[var(--ui-primary)] mt-1">
                                    {{ number_format($teamGroup['total_amount_cents'] / 100, 2, ',', '.') }} €
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Root Groups innerhalb des Teams --}}
                @foreach($teamGroup['root_groups'] as $rootGroup)
                    <div class="mb-6 ml-4">
                        {{-- Root Header --}}
                        <div class="mb-4 p-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">
                                            {{ $rootGroup['root_name'] }}
                                        </h3>
                                        @if($rootGroup['source_module_title'])
                                            <x-ui-badge variant="secondary" size="xs">
                                                {{ $rootGroup['source_module_title'] }}
                                            </x-ui-badge>
                                        @endif
                                    </div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ class_basename($rootGroup['root_type']) }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                        {{ number_format($rootGroup['total_minutes'] / 60, 2, ',', '.') }}h
                                    </div>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ number_format($rootGroup['total_minutes'] / 480, 2, ',', '.') }} Tage
                                    </div>
                                    @if($rootGroup['total_amount_cents'] > 0)
                                        <div class="text-sm font-medium text-[var(--ui-secondary)] mt-1">
                                            {{ number_format($rootGroup['total_amount_cents'] / 100, 2, ',', '.') }} €
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Entries Table --}}
                        <x-ui-table compact="true">
                            <x-ui-table-header>
                                <x-ui-table-header-cell compact="true">Datum</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Benutzer</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Team</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Kontext</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Zeit</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Betrag</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                                <x-ui-table-header-cell compact="true">Notiz</x-ui-table-header-cell>
                            </x-ui-table-header>
                            
                            <x-ui-table-body>
                                @foreach($rootGroup['entries'] as $entry)
                                    <x-ui-table-row compact="true">
                                        <x-ui-table-cell compact="true">
                                            <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                                {{ $entry->work_date->format('d.m.Y') }}
                                            </div>
                                            <div class="text-xs text-[var(--ui-muted)]">
                                                {{ $entry->work_date->format('D') }}
                                            </div>
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            <div class="flex items-center gap-2">
                                                @if($entry->user && $entry->user->avatar)
                                                    <img src="{{ $entry->user->avatar }}" alt="{{ $entry->user->name ?? 'User' }}" class="w-8 h-8 rounded-full object-cover" />
                                                @else
                                                    <div class="w-8 h-8 rounded-full bg-[var(--ui-primary-5)] flex items-center justify-center text-xs font-medium text-[var(--ui-primary)]">
                                                        {{ strtoupper(substr($entry->user->name ?? 'U', 0, 1)) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                                        {{ $entry->user->name ?? 'Unbekannt' }}
                                                    </div>
                                                    <div class="text-xs text-[var(--ui-muted)]">
                                                        {{ $entry->user->email ?? '' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            <div class="text-sm text-[var(--ui-secondary)]">
                                                {{ $entry->team->name ?? 'Unbekannt' }}
                                            </div>
                                        </x-ui-table-cell>
                                <x-ui-table-cell compact="true">
                                    @if($entry->context)
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1">
                                                <div class="text-sm text-[var(--ui-secondary)]">
                                                    {{ class_basename($entry->context_type) }}
                                                </div>
                                                <div class="text-xs text-[var(--ui-muted)]">
                                                    @if($entry->context instanceof \Platform\Core\Contracts\HasDisplayName)
                                                        {{ $entry->context->getDisplayName() ?? 'Unbekannt' }}
                                                    @else
                                                        {{ $entry->context->name ?? $entry->context->title ?? 'Unbekannt' }}
                                                    @endif
                                                </div>
                                            </div>
                                            @if($entry->source_module_title)
                                                <x-ui-badge variant="secondary" size="xs" class="shrink-0">
                                                    {{ $entry->source_module_title }}
                                                </x-ui-badge>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-[var(--ui-muted)]">–</span>
                                    @endif
                                </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                                {{ number_format($entry->minutes / 60, 2, ',', '.') }}h
                                            </div>
                                            <div class="text-xs text-[var(--ui-muted)]">
                                                {{ number_format($entry->minutes / 480, 2, ',', '.') }} Tage
                                            </div>
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            @if($entry->amount_cents)
                                                <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                                    {{ number_format($entry->amount_cents / 100, 2, ',', '.') }} €
                                                </div>
                                                @if($entry->rate_cents)
                                                    <div class="text-xs text-[var(--ui-muted)]">
                                                        {{ number_format($entry->rate_cents / 100, 2, ',', '.') }} €/h
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-xs text-[var(--ui-muted)]">–</span>
                                            @endif
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            @if($entry->is_billed)
                                                <x-ui-badge variant="success" size="xs">Abgerechnet</x-ui-badge>
                                            @else
                                                <x-ui-badge variant="warning" size="xs">Offen</x-ui-badge>
                                            @endif
                                        </x-ui-table-cell>
                                        <x-ui-table-cell compact="true">
                                            @if($entry->note)
                                                <div class="text-sm text-[var(--ui-secondary)] max-w-xs truncate" title="{{ $entry->note }}">
                                                    {{ Str::limit($entry->note, 50) }}
                                                </div>
                                            @else
                                                <span class="text-xs text-[var(--ui-muted)]">–</span>
                                            @endif
                                        </x-ui-table-cell>
                                    </x-ui-table-row>
                                @endforeach
                            </x-ui-table-body>
                        </x-ui-table>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="text-center py-8">
                <div class="text-sm text-[var(--ui-muted)]">
                    Keine Zeiteinträge gefunden.
                </div>
            </div>
        @endforelse
    </x-ui-page-container>
</x-ui-page>

