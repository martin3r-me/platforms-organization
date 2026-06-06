<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Einstellungen'],
            ['label' => 'Synthesis-Prompts'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="createNew">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Prompt</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <label class="flex items-center gap-2 text-sm text-[var(--ui-secondary)] cursor-pointer">
                            <input type="checkbox" wire:model.live="showInactive" class="rounded border-gray-300 text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]">
                            Inaktive anzeigen
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-1">Report-Typ</label>
                            <select wire:model.live="reportTypeFilter" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Alle</option>
                                <option value="weekly">Wöchentlich</option>
                                <option value="monthly">Monatlich</option>
                                <option value="quarterly">Quartalsweise</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <div class="mb-4 p-4 rounded-lg border border-blue-200 bg-blue-50/40 text-sm text-[var(--ui-secondary)]">
            @svg('heroicon-o-information-circle', 'w-4 h-4 inline-block -mt-0.5 mr-1 text-blue-600')
            Synthesis-Prompts steuern wie Wochen-/Monats-Berichte erzeugt werden. Pro Report-Typ wird der erste aktive Prompt verwendet. Ohne aktiven Prompt nutzt das System einen hardcoded Default.
        </div>

        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Report-Typ</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Model</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" class="text-center">Max. Signale</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true" class="text-right">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->definitions as $def)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <a href="{{ route('organization.settings.synthesis-prompts.show', $def) }}" class="link font-medium">
                                {{ $def->name }}
                            </a>
                            @if($def->description)
                                <p class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($def->description, 80) }}</p>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="info">
                                @switch($def->report_type)
                                    @case('weekly') Wöchentlich @break
                                    @case('monthly') Monatlich @break
                                    @case('quarterly') Quartalsweise @break
                                @endswitch
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-xs text-[var(--ui-muted)] font-mono">{{ $def->model ?: 'default' }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true" class="text-center tabular-nums">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $def->max_signals }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <button wire:click="toggleActive({{ $def->id }})" type="button" class="inline-flex">
                                <x-ui-badge :variant="$def->is_active ? 'success' : 'muted'">
                                    {{ $def->is_active ? 'Aktiv' : 'Inaktiv' }}
                                </x-ui-badge>
                            </button>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true" class="text-right">
                            <a href="{{ route('organization.settings.synthesis-prompts.show', $def) }}" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] inline-flex items-center gap-1 mr-3">
                                @svg('heroicon-o-pencil-square', 'w-3.5 h-3.5')
                                Bearbeiten
                            </a>
                            <button
                                wire:click="delete({{ $def->id }})"
                                wire:confirm="Diesen Synthesis-Prompt wirklich löschen?"
                                type="button"
                                class="text-xs text-[var(--ui-muted)] hover:text-red-600 inline-flex items-center gap-1"
                            >
                                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                Löschen
                            </button>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="6">
                            <div class="text-center py-8">
                                @svg('heroicon-o-document-text', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                <p class="text-sm text-[var(--ui-muted)] mb-2">Noch keine Synthesis-Prompts angelegt.</p>
                                <p class="text-xs text-[var(--ui-muted)]">System nutzt aktuell den hardcoded Default-Prompt.</p>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>
</x-ui-page>
