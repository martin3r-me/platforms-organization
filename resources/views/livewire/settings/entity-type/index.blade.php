<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Entity Types" icon="heroicon-o-cube" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Entity Types..." class="w-full" size="sm" />
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Gruppe</label>
                            <select 
                                name="selectedGroup"
                                wire:model.live="selectedGroup"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="">Alle Gruppen</option>
                                @foreach($this->entityTypeGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
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
                <x-ui-table-header-cell compact="true">Code</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Gruppe</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @foreach($this->entityTypes as $entityType)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <code class="text-xs bg-[var(--ui-muted-5)] px-2 py-1 rounded">{{ $entityType->code }}</code>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.entity-types.show', $entityType) }}" class="link font-medium">
                                {{ $entityType->name }}
                            </a>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($entityType->group)
                                <x-ui-badge variant="info">{{ $entityType->group->name }}</x-ui-badge>
                            @else
                                <span class="text-[var(--ui-muted)]">–</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $entityType->is_active ? 'success' : 'muted' }}">
                                {{ $entityType->is_active ? 'Aktiv' : 'Inaktiv' }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.entity-types.show', $entityType) }}" class="text-[var(--ui-primary)] hover:underline text-sm">
                                Bearbeiten
                            </a>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>

