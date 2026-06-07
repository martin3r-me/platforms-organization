<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Signale'],
        ]">
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Signale..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer select-none">
                            <input type="checkbox" wire:model.live="focusOnly" class="rounded border-gray-300 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]">
                            <span class="inline-flex items-center gap-1">
                                @svg('heroicon-s-star', 'w-4 h-4 text-amber-500')
                                Nur Fokus-Signale
                            </span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Status</label>
                            <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                @if($view === 'archive')
                                    <option value="">Alle (gelöst + verworfen)</option>
                                    <option value="resolved">Nur gelöst</option>
                                    <option value="dismissed">Nur verworfen</option>
                                @else
                                    <option value="">Alle aktiven (offen + bestätigt)</option>
                                    <option value="open">Nur offen</option>
                                    <option value="acknowledged">Nur bestätigt</option>
                                @endif
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Schweregrad</label>
                            <select wire:model.live="severityFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Alle Schweregrade</option>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="critical">Critical</option>
                                <option value="algedonic">Algedonic</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Quelle</label>
                            <select wire:model.live="sourceFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Alle Quellen</option>
                                <option value="rule">Regel</option>
                                <option value="inference">Inference</option>
                            </select>
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
        @php
            $focusedIds = $this->focusedIds;
            $viewCounts = $this->viewCounts;
        @endphp

        {{-- View toggle: Active / Archive --}}
        <div class="mb-4 inline-flex items-center gap-1 p-1 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
            <button
                type="button"
                wire:click="switchView('active')"
                @class([
                    'px-3 py-1.5 text-sm font-medium rounded-md transition inline-flex items-center gap-1.5',
                    'bg-white text-[var(--ui-secondary)] shadow-sm' => $view === 'active',
                    'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' => $view !== 'active',
                ])
            >
                @svg('heroicon-o-bolt', 'w-4 h-4')
                Aktiv
                <span class="text-xs text-[var(--ui-muted)] tabular-nums">{{ $viewCounts['active'] }}</span>
            </button>
            <button
                type="button"
                wire:click="switchView('archive')"
                @class([
                    'px-3 py-1.5 text-sm font-medium rounded-md transition inline-flex items-center gap-1.5',
                    'bg-white text-[var(--ui-secondary)] shadow-sm' => $view === 'archive',
                    'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]' => $view !== 'archive',
                ])
            >
                @svg('heroicon-o-archive-box', 'w-4 h-4')
                Archiv
                <span class="text-xs text-[var(--ui-muted)] tabular-nums">{{ $viewCounts['archive'] }}</span>
            </button>
        </div>

        @if($view !== 'archive' && ! $focusOnly && $this->focusedSignals->isNotEmpty())
            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50/40 p-4">
                <div class="flex items-center gap-2 mb-3">
                    @svg('heroicon-s-star', 'w-5 h-5 text-amber-500')
                    <h2 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Fokus-Signale</h2>
                    <span class="text-xs text-[var(--ui-muted)]">({{ $this->focusedSignals->count() }})</span>
                </div>
                <div class="space-y-2">
                    @foreach($this->focusedSignals as $signal)
                        <div class="flex items-start gap-3 bg-white rounded-md border border-amber-200/60 p-3 hover:border-amber-400 transition">
                            <button
                                wire:click="toggleFocus({{ $signal->id }})"
                                type="button"
                                class="flex-shrink-0 mt-0.5 text-amber-500 hover:text-amber-700 transition"
                                title="Aus Fokus entfernen"
                            >
                                @svg('heroicon-s-star', 'w-4 h-4')
                            </button>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    @if($signal->entity)
                                        <a href="{{ route('organization.entities.show', $signal->entity) }}" class="link text-sm font-medium">
                                            {{ $signal->entity->name }}
                                        </a>
                                    @endif
                                    <x-ui-badge variant="{{ match($signal->severity) { 'critical' => 'danger', 'algedonic' => 'danger', 'warning' => 'warning', default => 'info' } }}">
                                        {{ ucfirst($signal->severity) }}
                                    </x-ui-badge>
                                    <x-ui-badge variant="{{ match($signal->status) { 'open' => 'warning', 'acknowledged' => 'info', 'resolved' => 'success', 'dismissed' => 'muted', default => 'secondary' } }}">
                                        @switch($signal->status)
                                            @case('open') Offen @break
                                            @case('acknowledged') Bestätigt @break
                                            @case('resolved') Gelöst @break
                                            @case('dismissed') Verworfen @break
                                        @endswitch
                                    </x-ui-badge>
                                </div>
                                <a href="{{ route('organization.signals.show', $signal) }}" class="text-sm text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] block">
                                    {{ \Illuminate\Support\Str::limit($signal->message, 140) }}
                                </a>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">{{ $signal->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($view === 'archive' && count($selectedIds) > 0)
            <div class="mb-3 flex items-center justify-between gap-3 p-3 rounded-lg bg-red-50 border border-red-200">
                <div class="flex items-center gap-2 text-sm text-red-900">
                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                    <span class="font-medium">{{ count($selectedIds) }} {{ count($selectedIds) === 1 ? 'Signal' : 'Signale' }} ausgewählt</span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="$set('selectedIds', [])"
                        class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]"
                    >
                        Auswahl aufheben
                    </button>
                    <button
                        type="button"
                        wire:click="bulkDelete"
                        wire:confirm="{{ count($selectedIds) }} archivierte Signale dauerhaft löschen? Diese Aktion kann nicht rückgängig gemacht werden."
                        class="px-3 py-1.5 rounded-md text-xs font-medium bg-red-600 text-white hover:bg-red-700 transition inline-flex items-center gap-1.5"
                    >
                        @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                        Dauerhaft löschen
                    </button>
                </div>
            </div>
        @endif

        <x-ui-table compact="true">
            <x-ui-table-header>
                @if($view === 'archive')
                    <x-ui-table-header-cell compact="true" class="w-8">
                        @php($visibleIds = $this->signals->pluck('id')->all())
                        @php($allSelected = ! empty($visibleIds) && count(array_intersect($visibleIds, $selectedIds)) === count($visibleIds))
                        <input
                            type="checkbox"
                            wire:click="toggleSelectAll"
                            @checked($allSelected)
                            class="rounded border-gray-300 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                            title="Alle sichtbaren auswählen"
                        >
                    </x-ui-table-header-cell>
                @endif
                <x-ui-table-header-cell compact="true" class="w-8"></x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Entity</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Schweregrad</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Quelle</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Nachricht</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" class="text-center">@svg('heroicon-o-chat-bubble-left', 'w-4 h-4 inline-block')</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">{{ $view === 'archive' ? 'Abgeschlossen' : 'Erstellt' }}</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->signals as $signal)
                    @php($isFocused = in_array($signal->id, $focusedIds, true))
                    <x-ui-table-row compact="true">
                        @if($view === 'archive')
                            <x-ui-table-cell compact="true">
                                <input
                                    type="checkbox"
                                    wire:model.live="selectedIds"
                                    value="{{ $signal->id }}"
                                    class="rounded border-gray-300 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]"
                                >
                            </x-ui-table-cell>
                        @endif
                        <x-ui-table-cell compact="true">
                            <button
                                wire:click="toggleFocus({{ $signal->id }})"
                                type="button"
                                @class([
                                    'transition',
                                    'text-amber-500 hover:text-amber-700' => $isFocused,
                                    'text-gray-300 hover:text-amber-500' => ! $isFocused,
                                ])
                                title="{{ $isFocused ? 'Aus Fokus entfernen' : 'In Fokus aufnehmen' }}"
                            >
                                @if($isFocused)
                                    @svg('heroicon-s-star', 'w-4 h-4')
                                @else
                                    @svg('heroicon-o-star', 'w-4 h-4')
                                @endif
                            </button>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($signal->entity)
                                <a href="{{ route('organization.entities.show', $signal->entity) }}" class="link font-medium">
                                    {{ $signal->entity->name }}
                                </a>
                            @else
                                <span class="text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ match($signal->severity) { 'critical' => 'danger', 'algedonic' => 'danger', 'warning' => 'warning', default => 'info' } }}">
                                {{ ucfirst($signal->severity) }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $signal->source === 'inference' ? 'primary' : 'secondary' }}">
                                {{ $signal->source === 'inference' ? 'Inference' : 'Regel' }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.signals.show', $signal) }}" class="link text-sm">
                                {{ \Illuminate\Support\Str::limit($signal->message, 80) }}
                            </a>
                            @if($view === 'archive' && $signal->status === 'dismissed' && $signal->dismissed_reason)
                                <p class="text-xs text-[var(--ui-muted)] italic mt-0.5" title="{{ $signal->dismissed_reason }}">
                                    „{{ \Illuminate\Support\Str::limit($signal->dismissed_reason, 90) }}"
                                </p>
                            @elseif($view === 'archive' && $signal->status === 'resolved' && $signal->resolution_summary)
                                <p class="text-xs text-green-700 mt-0.5" title="{{ $signal->resolution_summary }}">
                                    @svg('heroicon-o-check', 'w-3 h-3 inline-block -mt-0.5')
                                    {{ \Illuminate\Support\Str::limit(str_replace(["\n", '•'], [' · ', ''], $signal->resolution_summary), 100) }}
                                </p>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex items-center gap-1.5">
                                <x-ui-badge variant="{{ match($signal->status) { 'open' => 'warning', 'acknowledged' => 'info', 'resolved' => 'success', 'dismissed' => 'muted', default => 'secondary' } }}">
                                    @switch($signal->status)
                                        @case('open') Offen @break
                                        @case('acknowledged') Bestätigt @break
                                        @case('resolved') Gelöst @break
                                        @case('dismissed') Verworfen @break
                                    @endswitch
                                </x-ui-badge>
                                @if($signal->snooze_until && $signal->snooze_until->isFuture())
                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700" title="Snoozed bis {{ $signal->snooze_until->format('d.m.Y') }}">
                                        @svg('heroicon-o-clock', 'w-3 h-3')
                                    </span>
                                @endif
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true" class="text-center">
                            @if($signal->comments_count > 0)
                                <span class="text-xs text-[var(--ui-muted)] tabular-nums">{{ $signal->comments_count }}</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @php($dateColumn = $view === 'archive' ? ($signal->resolved_at ?? $signal->created_at) : $signal->created_at)
                            <div class="text-sm text-[var(--ui-muted)]" title="{{ $dateColumn->format('d.m.Y H:i') }}">
                                {{ $dateColumn->diffForHumans() }}
                            </div>
                            @if($view === 'archive' && $signal->resolvedByUser)
                                <div class="text-[10px] text-[var(--ui-muted)] mt-0.5">
                                    von {{ $signal->resolvedByUser->name }}
                                </div>
                            @endif
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="{{ $view === 'archive' ? 9 : 8 }}">
                            <div class="text-center py-8">
                                @svg('heroicon-o-bell-slash', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                <p class="text-sm text-[var(--ui-muted)]">
                                    @if($view === 'archive')
                                        Noch keine archivierten Signale.
                                    @else
                                        Keine aktiven Signale.
                                    @endif
                                </p>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>
