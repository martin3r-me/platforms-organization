<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Relation Types" icon="heroicon-o-arrows-right-left" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Relation Types..." class="w-full" size="sm" />
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
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
                <x-ui-table-header-cell compact="true">Code</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Eigenschaften</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @foreach($this->relationTypes as $relationType)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <code class="text-xs bg-[var(--ui-muted-5)] px-2 py-1 rounded">{{ $relationType->code }}</code>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.relation-types.show', $relationType) }}" class="link font-medium">
                                {{ $relationType->name }}
                            </a>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex items-center gap-1">
                                @if($relationType->is_directional)
                                    <x-ui-badge variant="info" size="xs">Direktional</x-ui-badge>
                                @endif
                                @if($relationType->is_hierarchical)
                                    <x-ui-badge variant="warning" size="xs">Hierarchisch</x-ui-badge>
                                @endif
                                @if($relationType->is_reciprocal)
                                    <x-ui-badge variant="secondary" size="xs">Reziprok</x-ui-badge>
                                @endif
                                @if(!$relationType->is_directional && !$relationType->is_hierarchical && !$relationType->is_reciprocal)
                                    <span class="text-[var(--ui-muted)]">–</span>
                                @endif
                            </div>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $relationType->is_active ? 'success' : 'muted' }}">
                                {{ $relationType->is_active ? 'Aktiv' : 'Inaktiv' }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.relation-types.show', $relationType) }}" class="text-[var(--ui-primary)] hover:underline text-sm">
                                Bearbeiten
                            </a>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>
