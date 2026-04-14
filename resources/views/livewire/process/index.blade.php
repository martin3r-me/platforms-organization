<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Prozesse'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neuer Prozess</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Name, Code, Beschreibung..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Alle</option>
                        <option value="draft">Entwurf</option>
                        <option value="active">Aktiv</option>
                        <option value="deprecated">Veraltet</option>
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
        @php $tree = $this->processTree; @endphp

        @if(count($tree) === 0 && $this->processes->isEmpty())
            <div class="text-center text-[var(--ui-muted)] py-12">Keine Prozesse gefunden.</div>
        @else
            <div class="space-y-1">
                @each('organization::livewire.process.partials.tree-node', $tree, 'node')
            </div>
        @endif
    </x-ui-page-container>

    <!-- Create/Edit Modal -->
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">
            {{ $editingId ? 'Prozess bearbeiten' : 'Neuen Prozess erstellen' }}
        </x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="z.B. Onboarding neuer Mitarbeiter" />

            <x-ui-input-text name="code" label="Code (optional)" wire:model.live="form.code" placeholder="z.B. PROC-001" />

            <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="3" />

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select wire:model.live="form.status" class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="draft">Entwurf</option>
                    <option value="active">Aktiv</option>
                    <option value="deprecated">Veraltet</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Owner (Entity)</label>
                    <select wire:model.live="form.owner_entity_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">– Kein Owner –</option>
                        @foreach($this->availableEntities as $entity)
                            <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">VSM System</label>
                    <select wire:model.live="form.vsm_system_id" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">– Kein VSM System –</option>
                        @foreach($this->availableVsmSystems as $vsm)
                            <option value="{{ $vsm->id }}">{{ $vsm->name }}</option>
                        @endforeach
                    </select>
                </div>
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
