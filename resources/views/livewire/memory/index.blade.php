@php
    $alle = $this->memoryEntries;
    $zeilen = $alle->take($limit);
    $typLabel = fn ($t) => ucfirst(str_replace('_', ' ', (string) $t));
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Memory" icon="heroicon-o-circle-stack" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Memory'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Inhalt oder Einheit…" wire:model.live.debounce.400ms="search" />
                    </div>
                    <div class="w-44">
                        <x-nx-input-select name="typeFilter" size="sm" wire:model.live="typeFilter" nullable nullLabel="Alle Typen"
                            :options="$this->memoryTypes->map(fn ($t) => ['value' => $t, 'label' => $typLabel($t)])->all()" />
                    </div>
                    <x-nx-input-checkbox label="Inaktive" wire:model.live="showInactive" />
                </div>
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <x-nx-card flush>
            @if ($alle->isEmpty())
                <x-nx-empty icon="heroicon-o-circle-stack">Keine Memory-Einträge gefunden.</x-nx-empty>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-[color:var(--nx-muted)]">
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Einheit · Typ</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Inhalt</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium" title="Konfidenz">Konf.</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium" title="Bestätigungen">Best.</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Gültig bis</th>
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-right font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($zeilen as $entry)
                                <tr wire:key="mem-{{ $entry->id }}" class="border-b border-[color:var(--nx-line)] align-top last:border-0 hover:bg-[color:var(--nx-hover)]">
                                    <td class="whitespace-nowrap px-4 py-2">
                                        @if ($entry->entity)
                                            <a href="{{ route('organization.entities.show', $entry->entity) }}" wire:navigate class="font-medium text-[color:var(--nx-text)] hover:underline">{{ $entry->entity->name }}</a>
                                        @else
                                            <span class="text-[color:var(--nx-faint)]">–</span>
                                        @endif
                                        <div class="text-xs text-[color:var(--nx-faint)]">{{ $typLabel($entry->memory_type ?? '–') }}</div>
                                    </td>
                                    <td class="max-w-xl px-3 py-2">
                                        <div class="line-clamp-2 text-[color:var(--nx-muted)]" title="{{ $entry->content }}">{{ $entry->content }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">{{ number_format($entry->confidence * 100, 0) }} %</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">{{ $entry->reinforcement_count }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums {{ $entry->valid_until?->isPast() ? 'text-[color:var(--nx-danger)]' : 'text-[color:var(--nx-muted)]' }}">
                                        {{ $entry->valid_until?->format('d.m.Y') ?? 'unbegrenzt' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right">
                                        <x-nx-badge :variant="$entry->is_active ? 'success' : 'neutral'" dot>{{ $entry->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($alle->count() > $zeilen->count())
                    <div class="flex items-center justify-between border-t border-[color:var(--nx-line)] px-4 py-2.5">
                        <span class="text-xs text-[color:var(--nx-faint)]">{{ $zeilen->count() }} von {{ $alle->count() }}</span>
                        <x-nx-button size="sm" wire:click="loadMore">Weitere laden</x-nx-button>
                    </div>
                @endif
            @endif
        </x-nx-card>
    </x-ui-page-container>
</x-ui-page>
