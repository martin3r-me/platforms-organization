<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$relationType->name" icon="heroicon-o-link" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Einstellungen" width="w-64" :defaultOpen="true" side="left">
            @include('organization::livewire.settings.partials.nav', ['aktiv' => 'relation-types'])
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Beziehungstypen', 'href' => route('organization.settings.relation-types.index')],
            ['label' => $relationType->name],
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
                    <x-nx-dropdown-item variant="danger" wire:click="delete" wire:confirm="Beziehungstyp „{{ $relationType->name }}“ wirklich löschen?">
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        Löschen
                    </x-nx-dropdown-item>
                </x-nx-dropdown>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            {{-- Kopf: Identität + Zustand --}}
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $relationType->name }}</h1>
                <span class="font-mono text-xs text-[color:var(--nx-faint)]">{{ $relationType->code }}</span>
                <x-nx-badge :variant="$relationType->is_active ? 'success' : 'neutral'" dot>{{ $relationType->is_active ? 'Aktiv' : 'Inaktiv' }}</x-nx-badge>
                <span class="ml-auto text-xs text-[color:var(--nx-faint)]">angelegt {{ $relationType->created_at->format('d.m.Y') }}</span>
            </div>

            <x-nx-panel title="Grunddaten">
                <div class="space-y-3 p-4">
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required />
                        <x-nx-input-text name="code" label="Code" wire:model.live="form.code" errorKey="form.code" required />
                    </div>
                    <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="3" />
                    <div class="grid grid-cols-2 gap-3">
                        <x-nx-input-text name="icon" label="Icon" wire:model.live="form.icon" placeholder="heroicon-o-arrow-right" />
                        <x-nx-input-number name="sort_order" label="Sortierung" wire:model.live="form.sort_order" min="0" />
                    </div>
                </div>
            </x-nx-panel>

            <x-nx-panel title="Eigenschaften">
                <div class="space-y-2.5 p-4">
                    <x-nx-input-checkbox wire:model.live="form.is_directional">
                        <span>Gerichtet <span class="text-xs text-[color:var(--nx-muted)]">— die Beziehung hat eine Richtung (von → nach)</span></span>
                    </x-nx-input-checkbox>
                    <x-nx-input-checkbox wire:model.live="form.is_hierarchical">
                        <span>Hierarchisch <span class="text-xs text-[color:var(--nx-muted)]">— bildet Über-/Unterordnung</span></span>
                    </x-nx-input-checkbox>
                    <x-nx-input-checkbox wire:model.live="form.is_reciprocal">
                        <span>Wechselseitig <span class="text-xs text-[color:var(--nx-muted)]">— gilt in beide Richtungen gleichermaßen</span></span>
                    </x-nx-input-checkbox>
                    <div class="border-t border-[color:var(--nx-line)] pt-2.5">
                        <x-nx-input-checkbox label="Aktiv" wire:model.live="form.is_active" />
                    </div>
                </div>
            </x-nx-panel>

            <x-nx-panel title="Berechtigungen" subtitle="Was eine Person über diese Beziehung auf die Einheit und ihren Teilbaum erhält">
                <div class="space-y-3 p-4">
                    <x-nx-callout variant="neutral" icon="heroicon-o-information-circle">
                        Wirkt nur bei <strong>Person → Einheit</strong>. Rein strukturelle Beziehungen zwischen Einheiten verleihen nichts.
                        Ohne Auswahl entsteht kein Zugriff; die höchste Stufe gewinnt (Verwalten &gt; Bearbeiten &gt; Lesen).
                    </x-nx-callout>
                    <div class="space-y-2.5">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[color:var(--nx-text)]">
                            <input type="checkbox" value="read" wire:model.live="form.capabilities" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]" />
                            <span>Lesen <code class="text-xs text-[color:var(--nx-faint)]">read</code> <span class="text-xs text-[color:var(--nx-muted)]">— Objekte im Teilbaum sehen</span></span>
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[color:var(--nx-text)]">
                            <input type="checkbox" value="write" wire:model.live="form.capabilities" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]" />
                            <span>Bearbeiten <code class="text-xs text-[color:var(--nx-faint)]">write</code> <span class="text-xs text-[color:var(--nx-muted)]">— sehen und ändern</span></span>
                        </label>
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[color:var(--nx-text)]">
                            <input type="checkbox" value="manage" wire:model.live="form.capabilities" class="h-4 w-4 rounded-[4px] accent-[var(--nx-accent)]" />
                            <span>Verwalten <code class="text-xs text-[color:var(--nx-faint)]">manage</code> <span class="text-xs text-[color:var(--nx-muted)]">— voller Zugriff inkl. Löschen</span></span>
                        </label>
                    </div>
                </div>
            </x-nx-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
