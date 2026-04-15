<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Prozesse'],
        ]">
            <x-slot name="left">
                <x-ui-input-select
                    wire:key="filter-status"
                    name="statusFilter"
                    :options="['draft' => 'Entwurf', 'active' => 'Aktiv', 'deprecated' => 'Veraltet']"
                    wire:model.live="statusFilter"
                    :nullable="true"
                    nullLabel="Alle Status"
                    size="xs"
                />
                <x-ui-input-select
                    wire:key="filter-category"
                    name="categoryFilter"
                    :options="\Platform\Organization\Enums\ProcessCategory::cases()"
                    optionValue="value"
                    optionLabel="label"
                    wire:model.live="categoryFilter"
                    :nullable="true"
                    nullLabel="Alle Kategorien"
                    size="xs"
                />
                <x-ui-input-select
                    wire:key="filter-vsm"
                    name="vsmFilter"
                    :options="$this->availableVsmSystems"
                    optionValue="id"
                    optionLabel="name"
                    wire:model.live="vsmFilter"
                    :nullable="true"
                    nullLabel="Alle VSM Systeme"
                    size="xs"
                />
                <button wire:key="filter-focus" wire:click="$toggle('focusFilter')" class="inline-flex items-center gap-1 text-xs px-3 py-1 rounded-lg border transition-all duration-200 {{ $focusFilter ? 'bg-[rgb(var(--ui-warning-rgb))] text-[color:var(--ui-on-warning)] border-2 border-[rgb(var(--ui-warning-rgb))] shadow-sm font-semibold ring-2 ring-[rgb(var(--ui-warning-rgb))] ring-opacity-20' : 'bg-white/50 backdrop-blur-sm text-[color:var(--ui-secondary)] border border-white/40 hover:bg-[rgba(var(--ui-warning-rgb),0.05)] hover:border-[rgb(var(--ui-warning-rgb))]' }}">
                    @svg('heroicon-o-star', 'w-3.5 h-3.5')
                    Fokus
                </button>
            </x-slot>

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
            <div wire:key="empty-state" class="text-center text-[var(--ui-muted)] py-12">Keine Prozesse gefunden.</div>
        @else
            <div wire:key="process-tree" class="space-y-1">
                @foreach($tree as $node)
                    @include('organization::livewire.process.partials.tree-node', ['node' => $node])
                @endforeach
            </div>
        @endif
    </x-ui-page-container>

    <!-- Create/Edit Modal -->
    <x-ui-modal wire:model="modalShow" size="lg" wire:key="process-modal-{{ $editingId ?? 'create' }}">
        <x-slot name="header">
            {{ $editingId ? 'Prozess bearbeiten' : 'Neuen Prozess erstellen' }}
        </x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="z.B. Onboarding neuer Mitarbeiter" />

            <x-ui-input-text name="code" label="Code (optional)" wire:model.live="form.code" placeholder="z.B. PROC-001" />

            <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="3" />

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-select
                    name="form.status"
                    label="Status"
                    :options="['draft' => 'Entwurf', 'active' => 'Aktiv', 'deprecated' => 'Veraltet']"
                    wire:model.live="form.status"
                    :required="true"
                    size="sm"
                />
                <x-ui-input-select
                    name="form.process_category"
                    label="Kategorie"
                    :options="\Platform\Organization\Enums\ProcessCategory::cases()"
                    optionValue="value"
                    optionLabel="label"
                    wire:model.live="form.process_category"
                    :nullable="true"
                    nullLabel="– Keine Kategorie –"
                    size="sm"
                />
            </div>

            <div class="space-y-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" wire:model.live="form.is_focus" class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-500">
                    <span class="text-sm font-medium text-gray-700">Fokus-Prozess</span>
                </label>
                @if($form['is_focus'])
                    <x-ui-input-textarea name="focus_reason" label="Fokus-Begründung" wire:model.live="form.focus_reason" rows="2" placeholder="Warum ist dieser Prozess im Fokus?" />
                    <x-ui-input-text type="date" name="focus_until" label="Fokus bis" wire:model.live="form.focus_until" />
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-select
                    name="form.owner_entity_id"
                    label="Owner (Entity)"
                    :options="$this->availableEntities"
                    optionValue="id"
                    optionLabel="name"
                    wire:model.live="form.owner_entity_id"
                    :nullable="true"
                    nullLabel="– Kein Owner –"
                    size="sm"
                />
                <x-ui-input-select
                    name="form.vsm_system_id"
                    label="VSM System"
                    :options="$this->availableVsmSystems"
                    optionValue="id"
                    optionLabel="name"
                    wire:model.live="form.vsm_system_id"
                    :nullable="true"
                    nullLabel="– Kein VSM System –"
                    size="sm"
                />
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
