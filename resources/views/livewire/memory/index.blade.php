<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Memory'],
        ]">
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Memory Entries..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Typ</label>
                            <select wire:model.live="typeFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                                <option value="">Alle Typen</option>
                                @foreach($this->memoryTypes as $type)
                                    <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="showInactive" id="showInactive" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="showInactive" class="ml-2 text-sm text-[var(--ui-secondary)]">Inaktive anzeigen</label>
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
                <x-ui-table-header-cell compact="true">Content</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Confidence</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Reinforcements</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Gültig bis</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->memoryEntries as $entry)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            @if($entry->entity)
                                <a href="{{ route('organization.entities.show', $entry->entity) }}" class="link font-medium">
                                    {{ $entry->entity->name }}
                                </a>
                            @else
                                <span class="text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="info">{{ ucfirst(str_replace('_', ' ', $entry->memory_type ?? '–')) }}</x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ \Illuminate\Support\Str::limit($entry->content, 80) }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ number_format($entry->confidence * 100, 0) }}%</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $entry->reinforcement_count }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm text-[var(--ui-muted)]">
                                @if($entry->valid_until)
                                    <span class="{{ $entry->valid_until->isPast() ? 'text-red-600' : '' }}">
                                        {{ $entry->valid_until->format('d.m.Y') }}
                                    </span>
                                @else
                                    Unbegrenzt
                                @endif
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $entry->is_active ? 'success' : 'muted' }}">
                                {{ $entry->is_active ? 'Aktiv' : 'Inaktiv' }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="7">
                            <div class="text-center py-8">
                                @svg('heroicon-o-circle-stack', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                <p class="text-sm text-[var(--ui-muted)]">Keine Memory Entries gefunden.</p>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>
