@php
    $focusedIds = $this->focusedIds;
    $viewCounts = $this->viewCounts;
    $zeilen = $this->signals->take($limit);
    $archiv = $view === 'archive';

    $sevVariant = fn ($s) => match ($s) { 'critical', 'algedonic' => 'danger', 'warning' => 'warning', default => 'info' };
    $sevLabel = fn ($s) => match ($s) { 'critical' => 'Kritisch', 'algedonic' => 'Algedonisch', 'warning' => 'Warnung', 'info' => 'Info', default => ucfirst((string) $s) };
    $statusVariant = fn ($s) => match ($s) { 'open' => 'warning', 'acknowledged' => 'info', 'resolved' => 'success', default => 'neutral' };
    $statusLabel = fn ($s) => match ($s) { 'open' => 'Offen', 'acknowledged' => 'Bestätigt', 'resolved' => 'Gelöst', 'dismissed' => 'Verworfen', default => ucfirst((string) $s) };

    // Badge-Klassen als Strings: in der Tabelle keine Komponente pro Zelle.
    $pill = fn ($v) => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ' . match ($v) {
        'success' => 'text-[color:var(--nx-success)] bg-[rgba(47,158,68,.12)]',
        'danger'  => 'text-[color:var(--nx-danger)] bg-[rgba(224,49,49,.12)]',
        'warning' => 'text-[color:var(--nx-warning)] bg-[rgba(232,89,12,.12)]',
        'info'    => 'text-[color:var(--nx-info)] bg-[rgba(25,113,194,.12)]',
        default   => 'text-[color:var(--nx-muted)] bg-[color:var(--nx-accent-soft)]',
    };
    $chip = 'inline-flex items-center gap-0.5 rounded px-1 text-[10px] font-medium';

    $visibleIds = $zeilen->pluck('id')->all();
    $allSelected = ! empty($visibleIds) && count(array_intersect($visibleIds, $selectedIds)) === count($visibleIds);

    $statusOptions = $archiv
        ? [['value' => 'resolved', 'label' => 'Nur gelöst'], ['value' => 'dismissed', 'label' => 'Nur verworfen']]
        : [['value' => 'open', 'label' => 'Nur offen'], ['value' => 'acknowledged', 'label' => 'Nur bestätigt']];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Signale" icon="heroicon-o-bell-alert" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-64" :defaultOpen="true" side="left">
            <div class="flex flex-col gap-3 p-3">
                <x-nx-input-select name="statusFilter" label="Status" size="sm" wire:model.live="statusFilter"
                    :options="$statusOptions" nullable :nullLabel="$archiv ? 'Gelöst + verworfen' : 'Offen + bestätigt'" />
                <x-nx-input-select name="severityFilter" label="Schweregrad" size="sm" wire:model.live="severityFilter"
                    :options="[
                        ['value' => 'info', 'label' => 'Info'],
                        ['value' => 'warning', 'label' => 'Warnung'],
                        ['value' => 'critical', 'label' => 'Kritisch'],
                        ['value' => 'algedonic', 'label' => 'Algedonisch'],
                    ]" nullable nullLabel="Alle" />
                <x-nx-input-select name="sourceFilter" label="Quelle" size="sm" wire:model.live="sourceFilter"
                    :options="[['value' => 'rule', 'label' => 'Regel'], ['value' => 'inference', 'label' => 'Inference']]" nullable nullLabel="Alle" />
                <div class="border-t border-[color:var(--nx-line)] pt-3">
                    <x-nx-input-checkbox label="Nur Fokus-Signale" wire:model.live="focusOnly" />
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Signale'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="inline-flex items-center gap-0.5 rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-0.5">
                        @foreach (['active' => ['Aktiv', 'heroicon-o-bolt'], 'archive' => ['Archiv', 'heroicon-o-archive-box']] as $key => [$label, $ico])
                            <button type="button" wire:click="switchView('{{ $key }}')"
                                class="inline-flex items-center gap-1.5 rounded-[5px] px-2.5 py-1 text-sm font-medium transition-colors {{ $view === $key ? 'bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] shadow-sm' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">
                                @svg($ico, 'w-4 h-4')
                                {{ $label }}
                                <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $viewCounts[$key] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Signale suchen…" wire:model.live.debounce.400ms="search" />
                    </div>
                </div>
            </x-slot>
            @if ($archiv && count($selectedIds) > 0)
                <x-nx-button variant="danger" size="sm" wire:click="bulkDelete"
                    wire:confirm="{{ count($selectedIds) }} archivierte Signale dauerhaft löschen? Diese Aktion kann nicht rückgängig gemacht werden.">
                    @svg('heroicon-o-trash', 'w-4 h-4')
                    <span>{{ count($selectedIds) }} dauerhaft löschen</span>
                </x-nx-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $archiv ? 'Signal-Archiv' : 'Signale' }}</h1>

            @if (! $archiv && ! $focusOnly && $this->focusedSignals->isNotEmpty())
                <x-nx-panel flush title="Fokus" :subtitle="$this->focusedSignals->count() . ' markiert'">
                    <div>
                        @foreach ($this->focusedSignals as $signal)
                            <div wire:key="fs-{{ $signal->id }}" class="flex items-start gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-0 hover:bg-[color:var(--nx-hover)]">
                                <button wire:click="toggleFocus({{ $signal->id }})" type="button" title="Aus Fokus entfernen"
                                    class="mt-0.5 shrink-0 text-[color:var(--nx-warning)] hover:opacity-70">
                                    @svg('heroicon-s-star', 'w-4 h-4')
                                </button>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('organization.signals.show', $signal) }}" wire:navigate class="block text-sm text-[color:var(--nx-text)] hover:underline">
                                        {{ \Illuminate\Support\Str::limit($signal->message, 140) }}
                                    </a>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-[color:var(--nx-faint)]">
                                        @if ($signal->entity)
                                            <a href="{{ route('organization.entities.show', $signal->entity) }}" wire:navigate class="font-medium text-[color:var(--nx-muted)] hover:underline">{{ $signal->entity->name }}</a>
                                        @endif
                                        <span class="{{ $pill($sevVariant($signal->severity)) }}">{{ $sevLabel($signal->severity) }}</span>
                                        <span class="{{ $pill($statusVariant($signal->status)) }}">{{ $statusLabel($signal->status) }}</span>
                                        <span>{{ $signal->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-nx-panel>
            @endif

            <x-nx-card flush>
                @if ($zeilen->isEmpty())
                    <x-nx-empty icon="heroicon-o-bell-slash">
                        {{ $archiv ? 'Noch keine archivierten Signale.' : 'Keine aktiven Signale.' }}
                    </x-nx-empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-[color:var(--nx-muted)]">
                                    @if ($archiv)
                                        <th class="w-8 border-b border-[color:var(--nx-line)] py-2.5 pl-4 text-left">
                                            <input type="checkbox" wire:click="toggleSelectAll" @checked($allSelected) title="Alle sichtbaren auswählen"
                                                class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]">
                                        </th>
                                    @endif
                                    <th class="w-8 border-b border-[color:var(--nx-line)] py-2.5 pl-4"></th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Signal</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Einheit</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Schweregrad</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Status</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-center font-medium">@svg('heroicon-o-chat-bubble-left', 'w-4 h-4 inline-block')</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-right font-medium">{{ $archiv ? 'Abgeschlossen' : 'Erstellt' }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($zeilen as $signal)
                                    @php
                                        $isFocused = in_array($signal->id, $focusedIds, true);
                                        $isOverdue = $signal->deadline_at && $signal->status === 'open' && $signal->deadline_at->isPast();
                                        $dateColumn = $archiv ? ($signal->resolved_at ?? $signal->created_at) : $signal->created_at;
                                    @endphp
                                    <tr wire:key="s-{{ $signal->id }}" class="border-b border-[color:var(--nx-line)] align-top last:border-0 hover:bg-[color:var(--nx-hover)]">
                                        @if ($archiv)
                                            <td class="py-2 pl-4">
                                                <input type="checkbox" wire:model.live="selectedIds" value="{{ $signal->id }}" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]">
                                            </td>
                                        @endif
                                        <td class="py-2 pl-4">
                                            <button wire:click="toggleFocus({{ $signal->id }})" type="button"
                                                title="{{ $isFocused ? 'Aus Fokus entfernen' : 'In Fokus aufnehmen' }}"
                                                class="transition-colors {{ $isFocused ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-faint)] hover:text-[color:var(--nx-warning)]' }}">
                                                @svg($isFocused ? 'heroicon-s-star' : 'heroicon-o-star', 'w-4 h-4')
                                            </button>
                                        </td>
                                        <td class="max-w-md px-3 py-2">
                                            <a href="{{ route('organization.signals.show', $signal) }}" wire:navigate class="text-[color:var(--nx-text)] hover:underline">
                                                {{ \Illuminate\Support\Str::limit($signal->message, 100) }}
                                            </a>
                                            @if ($archiv && $signal->status === 'dismissed' && $signal->dismissed_reason)
                                                <p class="mt-0.5 text-xs italic text-[color:var(--nx-muted)]" title="{{ $signal->dismissed_reason }}">„{{ \Illuminate\Support\Str::limit($signal->dismissed_reason, 90) }}"</p>
                                            @elseif ($archiv && $signal->status === 'resolved' && $signal->resolution_summary)
                                                <p class="mt-0.5 text-xs text-[color:var(--nx-success)]" title="{{ $signal->resolution_summary }}">
                                                    {{ \Illuminate\Support\Str::limit(str_replace(["\n", '•'], [' · ', ''], $signal->resolution_summary), 100) }}
                                                </p>
                                            @endif
                                            {{-- Routing-Mikrozeile: Quelle, VSM, Owner, Deadline, Eskalation --}}
                                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                                <span class="{{ $chip }} {{ $signal->source === 'inference' ? 'bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-text)]' : 'text-[color:var(--nx-faint)]' }}">
                                                    {{ $signal->source === 'inference' ? 'Inference' : 'Regel' }}
                                                </span>
                                                @if ($signal->vsm_level)
                                                    <span class="{{ $chip }} bg-[rgba(25,113,194,.12)] text-[color:var(--nx-info)]">{{ strtoupper(str_replace('_', '', $signal->vsm_level)) }}</span>
                                                @endif
                                                @if ($signal->current_owner_entity_id)
                                                    <span class="{{ $chip }} bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)]" title="Owner">
                                                        @svg('heroicon-o-user-circle', 'w-2.5 h-2.5')
                                                        {{ $signal->currentOwner?->name ?? '#' . $signal->current_owner_entity_id }}
                                                    </span>
                                                @elseif ($signal->status === 'open')
                                                    <span class="{{ $chip }} bg-[rgba(232,89,12,.12)] text-[color:var(--nx-warning)]" title="Kein Owner">
                                                        @svg('heroicon-o-user-circle', 'w-2.5 h-2.5')
                                                        vakant
                                                    </span>
                                                @endif
                                                @if ($signal->deadline_at && $signal->status === 'open')
                                                    <span class="{{ $chip }} {{ $isOverdue ? 'bg-[rgba(224,49,49,.12)] text-[color:var(--nx-danger)]' : 'bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)]' }}" title="Deadline {{ $signal->deadline_at->format('d.m.Y H:i') }}">
                                                        @svg('heroicon-o-clock', 'w-2.5 h-2.5')
                                                        {{ $isOverdue ? 'überfällig' : '' }} {{ $signal->deadline_at->diffForHumans() }}
                                                    </span>
                                                @endif
                                                @if ($signal->escalated_at && $signal->status === 'open')
                                                    <span class="{{ $chip }} bg-[rgba(232,89,12,.12)] text-[color:var(--nx-warning)]" title="Eskaliert {{ $signal->escalated_at->format('d.m.Y H:i') }}">
                                                        @svg('heroicon-o-arrow-trending-up', 'w-2.5 h-2.5')
                                                        eskaliert
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2">
                                            @if ($signal->entity)
                                                <a href="{{ route('organization.entities.show', $signal->entity) }}" wire:navigate class="font-medium text-[color:var(--nx-text)] hover:underline">{{ $signal->entity->name }}</a>
                                            @else
                                                <span class="text-[color:var(--nx-faint)]">–</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2">
                                            <span class="{{ $pill($sevVariant($signal->severity)) }}">{{ $sevLabel($signal->severity) }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2">
                                            <div class="flex items-center gap-1.5">
                                                <span class="{{ $pill($statusVariant($signal->status)) }}">
                                                    <span class="h-1.5 w-1.5 rounded-full" style="background:currentColor"></span>
                                                    {{ $statusLabel($signal->status) }}
                                                </span>
                                                @if ($signal->snooze_until && $signal->snooze_until->isFuture())
                                                    <span class="text-[color:var(--nx-warning)]" title="Snoozed bis {{ $signal->snooze_until->format('d.m.Y') }}">@svg('heroicon-o-clock', 'w-3.5 h-3.5')</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-center text-xs tabular-nums text-[color:var(--nx-muted)]">
                                            {{ $signal->comments_count > 0 ? $signal->comments_count : '' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right text-[color:var(--nx-muted)]">
                                            <span title="{{ $dateColumn->format('d.m.Y H:i') }}">{{ $dateColumn->diffForHumans() }}</span>
                                            @if ($archiv && $signal->resolvedByUser)
                                                <div class="text-[10px] text-[color:var(--nx-faint)]">von {{ $signal->resolvedByUser->name }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($this->signals->count() > $limit)
                        <div class="flex items-center justify-between border-t border-[color:var(--nx-line)] px-4 py-2.5">
                            <span class="text-xs text-[color:var(--nx-faint)]">{{ $zeilen->count() }} angezeigt</span>
                            <x-nx-button size="sm" wire:click="loadMore">
                                <span wire:loading.remove wire:target="loadMore">Weitere laden</span>
                                <span wire:loading wire:target="loadMore">Lädt…</span>
                            </x-nx-button>
                        </div>
                    @endif
                @endif
            </x-nx-card>
        </div>
    </x-ui-page-container>
</x-ui-page>
