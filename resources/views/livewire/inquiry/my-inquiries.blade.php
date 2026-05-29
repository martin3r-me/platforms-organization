<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Meine Inquiries'],
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
                                <option value="pending">Offen</option>
                                <option value="answered">Beantwortet</option>
                                <option value="timeout">Timeout</option>
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
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md text-green-800 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Entity</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Typ</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Kontext</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Fällig am</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Runde</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktion</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->recipients as $recipient)
                    @php $inquiry = $recipient->inquiry; @endphp
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            @if($inquiry?->entity)
                                <span class="font-medium">{{ $inquiry->entity->name }}</span>
                            @else
                                <span class="text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="info">{{ $inquiry?->inquiry_type ?? '–' }}</x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ \Illuminate\Support\Str::limit($inquiry?->context_summary ?? '–', 60) }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($inquiry?->due_date)
                                <span class="text-sm {{ $inquiry->due_date->isPast() && $recipient->status === 'pending' ? 'text-red-600 font-bold' : 'text-[var(--ui-muted)]' }}">
                                    {{ $inquiry->due_date->format('d.m.Y') }}
                                </span>
                            @else
                                <span class="text-sm text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ match($recipient->status) { 'pending' => 'warning', 'answered' => 'success', 'timeout' => 'danger', default => 'secondary' } }}">
                                {{ match($recipient->status) { 'pending' => 'Offen', 'answered' => 'Beantwortet', 'timeout' => 'Timeout', default => ucfirst($recipient->status) } }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-muted)]">{{ ($inquiry?->depth ?? 0) + 1 }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($recipient->status === 'pending')
                                <a href="{{ route('organization.my-inquiries.respond', $recipient) }}" class="link text-sm font-medium" wire:navigate>
                                    Beantworten
                                </a>
                            @else
                                <span class="text-sm text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="7">
                            <div class="text-center py-8">
                                @svg('heroicon-o-inbox', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                <p class="text-sm text-[var(--ui-muted)]">Keine Inquiries gefunden.</p>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>
