<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$entityTypeGroup->name" icon="heroicon-o-rectangle-stack" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'entity-type-groups'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Typ-Gruppen', 'href' => route('organization.settings.entity-type-groups.index')],
            ['label' => $entityTypeGroup->name],
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
                    <x-nx-dropdown-item variant="danger" wire:click="delete" wire:confirm="Typ-Gruppe „{{ $entityTypeGroup->name }}“ wirklich löschen?">
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        Löschen
                    </x-nx-dropdown-item>
                </x-nx-dropdown>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $entityTypeGroup->name }}</h1>
                <x-nx-badge :variant="$entityTypeGroup->is_active ? 'success' : 'neutral'" dot>{{ $entityTypeGroup->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                <span class="ml-auto text-xs text-[color:var(--nx-faint)]">angelegt {{ $entityTypeGroup->created_at->format('d.m.Y') }}</span>
            </div>

            <x-nx-panel title="Grunddaten">
                <div class="space-y-3 p-4">
                    <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required />
                    <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" errorKey="form.description" rows="3" />
                    <x-nx-input-number name="sort_order" label="Sortierung" wire:model.live="form.sort_order" errorKey="form.sort_order" min="0" />
                    <div class="border-t border-[color:var(--nx-line)] pt-3">
                        <x-nx-input-checkbox label="Aktiv" wire:model.live="form.is_active" />
                    </div>
                </div>
            </x-nx-panel>

            <x-nx-panel title="Zugehörige Einheitstypen" flush>
                @if ($this->entityTypes->count() > 0)
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right">Status</x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach ($this->entityTypes as $entityType)
                                <x-nx-table-row wire:key="et-{{ $entityType->id }}" clickable :href="route('organization.settings.entity-types.show', $entityType)">
                                    <x-nx-table-cell>
                                        <div class="font-medium text-[color:var(--nx-text)]">{{ $entityType->name }}</div>
                                        <div class="font-mono text-xs text-[color:var(--nx-faint)]">{{ $entityType->code }}</div>
                                    </x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        <x-nx-badge :variant="$entityType->is_active ? 'success' : 'neutral'" dot>
                                            {{ $entityType->is_active ? 'Aktiv' : 'Inaktiv' }}
                                        </x-nx-badge>
                                    </x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                @else
                    <x-nx-empty icon="heroicon-o-cube">Keine Einheitstypen in dieser Gruppe.</x-nx-empty>
                @endif
            </x-nx-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
