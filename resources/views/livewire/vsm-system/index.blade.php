<x-ui-page>
    <x-slot name="title">VSM Systeme</x-slot>

    <x-slot name="actions">
        <x-ui-button wire:click="create">
            @svg('heroicons.plus', 'w-4 h-4 mr-2')
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
                <x-ui-table-header-cell compact="true">Reihenfolge</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @foreach($this->systems as $sys)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.vsm-systems.show', $sys) }}" class="link">{{ $sys->code }}</a>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $sys->name }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">{{ $sys->sort_order }}</x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $sys->is_active ? 'success' : 'muted' }}">{{ $sys->is_active ? 'Aktiv' : 'Inaktiv' }}</x-ui-badge>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforeach
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <x-ui-modal wire:model.live="modalShow">
        <x-slot name="title">VSM System anlegen</x-slot>
        <x-slot name="body">
            <x-ui-form>
                <x-ui-input-text label="Code" wire:model.defer="form.code" required />
                <x-ui-input-text label="Name" wire:model.defer="form.name" required />
                <x-ui-input-textarea label="Beschreibung" wire:model.defer="form.description" />
                <x-ui-input-text type="number" label="Reihenfolge" wire:model.defer="form.sort_order" />
                <div class="flex items-center">
                    <input type="checkbox" wire:model.defer="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                    <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                </div>
            </x-ui-form>
        </x-slot>
        <x-slot name="footer">
            <x-ui-button variant="muted" wire:click="$set('modalShow', false)">Abbrechen</x-ui-button>
            <x-ui-button wire:click="store">Speichern</x-ui-button>
        </x-slot>
    </x-ui-modal>
</x-ui-page>


