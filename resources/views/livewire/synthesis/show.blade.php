<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Synthesis Reports', 'href' => route('organization.synthesis-reports.index')],
            ['label' => $report->title],
        ]">
            @if($report->status === 'draft')
                <x-ui-button variant="primary" size="sm" wire:click="publish">
                    @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                    <span>Veröffentlichen</span>
                </x-ui-button>
            @elseif($report->status === 'published')
                <x-ui-button variant="ghost" size="sm" wire:click="archive">
                    @svg('heroicon-o-archive-box', 'w-4 h-4')
                    <span>Archivieren</span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <x-ui-badge variant="{{ match($report->status) { 'draft' => 'warning', 'published' => 'success', 'archived' => 'muted', default => 'secondary' } }}">
                        {{ ucfirst($report->status) }}
                    </x-ui-badge>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Typ</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ ucfirst($report->report_type ?? '–') }}</div>
                        </div>
                        @if($report->period_start && $report->period_end)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Zeitraum</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $report->period_start->format('d.m.Y') }} – {{ $report->period_end->format('d.m.Y') }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Signals</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ is_array($report->signals_included) ? count($report->signals_included) : 0 }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $report->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        @if($report->published_at)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Veröffentlicht</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $report->published_at->format('d.m.Y H:i') }}</div>
                            </div>
                        @endif
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
        <div class="space-y-6">
            {{-- Report Content --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Report</h2>
                <div class="prose prose-sm max-w-none text-[var(--ui-secondary)]">
                    {!! \Illuminate\Support\Str::markdown($report->content ?? '') !!}
                </div>
            </div>

            {{-- Structured Summary --}}
            @if($report->structured_summary && count($report->structured_summary) > 0)
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Structured Summary</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($report->structured_summary as $key => $value)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)] mt-0.5">
                                    {{ is_array($value) ? json_encode($value) : $value }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Algedonic Signals --}}
            @if($report->algedonic_signals && count($report->algedonic_signals) > 0)
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Algedonic Signals</h2>
                    <x-ui-table compact="true">
                        <x-ui-table-header>
                            <x-ui-table-header-cell compact="true">Entity</x-ui-table-header-cell>
                            <x-ui-table-header-cell compact="true">Severity</x-ui-table-header-cell>
                            <x-ui-table-header-cell compact="true">Message</x-ui-table-header-cell>
                        </x-ui-table-header>
                        <x-ui-table-body>
                            @foreach($report->algedonic_signals as $algedonic)
                                <x-ui-table-row compact="true">
                                    <x-ui-table-cell compact="true">
                                        <span class="text-sm text-[var(--ui-secondary)]">{{ $algedonic['entity'] ?? '–' }}</span>
                                    </x-ui-table-cell>
                                    <x-ui-table-cell compact="true">
                                        <x-ui-badge variant="{{ match($algedonic['severity'] ?? '') { 'critical' => 'danger', 'algedonic' => 'danger', 'warning' => 'warning', default => 'info' } }}">
                                            {{ ucfirst($algedonic['severity'] ?? '–') }}
                                        </x-ui-badge>
                                    </x-ui-table-cell>
                                    <x-ui-table-cell compact="true">
                                        <span class="text-sm text-[var(--ui-secondary)]">{{ $algedonic['message'] ?? '–' }}</span>
                                    </x-ui-table-cell>
                                </x-ui-table-row>
                            @endforeach
                        </x-ui-table-body>
                    </x-ui-table>
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
