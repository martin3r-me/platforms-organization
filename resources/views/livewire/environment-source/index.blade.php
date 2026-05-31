<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Umwelt'],
            ['label' => 'Quellen'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Quelle</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Suche Quellen..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Filter</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Cluster</label>
                            <select
                                name="clusterFilter"
                                wire:model.live="clusterFilter"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="">Alle Cluster</option>
                                @foreach($this->clusters as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Kategorie</label>
                            <select
                                name="categoryFilter"
                                wire:model.live="categoryFilter"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm"
                            >
                                <option value="">Alle Kategorien</option>
                                @foreach($this->categories as $cat)
                                    <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="showInactive" id="showInactive" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="showInactive" class="ml-2 text-sm text-[var(--ui-secondary)]">Inaktive anzeigen</label>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitaeten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitaeten verfuegbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Cluster</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Kategorie</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">URL</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Intervall</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Letzter Pull</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Snapshots</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Aktionen</x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->sources as $source)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <span class="font-medium">{{ $source->name }}</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="muted">{{ $this->clusters[$source->cluster] ?? $source->cluster ?? '-' }}</x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="info">{{ ucfirst($source->category) }}</x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-xs text-[var(--ui-muted)] truncate max-w-[200px] inline-block" title="{{ $source->config['url'] ?? '' }}">
                                {{ $source->config['url'] ?? '-' }}
                            </span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @php
                                $h = $source->pull_interval_hours;
                                $label = $h <= 24 ? "{$h}h" : ($h <= 168 ? round($h/24) . 'd' : round($h/168) . 'w');
                            @endphp
                            {{ $label }}
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            @if($source->last_pulled_at)
                                <span class="text-xs" title="{{ $source->last_pulled_at->format('d.m.Y H:i') }}">
                                    {{ $source->last_pulled_at->diffForHumans() }}
                                </span>
                            @else
                                <span class="text-[var(--ui-muted)]">-</span>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            {{ $source->snapshots_count }}
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $source->is_active ? 'success' : 'muted' }}">
                                {{ $source->is_active ? 'Aktiv' : 'Inaktiv' }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex items-center gap-2">
                                <button wire:click="edit({{ $source->id }})" class="text-[var(--ui-primary)] hover:underline text-sm">
                                    Bearbeiten
                                </button>
                                <button wire:click="toggleActive({{ $source->id }})" class="text-[var(--ui-muted)] hover:underline text-sm">
                                    {{ $source->is_active ? 'Deaktivieren' : 'Aktivieren' }}
                                </button>
                                <button wire:click="pullNow({{ $source->id }})" wire:confirm="Jetzt manuell pullen?" class="text-[var(--ui-primary)] hover:underline text-sm">
                                    Pull
                                </button>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="9">
                            <div class="text-center text-[var(--ui-muted)] py-8">
                                Keine Quellen vorhanden. Erstelle eine neue Quelle um Umwelt-Daten zu erfassen.
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <!-- Create/Edit Source Modal -->
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">
            {{ $editingId ? 'Quelle bearbeiten' : 'Neue Quelle erstellen' }}
        </x-slot>

        <div class="space-y-4">
            <form wire:submit.prevent="store" class="space-y-4">
                <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="z.B. t3n Digital" />

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Cluster</label>
                        <select name="cluster" wire:model.live="form.cluster" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                            @foreach($this->clusters as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Kategorie</label>
                        <select name="category" wire:model.live="form.category" class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50">
                            @foreach($this->categories as $cat)
                                <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <x-ui-input-text name="url" label="Feed-URL" wire:model.live="form.url" required placeholder="https://example.com/feed.xml" />

                <x-ui-input-number name="pull_interval_hours" label="Pull-Intervall (Stunden)" wire:model.live="form.pull_interval_hours" min="1" max="720" />

                <x-ui-input-textarea name="extraction_prompt" label="Extraktions-Prompt (optional)" wire:model.live="form.extraction_prompt" placeholder="Zusaetzlicher Kontext fuer die LLM-Extraktion..." rows="3" />

                <div class="flex items-center">
                    <input type="checkbox" wire:model.live="form.is_active" id="form_is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                    <label for="form_is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                </div>
            </form>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('modalShow', false)">Abbrechen</x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="store">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
