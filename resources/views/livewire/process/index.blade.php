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
                <select wire:model.live="statusFilter" class="text-xs rounded-md border-gray-300 shadow-sm py-1 pl-2 pr-7">
                    <option value="">Alle Status</option>
                    <option value="draft">Entwurf</option>
                    <option value="active">Aktiv</option>
                    <option value="deprecated">Veraltet</option>
                </select>
                <select wire:model.live="categoryFilter" class="text-xs rounded-md border-gray-300 shadow-sm py-1 pl-2 pr-7">
                    <option value="">Alle Kategorien</option>
                    @foreach(\Platform\Organization\Enums\ProcessCategory::cases() as $cat)
                        <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                    @endforeach
                </select>
                <button wire:click="$toggle('focusFilter')" class="inline-flex items-center gap-1 text-xs px-2 py-1 rounded-md border transition-colors {{ $focusFilter ? 'bg-yellow-100 border-yellow-400 text-yellow-800' : 'border-gray-300 text-gray-600 hover:bg-gray-50' }}">
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

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select wire:model.live="form.status" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="draft">Entwurf</option>
                        <option value="active">Aktiv</option>
                        <option value="deprecated">Veraltet</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategorie</label>
                    <select wire:model.live="form.process_category" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">– Keine Kategorie –</option>
                        @foreach(\Platform\Organization\Enums\ProcessCategory::cases() as $cat)
                            <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                        @endforeach
                    </select>
                </div>
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
