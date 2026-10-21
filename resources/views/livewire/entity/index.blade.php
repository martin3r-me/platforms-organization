@php
    $rootsByGroup = $this->entities['rootsByGroup'];
    $childrenByParent = $this->entities['childrenByParent'];
    $stats = $this->stats;

    $typeOptions = $this->entityTypes->flatten()->map(fn ($t) => ['value' => $t->id, 'label' => $t->name])->values()->all();
    $groupOptions = $this->entityTypeGroups->map(fn ($g) => ['value' => $g->id, 'label' => $g->name])->values()->all();
    $parentOptions = $this->parentEntities->map(fn ($e) => ['value' => $e->id, 'label' => $e->name])->values()->all();

    // Für die Zeilen einmal auflösen statt pro Zeile über $this->…
    $rowCtx = [
        'childrenByParent' => $childrenByParent,
        'entityMovements' => $this->entityMovements,
        'vsmSystemMap' => $this->vsmSystemMap,
        'signalCounts' => $this->signalCounts,
    ];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Einheiten" icon="heroicon-o-building-office" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-64" :defaultOpen="true" side="left">
            <div class="flex flex-col gap-3 p-3">
                <x-nx-input-select name="selectedGroup" label="Gruppe" size="sm" wire:model.live="selectedGroup"
                    :options="$groupOptions" nullable nullLabel="Alle" />
                <x-nx-input-select name="selectedType" label="Typ" size="sm" wire:model.live="selectedType"
                    :options="$typeOptions" nullable nullLabel="Alle" />
                <div class="flex flex-col items-start gap-2 border-t border-[color:var(--nx-line)] pt-3">
                    <x-nx-input-checkbox label="Inaktive anzeigen" wire:model.live="showInactive" />
                    <x-nx-input-checkbox label="Nur mit Signalen" wire:model.live="onlyWithSignals" />
                </div>

                <div class="border-t border-[color:var(--nx-line)] pt-3">
                    <span class="text-xs font-medium tracking-wide text-[color:var(--nx-faint)]">VSM-Klasse</span>
                    <div class="mt-2 flex flex-col gap-1.5 text-xs text-[color:var(--nx-muted)]">
                        <div class="flex items-center gap-2" title="Lebensfähiges System, kann eigene Perspektive sein">
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded-[4px] bg-[rgba(47,158,68,.12)] text-[10px] font-semibold text-[color:var(--nx-success)]">C</span>
                            Carrier · Perspektive möglich
                        </div>
                        <div class="flex items-center gap-2" title="Füllt VSM-Funktionen aus, empfängt Signale">
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded-[4px] bg-[color:var(--nx-accent-soft)] text-[10px] font-semibold text-[color:var(--nx-muted)]">A</span>
                            Actor · füllt Funktion aus
                        </div>
                        <div class="flex items-center gap-2" title="Umwelt, wird beobachtet">
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded-[4px] bg-[rgba(232,89,12,.12)] text-[10px] font-semibold text-[color:var(--nx-warning)]">O</span>
                            Observed · Umwelt
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Einheiten'],
        ]">
            <x-slot name="left">
                <div class="w-64">
                    <x-nx-input-text name="search" size="sm" placeholder="Name oder Beschreibung…" wire:model.live.debounce.400ms="search" />
                </div>
            </x-slot>
            <x-nx-button variant="primary" size="sm" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Einheit</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">Einheiten</h1>
                <span class="text-sm text-[color:var(--nx-muted)]">
                    {{ $stats['active'] }} aktiv
                    @if ($stats['inactive'] > 0)
                        · {{ $stats['inactive'] }} inaktiv
                    @endif
                </span>
            </div>

            @if ($rootsByGroup->isEmpty())
                <x-nx-card flush>
                    <x-nx-empty icon="heroicon-o-building-office">
                        Keine Einheiten gefunden.
                        <x-slot name="action">
                            <x-nx-button size="sm" wire:click="openCreateModal">Einheit anlegen</x-nx-button>
                        </x-slot>
                    </x-nx-empty>
                </x-nx-card>
            @else
                @foreach ($rootsByGroup as $groupId => $groupEntities)
                    <x-nx-panel flush wire:key="grp-{{ $groupId }}" :title="$groupEntities->first()->type->group->name ?? 'Sonstige'">
                        <x-slot name="actions">
                            <x-nx-badge>{{ $groupEntities->count() }}</x-nx-badge>
                        </x-slot>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-[color:var(--nx-muted)]">
                                        <th class="border-b border-[color:var(--nx-line)] px-4 py-2 text-left font-medium">Name</th>
                                        <th class="border-b border-[color:var(--nx-line)] px-3 py-2 text-left font-medium">Typ</th>
                                        <th class="border-b border-[color:var(--nx-line)] px-3 py-2 text-left font-medium">Beziehungen</th>
                                        <th class="border-b border-[color:var(--nx-line)] px-3 py-2 text-left font-medium">VSM</th>
                                        <th class="border-b border-[color:var(--nx-line)] px-3 py-2 text-left font-medium">Signale</th>
                                        <th class="border-b border-[color:var(--nx-line)] px-4 py-2 text-left font-medium">Bewegung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($groupEntities as $entity)
                                        @include('organization::livewire.entity.partials.tree-table-row', $rowCtx + ['entity' => $entity, 'depth' => 0])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-nx-panel>
                @endforeach
            @endif
        </div>
    </x-ui-page-container>

    <x-nx-modal size="md" wire:model="modalShow">
        <x-slot name="header">Neue Einheit</x-slot>

        <form wire:submit.prevent="createEntity" class="space-y-4">
            <x-nx-input-text name="name" label="Name" wire:model.live="newEntity.name" errorKey="newEntity.name" required placeholder="Name der Einheit" />
            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-select name="entity_type_id" label="Typ" wire:model.live="newEntity.entity_type_id" errorKey="newEntity.entity_type_id"
                    :options="$typeOptions" nullable nullLabel="Typ wählen" required />
                <x-nx-input-text name="code" label="Code" wire:model.live="newEntity.code" errorKey="newEntity.code" placeholder="Optional" />
            </div>
            <x-nx-input-select name="parent_entity_id" label="Übergeordnete Einheit" wire:model.live="newEntity.parent_entity_id" errorKey="newEntity.parent_entity_id"
                :options="$parentOptions" nullable nullLabel="Keine" />
            <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="newEntity.description" rows="3" placeholder="Optional" />
            <div class="border-t border-[color:var(--nx-line)] pt-3">
                <x-nx-input-checkbox label="Aktiv" wire:model.live="newEntity.is_active" />
            </div>
        </form>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="closeCreateModal">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="createEntity">Anlegen</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
