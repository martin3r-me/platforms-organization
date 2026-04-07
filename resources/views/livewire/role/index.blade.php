<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Rollen'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Rolle</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Name, Slug, Beschreibung..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="active">Aktiv</option>
                        <option value="archived">Archiviert</option>
                        <option value="">Alle</option>
                    </select>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <x-ui-table compact="true">
            <x-ui-table-header>
                <x-ui-table-header-cell compact="true">Name</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Slug</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Status</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true">Zuweisungen</x-ui-table-header-cell>
                <x-ui-table-header-cell compact="true"></x-ui-table-header-cell>
            </x-ui-table-header>

            <x-ui-table-body>
                @forelse($this->roles as $role)
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true">
                            <div class="font-medium">{{ $role->name }}</div>
                            @if($role->description)
                                <div class="text-xs text-[var(--ui-muted)]">{{ \Illuminate\Support\Str::limit($role->description, 80) }}</div>
                            @endif
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <code class="text-xs text-[var(--ui-muted)]">{{ $role->slug }}</code>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <x-ui-badge variant="{{ $role->status === 'active' ? 'success' : 'muted' }}">
                                {{ ucfirst($role->status) }}
                            </x-ui-badge>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <span class="text-sm">{{ $role->assignments_count }} Zuweisung(en)</span>
                        </x-ui-table-cell>
                        <x-ui-table-cell compact="true">
                            <div class="flex gap-1 justify-end">
                                <x-ui-button size="xs" variant="secondary-outline" wire:click="edit({{ $role->id }})">
                                    @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                </x-ui-button>
                                @if($role->status === 'archived')
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="unarchive({{ $role->id }})" title="Reaktivieren">
                                        @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                                    </x-ui-button>
                                @else
                                    <x-ui-button size="xs" variant="secondary-outline" wire:click="archive({{ $role->id }})" title="Archivieren">
                                        @svg('heroicon-o-archive-box', 'w-4 h-4')
                                    </x-ui-button>
                                @endif
                                <x-ui-button size="xs" variant="danger-outline" wire:click="delete({{ $role->id }})" wire:confirm="Rolle wirklich löschen?">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </x-ui-button>
                            </div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @empty
                    <x-ui-table-row compact="true">
                        <x-ui-table-cell compact="true" colspan="5">
                            <div class="text-center text-[var(--ui-muted)] py-6">Keine Rollen gefunden.</div>
                        </x-ui-table-cell>
                    </x-ui-table-row>
                @endforelse
            </x-ui-table-body>
        </x-ui-table>
    </x-ui-page-container>

    <!-- Create/Edit Modal -->
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">
            {{ $editingId ? 'Rolle bearbeiten' : 'Neue Rolle erstellen' }}
        </x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="z.B. Projektleiter" />

            <x-ui-input-text name="slug" label="Slug (optional, wird automatisch erzeugt)" wire:model.live="form.slug" placeholder="project-lead" />

            <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="3" />

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select wire:model.live="form.status" class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="active">Aktiv</option>
                    <option value="archived">Archiviert</option>
                </select>
            </div>
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="$set('modalShow', false)">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="store">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    {{ $editingId ? 'Speichern' : 'Erstellen' }}
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
