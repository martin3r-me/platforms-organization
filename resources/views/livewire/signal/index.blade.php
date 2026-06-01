<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Signale'],
        ]">
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Signale..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Status</label>
                            <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Alle Status</option>
                                <option value="open">Offen</option>
                                <option value="acknowledged">Bestätigt</option>
                                <option value="resolved">Gelöst</option>
                                <option value="dismissed">Verworfen</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Schweregrad</label>
                            <select wire:model.live="severityFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Alle Schweregrade</option>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="critical">Critical</option>
                                <option value="algedonic">Algedonic</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Quelle</label>
                            <select wire:model.live="sourceFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Alle Quellen</option>
                                <option value="rule">Regel</option>
                                <option value="inference">Inference</option>
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
                <x-ui-table-header-cell compact="true">Schweregrad</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Quelle</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Nachricht</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" class="text-center">@svg('heroicon-o-chat-bubble-left', 'w-4 h-4 inline-block')</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Erstellt</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->signals as $signal)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            @if($signal->entity)
                                <a href="{{ route('organization.entities.show', $signal->entity) }}" class="link font-medium">
                                    {{ $signal->entity->name }}
                                </a>
                            @else
                                <span class="text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ match($signal->severity) { 'critical' => 'danger', 'algedonic' => 'danger', 'warning' => 'warning', default => 'info' } }}">
                                {{ ucfirst($signal->severity) }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $signal->source === 'inference' ? 'primary' : 'secondary' }}">
                                {{ $signal->source === 'inference' ? 'Inference' : 'Regel' }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.signals.show', $signal) }}" class="link text-sm">
                                {{ \Illuminate\Support\Str::limit($signal->message, 80) }}
                            </a>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex items-center gap-1.5">
                                <x-ui-badge variant="{{ match($signal->status) { 'open' => 'warning', 'acknowledged' => 'info', 'resolved' => 'success', 'dismissed' => 'muted', default => 'secondary' } }}">
                                    @switch($signal->status)
                                        @case('open') Offen @break
                                        @case('acknowledged') Bestätigt @break
                                        @case('resolved') Gelöst @break
                                        @case('dismissed') Verworfen @break
                                    @endswitch
                                </x-ui-badge>
                                @if($signal->snooze_until && $signal->snooze_until->isFuture())
                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700" title="Snoozed bis {{ $signal->snooze_until->format('d.m.Y') }}">
                                        @svg('heroicon-o-clock', 'w-3 h-3')
                                    </span>
                                @endif
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true" class="text-center">
                            @if($signal->comments_count > 0)
                                <span class="text-xs text-[var(--ui-muted)] tabular-nums">{{ $signal->comments_count }}</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-muted)]">{{ $signal->created_at->format('d.m.Y H:i') }}</span>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="7">
                            <div class="text-center py-8">
                                @svg('heroicon-o-bell-slash', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                <p class="text-sm text-[var(--ui-muted)]">Keine Signale gefunden.</p>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>
