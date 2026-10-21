<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$category->name" icon="heroicon-o-tag" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'interlink-categories'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Schnittstellen-Kategorien', 'href' => route('organization.settings.interlink-categories.index')],
            ['label' => $category->name],
        ]">
            <div class="flex items-center gap-2">
                @if ($this->isDirty)
                    <x-nx-button variant="primary" size="sm" wire:click="save">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Speichern</span>
                    </x-nx-button>
                @endif
                <x-nx-dropdown>
                    @if ($this->isDirty)
                        <x-nx-dropdown-item wire:click="loadForm">
                            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                            Änderungen verwerfen
                        </x-nx-dropdown-item>
                        <x-nx-dropdown-divider />
                    @endif
                    <x-nx-dropdown-item variant="danger" wire:click="delete" wire:confirm="Kategorie „{{ $category->name }}“ wirklich löschen?">
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        Löschen
                    </x-nx-dropdown-item>
                </x-nx-dropdown>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            {{-- Kopf: Identität + Zustand --}}
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $category->name }}</h1>
                <span class="font-mono text-xs text-[color:var(--nx-faint)]">{{ $category->code }}</span>
                <x-nx-badge :variant="$category->is_active ? 'success' : 'neutral'" dot>{{ $category->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                <span class="ml-auto text-xs text-[color:var(--nx-faint)]">angelegt {{ $category->created_at->format('d.m.Y') }}</span>
            </div>

            <x-nx-panel title="Grunddaten">
                <div class="space-y-3 p-4">
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required />
                        <x-nx-input-text name="code" label="Code" wire:model.live="form.code" errorKey="form.code" required />
                    </div>
                    <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" errorKey="form.description" rows="3" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-text name="icon" label="Icon" wire:model.live="form.icon" errorKey="form.icon" placeholder="heroicon-o-tag" />
                        <x-nx-input-number name="sort_order" label="Sortierung" wire:model.live="form.sort_order" errorKey="form.sort_order" min="0" />
                    </div>
                    <div class="flex flex-col items-start gap-2.5 border-t border-[color:var(--nx-line)] pt-3">
                        <x-nx-input-checkbox label="Aktiv" wire:model.live="form.is_active" />
                    </div>
                </div>
            </x-nx-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
