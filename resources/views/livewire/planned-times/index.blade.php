@php
    $opts = fn ($items) => $items->map(fn ($i) => ['value' => $i->id, 'label' => $i->name])->values()->all();
    $hours = fn ($m) => number_format($m / 60, 2, ',', '.') . ' h';
    $alle = $this->plannedTimes;
    $zeilen = $alle->take($limit);
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Plan-Zeiten" icon="heroicon-o-calendar" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-64" :defaultOpen="true" side="left">
            <div class="flex flex-col gap-3 p-3">
                <x-nx-input-select name="selectedTeamId" label="Team" size="sm" wire:model.live="selectedTeamId"
                    :options="$opts($this->availableTeams)" nullable nullLabel="Alle" />
                <x-nx-input-select name="selectedUserId" label="Person" size="sm" wire:model.live="selectedUserId"
                    :options="$opts($this->availableUsers)" nullable nullLabel="Alle" />
                <div class="border-t border-[color:var(--nx-line)] pt-3">
                    <x-nx-input-checkbox label="Nur inaktive" wire:model.live="showInactiveOnly" />
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Plan-Zeiten'],
        ]">
            <x-slot name="left">
                <div class="w-64">
                    <x-nx-input-text name="search" size="sm" placeholder="Notiz, Person oder Team…" wire:model.live.debounce.400ms="search" />
                </div>
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">Plan-Zeiten</h1>

            <x-nx-stat-grid cols="2">
                <x-nx-stat label="Geplant" :value="$hours($this->totalPlannedMinutes)" icon="heroicon-o-calendar" />
                <x-nx-stat label="Einträge" :value="number_format($alle->count(), 0, ',', '.')" icon="heroicon-o-queue-list" />
            </x-nx-stat-grid>

            <x-nx-card flush>
                @if ($alle->isEmpty())
                    <x-nx-empty icon="heroicon-o-calendar">Keine geplanten Zeiten gefunden.</x-nx-empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-[color:var(--nx-muted)]">
                                    <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Person</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Kontext</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Notiz</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Geplant</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Erstellt</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-right font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($zeilen as $p)
                                    @php($ctx = $p->context)
                                    <tr wire:key="pt-{{ $p->id }}" class="border-b border-[color:var(--nx-line)] last:border-0 hover:bg-[color:var(--nx-hover)]">
                                        <td class="whitespace-nowrap px-4 py-1.5">
                                            <div class="flex items-center gap-2">
                                                @if ($p->user?->avatar)
                                                    <img src="{{ $p->user->avatarUrl() }}" loading="lazy" alt="" class="h-6 w-6 rounded-[6px] object-cover" />
                                                @else
                                                    <span class="flex h-6 w-6 items-center justify-center rounded-[6px] bg-[color:var(--nx-accent-soft)] text-[10px] font-medium">{{ mb_strtoupper(mb_substr($p->user->name ?? '?', 0, 1)) }}</span>
                                                @endif
                                                <div class="min-w-0">
                                                    <div class="text-[color:var(--nx-text)]">{{ $p->user->name ?? 'Unbekannt' }}</div>
                                                    <div class="text-xs text-[color:var(--nx-faint)]">{{ $p->team->name ?? '' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="max-w-[16rem] px-3 py-1.5">
                                            @if ($ctx)
                                                <div class="truncate text-[color:var(--nx-text)]">{{ $ctx->name ?? $ctx->title ?? 'Unbekannt' }}</div>
                                                <div class="text-xs text-[color:var(--nx-faint)]">{{ class_basename($p->context_type) }}</div>
                                            @else
                                                <span class="text-[color:var(--nx-faint)]">–</span>
                                            @endif
                                        </td>
                                        <td class="max-w-xs px-3 py-1.5">
                                            @if ($p->note)
                                                <div class="truncate text-[color:var(--nx-muted)]" title="{{ $p->note }}">{{ $p->note }}</div>
                                            @else
                                                <span class="text-[color:var(--nx-faint)]">–</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-1.5 text-right font-medium tabular-nums text-[color:var(--nx-text)]">{{ $hours($p->planned_minutes) }}</td>
                                        <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums text-[color:var(--nx-muted)]">{{ $p->created_at->format('d.m.Y') }}</td>
                                        <td class="whitespace-nowrap px-4 py-1.5 text-right">
                                            <x-nx-badge :variant="$p->is_active ? 'success' : 'neutral'" dot>{{ $p->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($alle->count() > $zeilen->count())
                        <div class="flex items-center justify-between border-t border-[color:var(--nx-line)] px-4 py-2.5">
                            <span class="text-xs text-[color:var(--nx-faint)]">{{ $zeilen->count() }} von {{ $alle->count() }}</span>
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
