@php
    $vsmLabels = ['carrier' => 'Träger', 'actor' => 'Akteur', 'observed' => 'Beobachtet'];
    $vsmOptions = collect($vsmLabels)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$entityType->name" icon="heroicon-o-cube" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'entity-types'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Einheitstypen', 'href' => route('organization.settings.entity-types.index')],
            ['label' => $entityType->name],
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
                    <x-nx-dropdown-item variant="danger" wire:click="delete" wire:confirm="Einheitstyp „{{ $entityType->name }}“ wirklich löschen?">
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
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $entityType->name }}</h1>
                <span class="font-mono text-xs text-[color:var(--nx-faint)]">{{ $entityType->code }}</span>
                <x-nx-badge :variant="$entityType->is_active ? 'success' : 'neutral'" dot>{{ $entityType->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                @if ($entityType->group)
                    <x-nx-badge>{{ $entityType->group->name }}</x-nx-badge>
                @endif
                @if ($entityType->vsm_class)
                    <x-nx-badge>{{ $vsmLabels[$entityType->vsm_class] ?? $entityType->vsm_class }}</x-nx-badge>
                @endif
                @if ($entityType->can_be_perspective)
                    <x-nx-badge variant="info">Perspektive</x-nx-badge>
                @endif
                <span class="ml-auto text-xs text-[color:var(--nx-faint)]">angelegt {{ $entityType->created_at->format('d.m.Y') }}</span>
            </div>

            <x-nx-panel title="Grunddaten">
                <div class="space-y-3 p-4">
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required />
                        <x-nx-input-text name="code" label="Code" wire:model.live="form.code" errorKey="form.code" required />
                    </div>
                    <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" errorKey="form.description" rows="3" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-text name="icon" label="Icon" wire:model.live="form.icon" errorKey="form.icon" placeholder="heroicon-o-cube" />
                        <x-nx-input-number name="sort_order" label="Sortierung" wire:model.live="form.sort_order" errorKey="form.sort_order" min="0" />
                    </div>
                    <x-nx-input-select
                        name="entity_type_group_id"
                        label="Gruppe"
                        :options="$this->entityTypeGroups"
                        optionValue="id"
                        optionLabel="name"
                        nullable
                        nullLabel="Keine Gruppe"
                        wire:model.live="form.entity_type_group_id"
                        errorKey="form.entity_type_group_id"
                    />
                    <div class="border-t border-[color:var(--nx-line)] pt-3">
                        <x-nx-input-checkbox label="Aktiv" wire:model.live="form.is_active" />
                    </div>
                </div>
            </x-nx-panel>

            <x-nx-panel title="VSM-Klassifikation" subtitle="Rolle des Typs im Viable System Model">
                <div class="space-y-3 p-4">
                    <x-nx-callout variant="neutral" icon="heroicon-o-information-circle">
                        <strong>Träger</strong> tragen Strategie und Perspektive (z. B. Unternehmen, Venture),
                        <strong>Akteure</strong> handeln (Person, Agent), <strong>Beobachtete</strong> werden nur betrachtet.
                        Perspektivfähig sind ausschließlich Träger — das wird beim Speichern automatisch gesetzt.
                    </x-nx-callout>
                    <x-nx-input-select
                        name="vsm_class"
                        label="VSM-Klasse"
                        :options="$vsmOptions"
                        nullable
                        nullLabel="Nicht klassifiziert"
                        wire:model.live="form.vsm_class"
                        errorKey="form.vsm_class"
                    />
                </div>
            </x-nx-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
