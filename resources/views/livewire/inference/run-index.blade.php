<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Inference Runs'],
        ]">
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Inference Runs..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Status</label>
                            <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Alle Status</option>
                                <option value="running">Running</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
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
                <x-ui-table-header-cell compact="true">ID</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Trigger</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Prompts</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Entities</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Signals</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Inquiries</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Memory</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Dauer</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Model</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Erstellt</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->runs as $run)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <span class="text-xs font-mono text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($run->uuid, 8, '') }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $run->trigger?->name ?? $run->trigger_type ?? '–' }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($run->status === 'failed' && $run->error_message)
                                <span title="{{ $run->error_message }}">
                                    <x-ui-badge variant="danger">Failed</x-ui-badge>
                                </span>
                            @else
                                <x-ui-badge variant="{{ match($run->status) { 'running' => 'warning', 'completed' => 'success', 'failed' => 'danger', default => 'secondary' } }}">
                                    {{ ucfirst($run->status) }}
                                </x-ui-badge>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $run->prompts_evaluated ?? 0 }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $run->entities_analyzed ?? 0 }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $run->signals_created ?? 0 }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $run->inquiries_created ?? 0 }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $run->memory_updates ?? 0 }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-muted)]">
                                @if($run->duration_ms)
                                    {{ number_format($run->duration_ms / 1000, 1) }}s
                                @else
                                    –
                                @endif
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-xs text-[var(--ui-muted)]">{{ $run->llm_model ?? '–' }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-muted)]">{{ $run->created_at->format('d.m.Y H:i') }}</span>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="11">
                            <div class="text-center py-8">
                                @svg('heroicon-o-play', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                <p class="text-sm text-[var(--ui-muted)]">Keine Inference Runs gefunden.</p>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>
