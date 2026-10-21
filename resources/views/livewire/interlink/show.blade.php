@php
    $links = $this->linkedRelationships;
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$interlink->name" icon="heroicon-o-arrows-right-left" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Interlink" width="w-64" :defaultOpen="true" side="left">
            <div class="flex flex-col gap-0.5 p-2">
                <x-nx-property-row icon="heroicon-o-signal" label="Status">
                    <x-nx-badge :variant="$interlink->is_active ? 'success' : 'neutral'" dot>{{ $interlink->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                </x-nx-property-row>
                <x-nx-property-row icon="heroicon-o-arrows-right-left" label="Richtung">
                    {{ $interlink->is_bidirectional ? 'Bidirektional' : 'Einseitig' }}
                </x-nx-property-row>
                <x-nx-property-row icon="heroicon-o-tag" label="Kategorie">
                    {{ $interlink->category?->name ?? '–' }}
                </x-nx-property-row>
                <x-nx-property-row icon="heroicon-o-squares-2x2" label="Typ">
                    {{ $interlink->type?->name ?? '–' }}
                </x-nx-property-row>
                <x-nx-property-row icon="heroicon-o-calendar" label="Gültig">
                    @if ($interlink->valid_from || $interlink->valid_to)
                        {{ $interlink->valid_from?->format('d.m.Y') ?? '–' }} → {{ $interlink->valid_to?->format('d.m.Y') ?? '–' }}
                    @else
                        <span class="text-[color:var(--nx-faint)]">unbegrenzt</span>
                    @endif
                </x-nx-property-row>
                <x-nx-property-row icon="heroicon-o-link" label="Beziehungen">
                    {{ $links->count() }}
                </x-nx-property-row>
                <x-nx-property-row icon="heroicon-o-clock" label="Erstellt">
                    {{ $interlink->created_at->format('d.m.Y H:i') }}
                </x-nx-property-row>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Interlinks', 'href' => route('organization.interlinks.index')],
            ['label' => $interlink->name],
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
                    <x-nx-dropdown-item variant="danger" wire:click="delete" wire:confirm="Interlink „{{ $interlink->name }}“ wirklich löschen?">
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        Löschen
                    </x-nx-dropdown-item>
                </x-nx-dropdown>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $interlink->name }}</h1>
                <x-nx-badge :variant="$interlink->is_active ? 'success' : 'neutral'" dot>{{ $interlink->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
            </div>

            <x-nx-tabs class="!mb-0">
                <x-nx-tab :active="$activeTab === 'details'" wire:click="$set('activeTab', 'details')">Details</x-nx-tab>
                <x-nx-tab :active="$activeTab === 'relationships'" wire:click="$set('activeTab', 'relationships')">
                    Beziehungen <span class="ml-1 text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $links->count() }}</span>
                </x-nx-tab>
            </x-nx-tabs>

            @if ($activeTab === 'details')
                <x-nx-panel title="Grunddaten">
                    <div class="space-y-3 p-4">
                        <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required />
                        <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" errorKey="form.description" rows="3" />
                        <div class="grid grid-cols-2 gap-3">
                            <x-nx-input-select name="category_id" label="Kategorie" wire:model.live="form.category_id" errorKey="form.category_id"
                                :options="$this->availableCategories" optionValue="id" optionLabel="name" nullable nullLabel="Kategorie wählen" required />
                            <x-nx-input-select name="type_id" label="Typ" wire:model.live="form.type_id" errorKey="form.type_id"
                                :options="$this->availableTypes" optionValue="id" optionLabel="name" nullable nullLabel="Typ wählen" required />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <x-nx-input-date name="valid_from" label="Gültig von" hint="optional" wire:model.live="form.valid_from" errorKey="form.valid_from" />
                            <x-nx-input-date name="valid_to" label="Gültig bis" hint="optional" wire:model.live="form.valid_to" errorKey="form.valid_to" />
                        </div>
                    </div>
                </x-nx-panel>

                <x-nx-panel title="Eigenschaften">
                    <div class="flex flex-col items-start gap-2.5 p-4">
                        <x-nx-input-checkbox wire:model.live="form.is_bidirectional">
                            <span>Bidirektional <span class="text-xs text-[color:var(--nx-muted)]">— gilt in beide Richtungen</span></span>
                        </x-nx-input-checkbox>
                        <div class="w-full border-t border-[color:var(--nx-line)] pt-2.5">
                            <x-nx-input-checkbox label="Aktiv" wire:model.live="form.is_active" />
                        </div>
                    </div>
                </x-nx-panel>
            @endif

            @if ($activeTab === 'relationships')
                <x-nx-card flush>
                    @if ($links->isEmpty())
                        <x-nx-empty icon="heroicon-o-link">
                            Dieser Interlink wird noch in keiner Beziehung verwendet.
                        </x-nx-empty>
                    @else
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-[color:var(--nx-muted)]">
                                    <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Von</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Beziehung</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Nach</th>
                                    <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Notiz</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($links as $pivot)
                                    @continue(! $pivot->entityRelationship)
                                    @php
                                        $rel = $pivot->entityRelationship;
                                    @endphp
                                    <tr wire:key="rel-{{ $pivot->id }}" class="border-b border-[color:var(--nx-line)] last:border-0 hover:bg-[color:var(--nx-hover)]">
                                        <td class="px-4 py-2">
                                            @if ($rel->fromEntity)
                                                <a href="{{ route('organization.entities.show', $rel->fromEntity) }}" wire:navigate class="font-medium text-[color:var(--nx-text)] hover:underline">{{ $rel->fromEntity->name }}</a>
                                                <div class="text-xs text-[color:var(--nx-faint)]">{{ $rel->fromEntity->type?->name }}</div>
                                            @else
                                                <span class="text-[color:var(--nx-faint)]">Unbekannt</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-[color:var(--nx-muted)]">{{ $rel->relationType?->name ?? '–' }}</td>
                                        <td class="px-3 py-2">
                                            @if ($rel->toEntity)
                                                <a href="{{ route('organization.entities.show', $rel->toEntity) }}" wire:navigate class="font-medium text-[color:var(--nx-text)] hover:underline">{{ $rel->toEntity->name }}</a>
                                                <div class="text-xs text-[color:var(--nx-faint)]">{{ $rel->toEntity->type?->name }}</div>
                                            @else
                                                <span class="text-[color:var(--nx-faint)]">Unbekannt</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-[color:var(--nx-muted)]">{{ $pivot->note ?: '–' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-nx-card>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
