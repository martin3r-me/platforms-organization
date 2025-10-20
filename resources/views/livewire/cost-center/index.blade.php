<x-ui-page>
    <x-slot name="title">Kostenstellen</x-slot>

    <x-slot name="actions">
        <x-ui-button wire:click="create">
            @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
            Neu
        </x-ui-button>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar>
            <x-ui-input-text wire:model.live="search" placeholder="Suchen..." />
            <div class="flex items-center">
                <input type="checkbox" wire:model.live="showInactive" id="showInactive" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                <label for="showInactive" class="ml-2 text-sm text-[var(--ui-secondary)]">Inaktive anzeigen</label>
            </div>
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

    <x-ui-modal wire:model.live="modalShow">
        <x-slot name="title">Kostenstelle anlegen</x-slot>
        <x-slot name="body">
            <div class="space-y-4">
                <x-ui-input-text label="Code" wire:model.defer="form.code" />
                <x-ui-input-text label="Name" wire:model.defer="form.name" required />
                <x-ui-input-textarea label="Beschreibung" wire:model.defer="form.description" />
                <div class="flex items-center">
                    <input type="checkbox" wire:model.defer="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                    <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                </div>
            </div>
        </x-slot>
        <x-slot name="footer">
            <x-ui-button variant="muted" wire:click="$set('modalShow', false)">Abbrechen</x-ui-button>
            <x-ui-button wire:click="store">Speichern</x-ui-button>
        </x-slot>
    </x-ui-modal>
</x-ui-page>


