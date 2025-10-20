<x-ui-page>
    <x-slot name="title">Kostenstellen</x-slot>

    <x-slot name="actions">
        <x-ui-button wire:click="create">
            @svg('heroicons.plus', 'w-4 h-4 mr-2')
            Neu
        </x-ui-button>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar>
            <x-ui-search-input wire:model.live="search" placeholder="Suchen..." />
            <x-ui-switch wire:click="toggleInactive" :checked="$showInactive">Inaktive anzeigen</x-ui-switch>
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
            <x-ui-form>
                <x-ui-input label="Code" wire:model.defer="form.code" />
                <x-ui-input label="Name" wire:model.defer="form.name" required />
                <x-ui-textarea label="Beschreibung" wire:model.defer="form.description" />
                <x-ui-switch label="Aktiv" wire:model.defer="form.is_active" />
            </x-ui-form>
        </x-slot>
        <x-slot name="footer">
            <x-ui-button variant="muted" wire:click="$set('modalShow', false)">Abbrechen</x-ui-button>
            <x-ui-button wire:click="store">Speichern</x-ui-button>
        </x-slot>
    </x-ui-modal>
</x-ui-page>


