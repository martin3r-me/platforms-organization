<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="VSM System" />
    </x-slot>

    <x-slot name="actions">
        <x-ui-button wire:click="save">
            @svg('heroicon-o-check', 'w-4 h-4 mr-2')
            Speichern
        </x-ui-button>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-4">
        <x-ui-input-text name="code" label="Code" wire:model.defer="form.code" required />
        <x-ui-input-text name="name" label="Name" wire:model.defer="form.name" required />
        <x-ui-input-textarea name="description" label="Beschreibung" wire:model.defer="form.description" />
        <x-ui-input-text name="sort_order" type="number" label="Reihenfolge" wire:model.defer="form.sort_order" />
            <div class="flex items-center">
                <input type="checkbox" wire:model.defer="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>


