<x-ui-page>
    <x-slot name="title">VSM System</x-slot>

    <x-slot name="actions">
        <x-ui-button wire:click="save">
            @svg('heroicons.check', 'w-4 h-4 mr-2')
            Speichern
        </x-ui-button>
    </x-slot>

    <x-ui-page-container>
        <x-ui-form>
            <x-ui-input label="Code" wire:model.defer="form.code" required />
            <x-ui-input label="Name" wire:model.defer="form.name" required />
            <x-ui-textarea label="Beschreibung" wire:model.defer="form.description" />
            <x-ui-input type="number" label="Reihenfolge" wire:model.defer="form.sort_order" />
            <x-ui-switch label="Aktiv" wire:model.defer="form.is_active" />
        </x-ui-form>
    </x-ui-page-container>
</x-ui-page>


