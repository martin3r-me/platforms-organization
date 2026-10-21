@php
    $clusterOptions = collect($this->clusters)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values()->all();
    $categoryOptions = collect($this->categories)->map(fn ($c) => ['value' => $c, 'label' => ucfirst($c)])->values()->all();
    $intervall = fn ($h) => $h <= 24 ? "{$h} h" : ($h <= 168 ? round($h / 24) . ' T' : round($h / 168) . ' W');
    $sources = $this->sources;
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Umwelt-Quellen" icon="heroicon-o-globe-alt" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-64" :defaultOpen="true" side="left">
            <div class="flex flex-col gap-3 p-3">
                <x-nx-input-select name="clusterFilter" label="Cluster" size="sm" wire:model.live="clusterFilter"
                    :options="$clusterOptions" nullable nullLabel="Alle Cluster" />
                <x-nx-input-select name="categoryFilter" label="Kategorie" size="sm" wire:model.live="categoryFilter"
                    :options="$categoryOptions" nullable nullLabel="Alle Kategorien" />
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Umwelt-Quellen'],
        ]">
            <x-slot name="left">
                <div class="flex items-center gap-3">
                    <div class="w-56">
                        <x-nx-input-text name="search" size="sm" placeholder="Name oder URL…" wire:model.live.debounce.400ms="search" />
                    </div>
                    <x-nx-input-checkbox label="Inaktive" wire:model.live="showInactive" />
                </div>
            </x-slot>
            <x-nx-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Quelle</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <x-nx-card flush>
            @if ($sources->isEmpty())
                <x-nx-empty icon="heroicon-o-globe-alt">
                    Keine Quellen vorhanden. Lege eine Quelle an, um Umwelt-Daten zu erfassen.
                    <x-slot name="action">
                        <x-nx-button size="sm" wire:click="create">Quelle anlegen</x-nx-button>
                    </x-slot>
                </x-nx-empty>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-[color:var(--nx-muted)]">
                                <th class="border-b border-[color:var(--nx-line)] px-4 py-2.5 text-left font-medium">Quelle</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-left font-medium">Einordnung</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Intervall</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Letzter Abruf</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Snapshots</th>
                                <th class="border-b border-[color:var(--nx-line)] px-3 py-2.5 text-right font-medium">Status</th>
                                <th class="w-10 border-b border-[color:var(--nx-line)] px-2 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sources as $source)
                                @php
                                    $learned = ($this->sourceRelevanceMemories[$source->id] ?? null)?->structured_data ?? [];
                                    $url = $source->config['url'] ?? null;
                                @endphp
                                <tr wire:key="src-{{ $source->id }}" class="cursor-pointer border-b border-[color:var(--nx-line)] last:border-0 hover:bg-[color:var(--nx-hover)]"
                                    wire:click="edit({{ $source->id }})">
                                    <td class="max-w-sm px-4 py-2">
                                        <div class="font-medium text-[color:var(--nx-text)]">{{ $source->name }}</div>
                                        @if ($url)
                                            <div class="truncate text-xs text-[color:var(--nx-faint)]" title="{{ $url }}">{{ $url }}</div>
                                        @endif
                                        @if (! empty($learned['topics_useful']) || ! empty($learned['topics_noise']))
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach (array_slice($learned['topics_useful'] ?? [], 0, 3) as $t)
                                                    <x-nx-badge variant="success">{{ $t }}</x-nx-badge>
                                                @endforeach
                                                @foreach (array_slice($learned['topics_noise'] ?? [], 0, 3) as $t)
                                                    <x-nx-badge variant="danger">{{ $t }}</x-nx-badge>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="text-[color:var(--nx-text)]">{{ $this->clusters[$source->cluster] ?? $source->cluster ?? '–' }}</div>
                                        <div class="text-xs text-[color:var(--nx-faint)]">{{ ucfirst($source->category) }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-muted)]">{{ $intervall($source->pull_interval_hours) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right text-[color:var(--nx-muted)]">
                                        @if ($source->last_pulled_at)
                                            <span title="{{ $source->last_pulled_at->format('d.m.Y H:i') }}">{{ $source->last_pulled_at->diffForHumans() }}</span>
                                        @else
                                            <span class="text-[color:var(--nx-faint)]">–</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-[color:var(--nx-text)]">{{ $source->snapshots_count }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-right">
                                        <x-nx-badge :variant="$source->is_active ? 'success' : 'neutral'" dot>{{ $source->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                                    </td>
                                    <td class="px-2 py-2 text-right" x-on:click.stop>
                                        <x-nx-dropdown>
                                            <x-nx-dropdown-item wire:click="edit({{ $source->id }})">
                                                @svg('heroicon-o-pencil-square', 'w-4 h-4') Bearbeiten
                                            </x-nx-dropdown-item>
                                            <x-nx-dropdown-item wire:click="pullNow({{ $source->id }})" wire:confirm="Quelle „{{ $source->name }}“ jetzt abrufen?">
                                                @svg('heroicon-o-arrow-down-tray', 'w-4 h-4') Jetzt abrufen
                                            </x-nx-dropdown-item>
                                            <x-nx-dropdown-divider />
                                            <x-nx-dropdown-item wire:click="toggleActive({{ $source->id }})">
                                                @svg($source->is_active ? 'heroicon-o-pause' : 'heroicon-o-play', 'w-4 h-4')
                                                {{ $source->is_active ? 'Deaktivieren' : 'Aktivieren' }}
                                            </x-nx-dropdown-item>
                                        </x-nx-dropdown>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-nx-card>
    </x-ui-page-container>

    <x-nx-modal size="md" wire:model="modalShow">
        <x-slot name="header">{{ $editingId ? 'Quelle bearbeiten' : 'Neue Quelle' }}</x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required placeholder="z. B. t3n Digital" />
            <div class="grid grid-cols-2 gap-3">
                <x-nx-input-select name="cluster" label="Cluster" wire:model.live="form.cluster" errorKey="form.cluster" :options="$clusterOptions" />
                <x-nx-input-select name="category" label="Kategorie" wire:model.live="form.category" errorKey="form.category" :options="$categoryOptions" />
            </div>
            <x-nx-input-text name="url" label="Feed-URL" wire:model.live="form.url" errorKey="form.url" required placeholder="https://example.com/feed.xml" />
            <x-nx-input-number name="pull_interval_hours" label="Abruf-Intervall (Stunden)" wire:model.live="form.pull_interval_hours" errorKey="form.pull_interval_hours" min="1" max="720" />
            <x-nx-input-textarea name="extraction_prompt" label="Extraktions-Prompt" hint="optional" wire:model.live="form.extraction_prompt" errorKey="form.extraction_prompt" rows="3" placeholder="Zusätzlicher Kontext für die LLM-Extraktion…" />
            <div class="border-t border-[color:var(--nx-line)] pt-3">
                <x-nx-input-checkbox label="Aktiv" wire:model.live="form.is_active" />
            </div>
        </form>

        <x-slot name="footer">
            <div class="flex justify-end gap-2">
                <x-nx-button variant="ghost" wire:click="$set('modalShow', false)">Abbrechen</x-nx-button>
                <x-nx-button variant="primary" wire:click="store">{{ $editingId ? 'Speichern' : 'Anlegen' }}</x-nx-button>
            </div>
        </x-slot>
    </x-nx-modal>
</x-ui-page>
