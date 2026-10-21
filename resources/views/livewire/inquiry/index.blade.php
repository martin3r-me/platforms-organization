@php
    $alle = $this->inquiries;
    $zeilen = $alle->take($limit);
    $statusLabel = ['pending' => 'Offen', 'partial' => 'Teilweise', 'completed' => 'Abgeschlossen', 'cancelled' => 'Abgebrochen'];
    $statusVariant = ['pending' => 'warning', 'partial' => 'info', 'completed' => 'success', 'cancelled' => 'neutral'];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Rückfragen" icon="heroicon-o-question-mark-circle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Rückfragen'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Suchen…" wire:model.live.debounce.400ms="search" />
                    </div>
                    <div class="w-44">
                        <x-nx-input-select name="statusFilter" size="sm" wire:model.live="statusFilter" nullable nullLabel="Alle Status"
                            :options="collect($statusLabel)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all()" />
                    </div>
                </div>
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <x-nx-card flush>
            @if ($alle->isEmpty())
                <x-nx-empty icon="heroicon-o-question-mark-circle">Keine Rückfragen gefunden.</x-nx-empty>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-[color:var(--nx-muted)]">
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Einheit</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Typ · Prompt</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Empfänger</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Fällig</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Erstellt</th>
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-right font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($zeilen as $inquiry)
                                @php
                                    $ueberfaellig = $inquiry->due_date && $inquiry->due_date->isPast() && in_array($inquiry->status, ['pending', 'partial']);
                                @endphp
                                <tr wire:key="inq-{{ $inquiry->id }}" class="border-b border-[color:var(--nx-line)] last:border-0 hover:bg-[color:var(--nx-hover)]">
                                    <td class="px-4 py-2">
                                        @if ($inquiry->entity)
                                            <a href="{{ route('organization.entities.show', $inquiry->entity) }}" wire:navigate class="font-medium text-[color:var(--nx-text)] hover:underline">{{ $inquiry->entity->name }}</a>
                                        @else
                                            <span class="text-[color:var(--nx-faint)]">–</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="text-[color:var(--nx-text)]">{{ $inquiry->inquiry_type ?? '–' }}</div>
                                        @if ($inquiry->inferencePrompt)
                                            <div class="text-xs text-[color:var(--nx-faint)]">{{ $inquiry->inferencePrompt->name }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">{{ $inquiry->recipients_count }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums {{ $ueberfaellig ? 'font-medium text-[color:var(--nx-danger)]' : 'text-[color:var(--nx-muted)]' }}">
                                        {{ $inquiry->due_date?->format('d.m.Y') ?? '–' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">{{ $inquiry->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right">
                                        <x-nx-badge :variant="$statusVariant[$inquiry->status] ?? 'neutral'" dot>{{ $statusLabel[$inquiry->status] ?? ucfirst($inquiry->status) }}</x-nx-badge>
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
