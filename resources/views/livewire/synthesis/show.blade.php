@php
    $statusLabel = ['draft' => 'Entwurf', 'published' => 'Veröffentlicht', 'archived' => 'Archiviert'];
    $statusVariant = ['draft' => 'warning', 'published' => 'success', 'archived' => 'neutral'];
    $typLabel = ['weekly' => 'Wöchentlich', 'monthly' => 'Monatlich'];
    $summary = $report->structured_summary ?? [];
    $algedonisch = $report->algedonic_signals ?? [];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$report->title" icon="heroicon-o-document-text" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Synthese-Berichte', 'href' => route('organization.synthesis-reports.index')],
            ['label' => $report->title],
        ]">
            @if ($report->status === 'draft')
                <x-nx-button variant="primary" size="sm" wire:click="publish">
                    @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                    <span>Veröffentlichen</span>
                </x-nx-button>
            @elseif ($report->status === 'published')
                <x-nx-button size="sm" wire:click="archive">
                    @svg('heroicon-o-archive-box', 'w-4 h-4')
                    <span>Archivieren</span>
                </x-nx-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $report->title }}</h1>
                    <x-nx-badge :variant="$statusVariant[$report->status] ?? 'neutral'" dot>{{ $statusLabel[$report->status] ?? ucfirst($report->status) }}</x-nx-badge>
                </div>
                <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-[color:var(--nx-muted)]">
                    <span>{{ $typLabel[$report->report_type] ?? ucfirst($report->report_type ?? '–') }}</span>
                    @if ($report->period_start && $report->period_end)
                        <span>{{ $report->period_start->format('d.m.Y') }} – {{ $report->period_end->format('d.m.Y') }}</span>
                    @endif
                    <span>{{ is_array($report->signals_included) ? count($report->signals_included) : 0 }} Signale</span>
                    <span>erstellt {{ $report->created_at->format('d.m.Y H:i') }}</span>
                    @if ($report->published_at)
                        <span>veröffentlicht {{ $report->published_at->format('d.m.Y H:i') }}</span>
                    @endif
                </div>
            </div>

            @if (count($algedonisch) > 0)
                <x-nx-panel flush title="Algedonische Signale" subtitle="Was sofort Aufmerksamkeit braucht">
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($algedonisch as $a)
                                @php
                                    $sev = $a['severity'] ?? '';
                                    $sevVariant = in_array($sev, ['critical', 'algedonic']) ? 'danger' : ($sev === 'warning' ? 'warning' : 'info');
                                @endphp
                                <tr class="border-b border-[color:var(--nx-line)] align-top last:border-0">
                                    <td class="whitespace-nowrap px-4 py-2 font-medium text-[color:var(--nx-text)]">{{ $a['entity'] ?? '–' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2"><x-nx-badge :variant="$sevVariant" dot>{{ ucfirst($sev ?: '–') }}</x-nx-badge></td>
                                    <td class="px-4 py-2 text-[color:var(--nx-muted)]">{{ $a['message'] ?? '–' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-nx-panel>
            @endif

            <x-nx-panel title="Bericht">
                <x-nx-prose class="text-sm">
                    {!! \Illuminate\Support\Str::markdown($report->content ?? '') !!}
                </x-nx-prose>
            </x-nx-panel>

            @if (count($summary) > 0)
                <x-nx-panel flush title="Kennzahlen">
                    <div class="grid grid-cols-2 gap-px bg-[color:var(--nx-line)] sm:grid-cols-3">
                        @foreach ($summary as $key => $value)
                            <div class="bg-[color:var(--nx-surface)] px-4 py-3">
                                <div class="text-xs text-[color:var(--nx-muted)]">{{ ucfirst(str_replace('_', ' ', $key)) }}</div>
                                <div class="mt-0.5 break-words text-sm font-medium text-[color:var(--nx-text)]">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</div>
                            </div>
                        @endforeach
                    </div>
                </x-nx-panel>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
