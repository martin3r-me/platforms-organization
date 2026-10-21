<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Beziehungstypen" icon="heroicon-o-link" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'relation-types'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Beziehungstypen'],
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
                <span>Neuer Beziehungstyp</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <x-nx-card flush>
            @if ($this->relationTypes->isEmpty())
                <x-nx-empty icon="heroicon-o-link">
                    Keine Beziehungstypen gefunden.
                    <x-slot name="action">
                        <x-nx-button size="sm" wire:click="create">Beziehungstyp anlegen</x-nx-button>
                    </x-slot>
                </x-nx-empty>
            @else
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Eigenschaften</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Verleiht</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Status</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach ($this->relationTypes as $relationType)
                            <x-nx-table-row wire:key="rt-{{ $relationType->id }}" clickable :href="route('organization.settings.relation-types.show', $relationType)">
                                <x-nx-table-cell>
                                    <div class="font-medium text-[color:var(--nx-text)]">{{ $relationType->name }}</div>
                                    <div class="font-mono text-xs text-[color:var(--nx-faint)]">{{ $relationType->code }}</div>
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <div class="flex flex-wrap gap-1">
                                        @if ($relationType->is_directional)<x-nx-badge>Gerichtet</x-nx-badge>@endif
                                        @if ($relationType->is_hierarchical)<x-nx-badge>Hierarchisch</x-nx-badge>@endif
                                        @if ($relationType->is_reciprocal)<x-nx-badge>Wechselseitig</x-nx-badge>@endif
                                        @if (! $relationType->is_directional && ! $relationType->is_hierarchical && ! $relationType->is_reciprocal)
                                            <span class="text-[color:var(--nx-faint)]">–</span>
                                        @endif
                                    </div>
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($relationType->capabilities ?? [] as $cap)
                                            <x-nx-badge variant="accent">{{ $cap }}</x-nx-badge>
                                        @empty
                                            <span class="text-[color:var(--nx-faint)]">–</span>
                                        @endforelse
                                    </div>
                                </x-nx-table-cell>
                                <x-nx-table-cell align="right">
                                    <x-nx-badge :variant="$relationType->is_active ? 'success' : 'neutral'" dot>
                                        {{ $relationType->is_active ? 'Aktiv' : 'Inaktiv' }}
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
        <x-slot name="header">Neuer Beziehungstyp</x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required placeholder="z. B. Arbeitet für" />
                <x-nx-input-text name="code" label="Code" wire:model.live="form.code" errorKey="form.code" required placeholder="works_for" />
            </div>
            <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="3" placeholder="Optional" />
            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-text name="icon" label="Icon" wire:model.live="form.icon" placeholder="heroicon-o-arrow-right" />
                <x-nx-input-number name="sort_order" label="Sortierung" wire:model.live="form.sort_order" min="0" />
            </div>
            <div class="flex flex-col items-start gap-2 border-t border-[color:var(--nx-line)] pt-3">
                <x-nx-input-checkbox label="Gerichtet — hat eine Richtung (von → nach)" wire:model.live="form.is_directional" />
                <x-nx-input-checkbox label="Hierarchisch — bildet Über-/Unterordnung" wire:model.live="form.is_hierarchical" />
                <x-nx-input-checkbox label="Wechselseitig — gilt in beide Richtungen" wire:model.live="form.is_reciprocal" />
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
