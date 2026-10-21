@php
    $tree = $this->itemTree;
    $stats = $this->stats;
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Interlinks" icon="heroicon-o-arrows-right-left" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-64" :defaultOpen="true" side="left">
            <div class="flex flex-col gap-3 p-3">
                <x-nx-input-select name="selectedCategoryId" label="Kategorie" size="sm" wire:model.live="selectedCategoryId"
                    :options="$this->availableCategories" optionValue="id" optionLabel="name" nullable nullLabel="Alle" />
                <x-nx-input-select name="selectedTypeId" label="Typ" size="sm" wire:model.live="selectedTypeId"
                    :options="$this->availableTypes" optionValue="id" optionLabel="name" nullable nullLabel="Alle" />
                <div class="border-t border-[color:var(--nx-line)] pt-3">
                    <div class="flex flex-col gap-1 text-xs text-[color:var(--nx-muted)]">
                        <div class="flex justify-between"><span>Gesamt</span><span class="tabular-nums text-[color:var(--nx-text)]">{{ $stats['total'] }}</span></div>
                        <div class="flex justify-between"><span>Aktiv</span><span class="tabular-nums text-[color:var(--nx-text)]">{{ $stats['active'] }}</span></div>
                        <div class="flex justify-between"><span>Inaktiv</span><span class="tabular-nums text-[color:var(--nx-text)]">{{ $stats['inactive'] }}</span></div>
                        <div class="flex justify-between"><span>Bidirektional</span><span class="tabular-nums text-[color:var(--nx-text)]">{{ $stats['bidirectional'] }}</span></div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Interlinks'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Suchen…" wire:model.live.debounce.400ms="search" />
                    </div>
                    <x-nx-input-checkbox label="Inaktive" wire:model.live="showInactive" />
                </div>
            </x-slot>
            <x-nx-button variant="primary" size="sm" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Interlink</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-4">
            @if (session()->has('message'))
                <x-nx-callout variant="success">{{ session('message') }}</x-nx-callout>
            @endif
            @if (session()->has('error'))
                <x-nx-callout variant="danger">{{ session('error') }}</x-nx-callout>
            @endif

            <x-nx-card flush>
                @if (count($tree) === 0)
                    <x-nx-empty icon="heroicon-o-arrows-right-left">
                        Keine Interlinks gefunden.
                        <x-slot name="action">
                            <x-nx-button size="sm" wire:click="openCreateModal">Interlink anlegen</x-nx-button>
                        </x-slot>
                    </x-nx-empty>
                @else
                    {{-- Baum: Owner-Einheit → Interlinks; schlichtes Markup, eine Zeile je Eintrag --}}
                    <div class="py-1">
                        @foreach ($tree as $node)
                            @include('organization::livewire.interlink.partials.tree-node', ['node' => $node])
                        @endforeach
                    </div>
                @endif
            </x-nx-card>
        </div>
    </x-ui-page-container>

    <x-nx-modal size="lg" wire:model="modalShow">
        <x-slot name="header">Neuer Interlink</x-slot>

        <form wire:submit.prevent="createInterlink" class="space-y-4">
            <x-nx-input-text name="name" label="Name" wire:model.live="newInterlink.name" errorKey="newInterlink.name" required placeholder="Name des Interlinks" />
            <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="newInterlink.description" errorKey="newInterlink.description" rows="3" placeholder="Optional" />

            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-select name="category_id" label="Kategorie" wire:model.live="newInterlink.category_id" errorKey="newInterlink.category_id"
                    :options="$this->availableCategories" optionValue="id" optionLabel="name" nullable nullLabel="Kategorie wählen" required />
                <x-nx-input-select name="type_id" label="Typ" wire:model.live="newInterlink.type_id" errorKey="newInterlink.type_id"
                    :options="$this->availableTypes" optionValue="id" optionLabel="name" nullable nullLabel="Typ wählen" required />
            </div>

            <x-nx-input-select name="owner_entity_id" label="Owner (Einheit)" wire:model.live="newInterlink.owner_entity_id" errorKey="newInterlink.owner_entity_id"
                :options="$this->availableEntities" optionValue="id" optionLabel="name" nullable nullLabel="Kein Owner" />

            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-date name="valid_from" label="Gültig von" hint="optional" wire:model.live="newInterlink.valid_from" errorKey="newInterlink.valid_from" />
                <x-nx-input-date name="valid_to" label="Gültig bis" hint="optional" wire:model.live="newInterlink.valid_to" errorKey="newInterlink.valid_to" />
            </div>

            <div class="flex flex-col items-start gap-2 border-t border-[color:var(--nx-line)] pt-3">
                <x-nx-input-checkbox label="Bidirektional — gilt in beide Richtungen" wire:model.live="newInterlink.is_bidirectional" />
                <x-nx-input-checkbox label="Aktiv" wire:model.live="newInterlink.is_active" />
            </div>
        </form>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeCreateModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="createInterlink">Anlegen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
