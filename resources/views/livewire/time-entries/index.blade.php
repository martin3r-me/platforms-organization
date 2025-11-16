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
                                <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ \Platform\Organization\Models\OrganizationTimeEntry::formatMinutes($this->totalMinutes) }}</span>
                            </div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-[var(--ui-muted)]">Abgerechnet</span>
                                <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ \Platform\Organization\Models\OrganizationTimeEntry::formatMinutes($this->totalBilledMinutes) }}</span>
                            </div>
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
            @foreach($this->timeEntriesGroupedByTeamAndRoot as $teamGroup)
                <div class="p-4 bg-gradient-to-br from-[var(--ui-surface)] to-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/60 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 bg-[var(--ui-primary-10)] rounded-lg flex items-center justify-center flex-shrink-0">
                            @svg('heroicon-o-user-group', 'w-4 h-4 text-[var(--ui-primary)]')
                        </div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] truncate">{{ $teamGroup['team_name'] }}</h3>
                    </div>
                    
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Zeit</span>
                            <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ \Platform\Organization\Models\OrganizationTimeEntry::formatMinutes($teamGroup['total_minutes']) }}</div>
                        </div>
                        @if($teamGroup['total_amount_cents'] > 0)
                            <div class="flex items-center justify-between pt-2 border-t border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Betrag</span>
                                <div class="text-sm font-semibold text-[var(--ui-primary)]">{{ number_format($teamGroup['total_amount_cents'] / 100, 2, ',', '.') }} €</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($this->timeEntriesGroupedByDateAndTeam->isNotEmpty())
            {{-- Single Table for all entries --}}
            <div class="flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                        <table class="relative min-w-full divide-y divide-gray-300 dark:divide-white/15">
                            <thead>
                                <tr>
                                    <th scope="col" class="py-3.5 pr-3 pl-4 text-left text-sm font-semibold text-gray-900 sm:pl-0 dark:text-white">Datum</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Benutzer</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Team</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Kontext</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Zeit</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Betrag</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                                    <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Notiz</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                                @foreach($this->timeEntriesGroupedByDateAndTeam as $dateGroup)
                                    {{-- Date Header Row --}}
                                    <tr class="bg-[var(--ui-primary-10)] border-t-2 border-[var(--ui-primary)]/80">
                                        <td colspan="8" class="py-4 pr-3 pl-4 sm:pl-0">
                                            <div class="flex items-center justify-between px-2">
                                                <div class="flex-1">
                                                    <h2 class="text-2xl font-bold text-[var(--ui-primary)] mb-1">
                                                        {{ $dateGroup['date']->format('d.m.Y') }}
                                                    </h2>
                                                    <div class="text-sm text-[var(--ui-muted)]">
                                                        {{ $dateGroup['date']->locale('de')->isoFormat('dddd') }} · {{ $dateGroup['team_groups']->count() }} {{ $dateGroup['team_groups']->count() === 1 ? 'Team' : 'Teams' }}
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-lg font-bold text-[var(--ui-primary)]">
                                                        {{ \Platform\Organization\Models\OrganizationTimeEntry::formatMinutes($dateGroup['total_minutes']) }}
                                                    </div>
                                                    @if($dateGroup['total_amount_cents'] > 0)
                                                        <div class="text-lg font-bold text-[var(--ui-primary)] mt-1">
                                                            {{ number_format($dateGroup['total_amount_cents'] / 100, 2, ',', '.') }} €
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    {{-- Team Groups innerhalb des Datums --}}
                                    @foreach($dateGroup['team_groups'] as $teamGroup)
                                        {{-- Team Header Row --}}
                                        <tr class="bg-[var(--ui-primary-5)] border-t border-[var(--ui-primary)]/40">
                                            <td colspan="8" class="py-3 pr-3 pl-4 sm:pl-0">
                                                <div class="flex items-center justify-between px-2">
                                                    <div class="flex-1">
                                                        <h3 class="text-lg font-bold text-[var(--ui-primary)] mb-1">
                                                            {{ $teamGroup['team_name'] }}
                                                        </h3>
                                                        <div class="text-sm text-[var(--ui-muted)]">
                                                            {{ $teamGroup['root_groups']->count() }} {{ $teamGroup['root_groups']->count() === 1 ? 'Projekt' : 'Projekte' }}
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-base font-bold text-[var(--ui-primary)]">
                                                            {{ \Platform\Organization\Models\OrganizationTimeEntry::formatMinutes($teamGroup['total_minutes']) }}
                                                        </div>
                                                        @if($teamGroup['total_amount_cents'] > 0)
                                                            <div class="text-base font-bold text-[var(--ui-primary)] mt-1">
                                                                {{ number_format($teamGroup['total_amount_cents'] / 100, 2, ',', '.') }} €
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Root Groups innerhalb des Teams --}}
                                        @foreach($teamGroup['root_groups'] as $rootGroup)
                                        {{-- Root Group Header Row --}}
                                        <tr class="bg-[var(--ui-muted-5)]">
                                            <td colspan="8" class="py-3 pr-3 pl-4 sm:pl-0">
                                                <div class="flex items-center justify-between px-2">
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <h3 class="text-xl font-semibold text-[var(--ui-secondary)]">
                                                                {{ $rootGroup['source_module_title'] ?? class_basename($rootGroup['root_type']) }}
                                                            </h3>
                                                            <x-ui-badge variant="secondary" size="xs">
                                                                {{ $rootGroup['root_name'] }}
                                                            </x-ui-badge>
                                                        </div>
                                                        @php
                                                            // Prüfe ob es unterschiedliche Contexts gibt
                                                            $contexts = $rootGroup['entries']->map(function($entry) {
                                                                if (!$entry->context) return null;
                                                                $contextName = $entry->context instanceof \Platform\Core\Contracts\HasDisplayName 
                                                                    ? $entry->context->getDisplayName() 
                                                                    : ($entry->context->name ?? $entry->context->title ?? null);
                                                                return [
                                                                    'type' => class_basename($entry->context_type),
                                                                    'name' => $contextName,
                                                                    'id' => $entry->context_id,
                                                                ];
                                                            })->filter()->unique(function($item) {
                                                                return ($item['type'] ?? '') . ':' . ($item['id'] ?? 0);
                                                            })->values();
                                                            
                                                            $firstEntry = $rootGroup['entries']->first();
                                                            $hasMultipleContexts = $contexts->count() > 1;
                                                            $hasDifferentContextFromRoot = $firstEntry && $firstEntry->context_id && $firstEntry->root_context_id 
                                                                && ($firstEntry->context_id != $firstEntry->root_context_id || $firstEntry->context_type != $firstEntry->root_context_type);
                                                        @endphp
                                                        @if($hasMultipleContexts || $hasDifferentContextFromRoot)
                                                            <div class="text-xs text-[var(--ui-muted)] mt-1">
                                                                @if($hasMultipleContexts)
                                                                    <span class="font-semibold">Kontexte:</span>
                                                                    @foreach($contexts as $ctx)
                                                                        <span class="inline-block mr-2">
                                                                            {{ $ctx['type'] }}: {{ $ctx['name'] }}
                                                                        </span>
                                                                    @endforeach
                                                                @elseif($hasDifferentContextFromRoot && $firstEntry)
                                                                    @php
                                                                        $contextName = $firstEntry->context instanceof \Platform\Core\Contracts\HasDisplayName 
                                                                            ? $firstEntry->context->getDisplayName() 
                                                                            : ($firstEntry->context->name ?? $firstEntry->context->title ?? 'Unbekannt');
                                                                        $rootContextName = $firstEntry->rootContext instanceof \Platform\Core\Contracts\HasDisplayName 
                                                                            ? $firstEntry->rootContext->getDisplayName() 
                                                                            : ($firstEntry->rootContext->name ?? $firstEntry->rootContext->title ?? 'Unbekannt');
                                                                    @endphp
                                                                    <span class="font-semibold">Kontext:</span> {{ class_basename($firstEntry->context_type) }}: {{ $contextName }}
                                                                    <span class="mx-1">→</span>
                                                                    <span class="font-semibold">Root:</span> {{ class_basename($firstEntry->root_context_type) }}: {{ $rootContextName }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="text-right">
                                                        <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                                            {{ \Platform\Organization\Models\OrganizationTimeEntry::formatMinutes($rootGroup['total_minutes']) }}
                                                        </div>
                                                        @if($rootGroup['total_amount_cents'] > 0)
                                                            <div class="text-sm font-medium text-[var(--ui-secondary)] mt-1">
                                                                {{ number_format($rootGroup['total_amount_cents'] / 100, 2, ',', '.') }} €
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Entries for this Root Group --}}
                                        @foreach($rootGroup['entries'] as $entry)
                                            <tr>
                                                <td class="py-5 pr-3 pl-4 text-sm whitespace-nowrap sm:pl-0">
                                                    <div class="text-gray-900 dark:text-white font-medium">
                                                        {{ $entry->work_date->format('d.m.Y') }}
                                                    </div>
                                                    <div class="mt-1 text-gray-500 dark:text-gray-400">
                                                        {{ $entry->work_date->format('D') }}
                                                    </div>
                                                </td>
                                                <td class="px-3 py-5 text-sm whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="size-11 shrink-0">
                                                            @if($entry->user && $entry->user->avatar)
                                                                <img src="{{ $entry->user->avatar }}" alt="{{ $entry->user->name ?? 'User' }}" class="size-11 rounded-full object-cover dark:outline dark:outline-white/10" />
                                                            @else
                                                                <div class="size-11 rounded-full bg-[var(--ui-primary-5)] flex items-center justify-center text-xs font-medium text-[var(--ui-primary)] dark:outline dark:outline-white/10">
                                                                    {{ strtoupper(substr($entry->user->name ?? 'U', 0, 1)) }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="font-medium text-gray-900 dark:text-white">
                                                                {{ $entry->user->name ?? 'Unbekannt' }}
                                                            </div>
                                                            <div class="mt-1 text-gray-500 dark:text-gray-400">
                                                                {{ $entry->user->email ?? '' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-5 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                    {{ $entry->team->name ?? 'Unbekannt' }}
                                                </td>
                                                <td class="px-3 py-5 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                    @if($entry->context)
                                                        <div class="text-gray-900 dark:text-white">
                                                            <span class="font-semibold">{{ class_basename($entry->context_type) }}:</span>
                                                            @if($entry->context instanceof \Platform\Core\Contracts\HasDisplayName)
                                                                {{ $entry->context->getDisplayName() ?? 'Unbekannt' }}
                                                            @else
                                                                {{ $entry->context->name ?? $entry->context->title ?? 'Unbekannt' }}
                                                            @endif
                                                        </div>
                                                        @if($entry->rootContext && ($entry->context_id != $entry->root_context_id || $entry->context_type != $entry->root_context_type))
                                                            <div class="mt-1 text-gray-500 dark:text-gray-400">
                                                                <span class="font-semibold">Root:</span>
                                                                <span class="text-xs">{{ class_basename($entry->root_context_type) }}:</span>
                                                                @if($entry->rootContext instanceof \Platform\Core\Contracts\HasDisplayName)
                                                                    {{ $entry->rootContext->getDisplayName() ?? 'Unbekannt' }}
                                                                @else
                                                                    {{ $entry->rootContext->name ?? $entry->rootContext->title ?? 'Unbekannt' }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                        @if($entry->source_module_title)
                                                            <div class="mt-1">
                                                                <x-ui-badge variant="secondary" size="xs">
                                                                    {{ $entry->source_module_title }}
                                                                </x-ui-badge>
                                                            </div>
                                                        @endif
                                                    @elseif($entry->rootContext)
                                                        <div class="text-gray-900 dark:text-white">
                                                            <span class="font-semibold">Root:</span>
                                                            <span class="text-xs">{{ class_basename($entry->root_context_type) }}:</span>
                                                            @if($entry->rootContext instanceof \Platform\Core\Contracts\HasDisplayName)
                                                                {{ $entry->rootContext->getDisplayName() ?? 'Unbekannt' }}
                                                            @else
                                                                {{ $entry->rootContext->name ?? $entry->rootContext->title ?? 'Unbekannt' }}
                                                            @endif
                                                        </div>
                                                    @else
                                                        <span class="text-gray-500 dark:text-gray-400">–</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-5 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                    <div class="text-gray-900 dark:text-white font-medium">
                                                        {{ \Platform\Organization\Models\OrganizationTimeEntry::formatMinutes($entry->minutes) }}
                                                    </div>
                                                </td>
                                                <td class="px-3 py-5 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                    @if($entry->amount_cents)
                                                        <div class="text-gray-900 dark:text-white font-medium">
                                                            {{ number_format($entry->amount_cents / 100, 2, ',', '.') }} €
                                                        </div>
                                                        @if($entry->rate_cents)
                                                            <div class="mt-1 text-gray-500 dark:text-gray-400">
                                                                {{ number_format($entry->rate_cents / 100, 2, ',', '.') }} €/h
                                                            </div>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-500 dark:text-gray-400">–</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-5 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                    @if($entry->is_billed)
                                                        <x-ui-badge variant="success" size="xs">Abgerechnet</x-ui-badge>
                                                    @else
                                                        <x-ui-badge variant="warning" size="xs">Offen</x-ui-badge>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-5 text-sm text-gray-500 dark:text-gray-400">
                                                    @if($entry->note)
                                                        <div class="max-w-xs truncate" title="{{ $entry->note }}">
                                                            {{ Str::limit($entry->note, 50) }}
                                                        </div>
                                                    @else
                                                        <span class="text-gray-500 dark:text-gray-400">–</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-8">
                <div class="text-sm text-[var(--ui-muted)]">
                    Keine Zeiteinträge gefunden.
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>

