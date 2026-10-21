@php
    $alle = $this->recipients;
    $zeilen = $alle->take($limit);
    $statusLabel = ['pending' => 'Offen', 'answered' => 'Beantwortet', 'timeout' => 'Abgelaufen'];
    $statusVariant = ['pending' => 'warning', 'answered' => 'success', 'timeout' => 'danger'];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Meine Rückfragen" icon="heroicon-o-inbox" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Meine Rückfragen'],
        ]">
            <x-slot name="left">
                <div class="w-44">
                    <x-nx-input-select name="statusFilter" size="sm" wire:model.live="statusFilter" nullable nullLabel="Alle Status"
                        :options="collect($statusLabel)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all()" />
                </div>
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-4">
            @if (session('success'))
                <x-nx-callout variant="success">{{ session('success') }}</x-nx-callout>
            @endif

            <x-nx-card flush>
                @if ($alle->isEmpty())
                    <x-nx-empty icon="heroicon-o-inbox">Keine Rückfragen an dich.</x-nx-empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-[color:var(--nx-muted)]">
                                    <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Einheit · Kontext</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Typ</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Runde</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Fällig</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Status</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($zeilen as $recipient)
                                    @php
                                        $inquiry = $recipient->inquiry;
                                        $offen = $recipient->status === 'pending';
                                        $ueberfaellig = $inquiry?->due_date && $inquiry->due_date->isPast() && $offen;
                                        $erinnert = $recipient->reminded_at && in_array($recipient->status, ['pending', 'sent']);
                                    @endphp
                                    <tr wire:key="rcp-{{ $recipient->id }}" class="border-b border-[color:var(--nx-line)] last:border-0 hover:bg-[color:var(--nx-hover)]">
                                        <td class="max-w-md px-4 py-2">
                                            <div class="font-medium text-[color:var(--nx-text)]">{{ $inquiry?->entity?->name ?? '–' }}</div>
                                            @if ($inquiry?->context_summary)
                                                <div class="truncate text-xs text-[color:var(--nx-muted)]" title="{{ $inquiry->context_summary }}">{{ $inquiry->context_summary }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-[color:var(--nx-muted)]">{{ $inquiry?->inquiry_type ?? '–' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">{{ ($inquiry?->depth ?? 0) + 1 }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums {{ $ueberfaellig ? 'font-medium text-[color:var(--nx-danger)]' : 'text-[color:var(--nx-muted)]' }}">
                                            {{ $inquiry?->due_date?->format('d.m.Y') ?? '–' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                <x-nx-badge :variant="$statusVariant[$recipient->status] ?? 'neutral'" dot>{{ $statusLabel[$recipient->status] ?? ucfirst($recipient->status) }}</x-nx-badge>
                                                @if ($erinnert)
                                                    <x-nx-badge variant="danger" title="Erinnert am {{ $recipient->reminded_at->format('d.m.Y H:i') }}">Erinnert</x-nx-badge>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2 text-right">
                                            @if ($offen)
                                                <x-nx-button size="sm" variant="primary" :href="route('organization.my-inquiries.respond', $recipient)" wire:navigate>Beantworten</x-nx-button>
                                            @endif
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
        </div>
    </x-ui-page-container>
</x-ui-page>
