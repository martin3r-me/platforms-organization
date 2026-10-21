<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Schnittstellen-Kategorien" icon="heroicon-o-tag" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'interlink-categories'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Schnittstellen-Kategorien'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Suchen…" wire:model.live.debounce.300ms="search" />
                    </div>
                    <x-nx-input-checkbox label="Inaktive" wire:model.live="showInactive" />
                </div>
            </x-slot>
            <x-nx-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Kategorie</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <x-nx-card flush>
            @if ($this->categories->isEmpty())
                <x-nx-empty icon="heroicon-o-tag">
                    Keine Schnittstellen-Kategorien gefunden.
                    <x-slot name="action">
                        <x-nx-button size="sm" wire:click="create">Kategorie anlegen</x-nx-button>
                    </x-slot>
                </x-nx-empty>
            @else
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Beschreibung</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Status</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach ($this->categories as $category)
                            <x-nx-table-row wire:key="ic-{{ $category->id }}" clickable :href="route('organization.settings.interlink-categories.show', $category)">
                                <x-nx-table-cell>
                                    <div class="font-medium text-[color:var(--nx-text)]">{{ $category->name }}</div>
                                    <div class="font-mono text-xs text-[color:var(--nx-faint)]">{{ $category->code }}</div>
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    @if ($category->description)
                                        <span class="line-clamp-2 text-sm text-[color:var(--nx-muted)]">{{ $category->description }}</span>
                                    @else
                                        <span class="text-[color:var(--nx-faint)]">–</span>
                                    @endif
                                </x-nx-table-cell>
                                <x-nx-table-cell align="right">
                                    <x-nx-badge :variant="$category->is_active ? 'success' : 'neutral'" dot>
                                        {{ $category->is_active ? 'Aktiv' : 'Inaktiv' }}
                                    </x-nx-badge>
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @endforeach
                    </x-nx-table-body>
                </x-nx-table>
            @endif
        </x-nx-card>
    </x-ui-page-container>

    <x-nx-modal size="md" wire:model="modalShow">
        <x-slot name="header">Neue Schnittstellen-Kategorie</x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required placeholder="Name der Kategorie" />
                <x-nx-input-text name="code" label="Code" wire:model.live="form.code" errorKey="form.code" required placeholder="Eindeutiger Code" />
            </div>
            <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" errorKey="form.description" rows="3" placeholder="Optional" />
            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-text name="icon" label="Icon" wire:model.live="form.icon" errorKey="form.icon" placeholder="heroicon-o-tag" />
                <x-nx-input-number name="sort_order" label="Sortierung" wire:model.live="form.sort_order" errorKey="form.sort_order" min="0" />
            </div>
            <div class="flex flex-col items-start gap-2 border-t border-[color:var(--nx-line)] pt-3">
                <x-nx-input-checkbox label="Aktiv" wire:model.live="form.is_active" />
            </div>
        </form>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="$set('modalShow', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="store">Anlegen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
