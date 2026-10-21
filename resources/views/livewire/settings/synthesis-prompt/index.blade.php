@php
    $reportTypeOptions = [
        'weekly' => 'Wöchentlich',
        'monthly' => 'Monatlich',
        'quarterly' => 'Quartalsweise',
    ];
@endphp
<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Synthese-Prompts" icon="heroicon-o-chat-bubble-left-right" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'synthesis-prompts'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Synthese-Prompts'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Suchen…" wire:model.live.debounce.300ms="search" />
                    </div>
                    <div class="w-44">
                        <x-nx-input-select
                            name="reportTypeFilter"
                            size="sm"
                            wire:model.live="reportTypeFilter"
                            :options="$reportTypeOptions"
                            nullable
                            nullLabel="Alle Report-Typen"
                        />
                    </div>
                    <x-nx-input-checkbox label="Inaktive" wire:model.live="showInactive" />
                </div>
            </x-slot>
            <x-nx-button variant="primary" size="sm" wire:click="createNew">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Synthese-Prompt</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            <x-nx-callout variant="neutral" icon="heroicon-o-information-circle">
                Synthese-Prompts steuern, wie Wochen-/Monats-Berichte erzeugt werden. Pro Report-Typ wird der erste aktive Prompt verwendet.
                Ohne aktiven Prompt nutzt das System einen fest hinterlegten Default.
            </x-nx-callout>

            <x-nx-card flush>
                @if ($this->definitions->isEmpty())
                    <x-nx-empty icon="heroicon-o-chat-bubble-left-right">
                        Noch keine Synthese-Prompts angelegt. Das System nutzt aktuell den fest hinterlegten Default-Prompt.
                        <x-slot name="action">
                            <x-nx-button size="sm" wire:click="createNew">Synthese-Prompt anlegen</x-nx-button>
                        </x-slot>
                    </x-nx-empty>
                @else
                    <x-nx-table>
                        <x-nx-table-header>
                            <x-nx-table-header-cell>Name</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Report-Typ</x-nx-table-header-cell>
                            <x-nx-table-header-cell>Model</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right">Max. Signale</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right">Status</x-nx-table-header-cell>
                            <x-nx-table-header-cell align="right"><span class="sr-only">Aktionen</span></x-nx-table-header-cell>
                        </x-nx-table-header>
                        <x-nx-table-body>
                            @foreach ($this->definitions as $def)
                                <x-nx-table-row wire:key="sp-{{ $def->id }}" clickable :href="route('organization.settings.synthesis-prompts.show', $def)">
                                    <x-nx-table-cell>
                                        <div class="font-medium text-[color:var(--nx-text)]">{{ $def->name }}</div>
                                        @if ($def->description)
                                            <div class="text-xs text-[color:var(--nx-faint)]">{{ \Illuminate\Support\Str::limit($def->description, 80) }}</div>
                                        @endif
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>
                                        <x-nx-badge>{{ $reportTypeOptions[$def->report_type] ?? $def->report_type }}</x-nx-badge>
                                    </x-nx-table-cell>
                                    <x-nx-table-cell>
                                        <span class="font-mono text-xs text-[color:var(--nx-muted)]">{{ $def->model ?: 'default' }}</span>
                                    </x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        <span class="text-sm tabular-nums text-[color:var(--nx-text)]">{{ $def->max_signals }}</span>
                                    </x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        <button type="button" wire:click="toggleActive({{ $def->id }})" onclick="event.stopPropagation()" class="inline-flex" title="Status umschalten">
                                            <x-nx-badge :variant="$def->is_active ? 'success' : 'neutral'" dot>
                                                {{ $def->is_active ? 'Aktiv' : 'Inaktiv' }}
                                            </x-nx-badge>
                                        </button>
                                    </x-nx-table-cell>
                                    <x-nx-table-cell align="right">
                                        <x-nx-button
                                            variant="ghost"
                                            size="sm"
                                            icon
                                            title="Löschen"
                                            onclick="event.stopPropagation()"
                                            wire:click="delete({{ $def->id }})"
                                            wire:confirm="Diesen Synthese-Prompt wirklich löschen?"
                                        >
                                            @svg('heroicon-o-trash', 'w-4 h-4 text-[color:var(--nx-danger)]')
                                        </x-nx-button>
                                    </x-nx-table-cell>
                                </x-nx-table-row>
                            @endforeach
                        </x-nx-table-body>
                    </x-nx-table>
                @endif
            </x-nx-card>
        </div>
    </x-ui-page-container>
</x-ui-page>
