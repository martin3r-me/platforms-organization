@php
    $alle = $this->reports;
    $zeilen = $alle->take($limit);
    $statusLabel = ['draft' => 'Entwurf', 'published' => 'Veröffentlicht', 'archived' => 'Archiviert'];
    $statusVariant = ['draft' => 'warning', 'published' => 'success', 'archived' => 'neutral'];
    $typLabel = ['weekly' => 'Wöchentlich', 'monthly' => 'Monatlich'];
    $opts = fn (array $m) => collect($m)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all();
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Synthese-Berichte" icon="heroicon-o-document-text" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Synthese-Berichte'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-44">
                        <x-nx-input-select name="statusFilter" size="sm" wire:model.live="statusFilter" nullable nullLabel="Alle Status" :options="$opts($statusLabel)" />
                    </div>
                    <div class="w-44">
                        <x-nx-input-select name="typeFilter" size="sm" wire:model.live="typeFilter" nullable nullLabel="Alle Typen" :options="$opts($typLabel)" />
                    </div>
                </div>
            </x-slot>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <x-nx-card flush>
            @if ($alle->isEmpty())
                <x-nx-empty icon="heroicon-o-document-text">Keine Synthese-Berichte gefunden.</x-nx-empty>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-[color:var(--nx-muted)]">
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Titel</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Zeitraum</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Signale</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Erstellt</th>
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-right font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($zeilen as $report)
                                <tr wire:key="syn-{{ $report->id }}" class="cursor-pointer border-b border-[color:var(--nx-line)] last:border-0 hover:bg-[color:var(--nx-hover)]"
                                    onclick="window.location.href='{{ route('organization.synthesis-reports.show', $report) }}'">
                                    <td class="px-4 py-2">
                                        <a href="{{ route('organization.synthesis-reports.show', $report) }}" wire:navigate class="font-medium text-[color:var(--nx-text)] hover:underline">{{ $report->title }}</a>
                                        <div class="text-xs text-[color:var(--nx-faint)]">{{ $typLabel[$report->report_type] ?? ucfirst($report->report_type ?? '–') }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 tabular-nums text-[color:var(--nx-muted)]">
                                        @if ($report->period_start && $report->period_end)
                                            {{ $report->period_start->format('d.m.Y') }} – {{ $report->period_end->format('d.m.Y') }}
                                        @else
                                            –
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">{{ is_array($report->signals_included) ? count($report->signals_included) : 0 }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">{{ $report->created_at->format('d.m.Y') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right">
                                        <x-nx-badge :variant="$statusVariant[$report->status] ?? 'neutral'" dot>{{ $statusLabel[$report->status] ?? ucfirst($report->status) }}</x-nx-badge>
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
