<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Synthesis Reports'],
        ]">
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Status</label>
                            <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Alle Status</option>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Typ</label>
                            <select wire:model.live="typeFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Alle Typen</option>
                                <option value="weekly">Wöchentlich</option>
                                <option value="monthly">Monatlich</option>
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
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Titel</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Zeitraum</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Signals</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Veröffentlicht</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Erstellt</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->reports as $report)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.synthesis-reports.show', $report) }}" class="link font-medium">
                                {{ $report->title }}
                            </a>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="info">{{ ucfirst($report->report_type ?? '–') }}</x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">
                                @if($report->period_start && $report->period_end)
                                    {{ $report->period_start->format('d.m.Y') }} – {{ $report->period_end->format('d.m.Y') }}
                                @else
                                    –
                                @endif
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ match($report->status) { 'draft' => 'warning', 'published' => 'success', 'archived' => 'muted', default => 'secondary' } }}">
                                {{ ucfirst($report->status) }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ is_array($report->signals_included) ? count($report->signals_included) : 0 }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-muted)]">
                                {{ $report->published_at ? $report->published_at->format('d.m.Y H:i') : '–' }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-muted)]">{{ $report->created_at->format('d.m.Y H:i') }}</span>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="7">
                            <div class="text-center py-8">
                                @svg('heroicon-o-document-text', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                <p class="text-sm text-[var(--ui-muted)]">Keine Synthesis Reports gefunden.</p>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>
