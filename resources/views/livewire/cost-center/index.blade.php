<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Kostenstellen" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktionen</h3>
                    <div class="space-y-2">
                        <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Kostenstellen..." class="w-full" size="sm" />
                        <x-ui-button variant="secondary" size="sm" wire:click="create" class="w-full justify-start">
                            @svg('heroicon-o-plus','w-4 h-4')
                            <span class="ml-2">Neue Kostenstelle</span>
                        </x-ui-button>
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
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @foreach($this->costCenters as $cc)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.cost-centers.show', $cc) }}" class="link">{{ $cc->code }}</a>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $cc->name }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $cc->is_active ? 'success' : 'muted' }}">{{ $cc->is_active ? 'Aktiv' : 'Inaktiv' }}</x-ui-badge>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <!-- Create Cost Center Modal -->
    <x-ui-modal
        wire:model="modalShow"
        size="lg"
    >
        <x-slot name="header">
            Neue Kostenstelle erstellen
        </x-slot>

        <div class="space-y-4">
            <form wire:submit.prevent="store" class="space-y-4">
                <div class="grid grid-cols-1 gap-4">
                    <x-ui-input-text
                        name="code"
                        label="Code"
                        wire:model.live="form.code"
                        placeholder="Optionaler Code"
                    />
                    
                    <x-ui-input-text
                        name="name"
                        label="Name"
                        wire:model.live="form.name"
                        required
                        placeholder="Name der Kostenstelle"
                    />
                    
                    <x-ui-input-textarea
                        name="description"
                        label="Beschreibung"
                        wire:model.live="form.description"
                        placeholder="Optionale Beschreibung"
                        rows="3"
                    />
                </div>

                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        wire:model.live="form.is_active" 
                        id="is_active"
                        class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                    />
                    <label for="is_active" class="ml-2 text-sm text-gray-700">Aktiv</label>
                </div>
            </form>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    wire:click="$set('modalShow', false)"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="store">
                    @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                    Erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>


