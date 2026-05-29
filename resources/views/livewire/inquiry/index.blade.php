<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Inquiries'],
        ]">
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Inquiries..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Status</label>
                            <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Alle Status</option>
                                <option value="pending">Pending</option>
                                <option value="partial">Partial</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
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
                <x-ui-table-header-cell compact="true">Entity</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Prompt</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Empfänger</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Fällig am</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Erstellt</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->inquiries as $inquiry)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            @if($inquiry->entity)
                                <a href="{{ route('organization.entities.show', $inquiry->entity) }}" class="link font-medium">
                                    {{ $inquiry->entity->name }}
                                </a>
                            @else
                                <span class="text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="info">{{ $inquiry->inquiry_type ?? '–' }}</x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $inquiry->inferencePrompt?->name ?? '–' }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ match($inquiry->status) { 'pending' => 'warning', 'partial' => 'info', 'completed' => 'success', 'cancelled' => 'muted', default => 'secondary' } }}">
                                {{ ucfirst($inquiry->status) }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $inquiry->recipients->count() }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($inquiry->due_date)
                                <span class="text-sm {{ $inquiry->due_date->isPast() && in_array($inquiry->status, ['pending', 'partial']) ? 'text-red-600 font-medium' : 'text-[var(--ui-muted)]' }}">
                                    {{ $inquiry->due_date->format('d.m.Y') }}
                                </span>
                            @else
                                <span class="text-sm text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-muted)]">{{ $inquiry->created_at->format('d.m.Y H:i') }}</span>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="7">
                            <div class="text-center py-8">
                                @svg('heroicon-o-question-mark-circle', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                <p class="text-sm text-[var(--ui-muted)]">Keine Inquiries gefunden.</p>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>
