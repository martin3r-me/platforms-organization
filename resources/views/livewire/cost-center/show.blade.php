<x-ui-page>
    <x-slot name="title">Kostenstelle</x-slot>

    <x-slot name="actions">
        <x-ui-button wire:click="save">
            @svg('heroicons.check', 'w-4 h-4 mr-2')
            Speichern
        </x-ui-button>
    </x-slot>

    <x-ui-page-container>
        <x-ui-form>
        <x-ui-input-text label="Code" wire:model.defer="form.code" />
        <x-ui-input-text label="Name" wire:model.defer="form.name" required />
            <x-ui-input-textarea label="Beschreibung" wire:model.defer="form.description" />
            <x-ui-switch label="Aktiv" wire:model.defer="form.is_active" />
        </x-ui-form>
    </x-ui-page-container>
</x-ui-page>


