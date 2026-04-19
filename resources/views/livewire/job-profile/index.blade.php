<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'JobProfiles'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="create">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neues JobProfile</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Filter" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Suche</h3>
                    <x-ui-input-text name="search" wire:model.live="search" placeholder="Name, Beschreibung, Inhalt..." class="w-full" size="sm" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <select wire:model.live="statusFilter" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="active">Aktiv</option>
                        <option value="draft">Entwurf</option>
                        <option value="archived">Archiviert</option>
                        <option value="">Alle</option>
                    </select>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Level</h3>
                    <select wire:model.live="levelFilter" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Alle</option>
                        <option value="junior">Junior</option>
                        <option value="mid">Mid</option>
                        <option value="senior">Senior</option>
                        <option value="lead">Lead</option>
                        <option value="principal">Principal</option>
                    </select>
                </div>
                @if(count($this->jobFamilies) > 0)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Job Family</h3>
                        <select wire:model.live="jobFamilyFilter" class="w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Alle</option>
                            @foreach($this->jobFamilies as $family)
                                <option value="{{ $family }}">{{ $family }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        @php $tree = $this->itemTree; @endphp

        @if(count($tree) === 0 && $this->jobProfiles->isEmpty())
            <div class="text-center text-[var(--ui-muted)] py-12">Keine JobProfiles gefunden.</div>
        @else
            <div class="space-y-1">
                @foreach($tree as $node)
                    @include('organization::livewire.partials.entity-tree-node', ['node' => $node, 'itemPartial' => 'organization::livewire.job-profile.partials.tree-item'])
                @endforeach
            </div>
        @endif
    </x-ui-page-container>

    <!-- Create/Edit Modal -->
    <x-ui-modal wire:model="modalShow" size="lg">
        <x-slot name="header">
            {{ $editingId ? 'JobProfile bearbeiten' : 'Neues JobProfile erstellen' }}
        </x-slot>

        <form wire:submit.prevent="store" class="space-y-4">
            <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required placeholder="z.B. Senior Backend Engineer" />

            <x-ui-input-textarea name="description" label="Kurzbeschreibung" wire:model.live="form.description" rows="2" />

            <x-ui-input-textarea name="content" label="Ausführliches Profil (Markdown)" wire:model.live="form.content" rows="6" />

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                    <select wire:model.live="form.level" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">—</option>
                        <option value="junior">Junior</option>
                        <option value="mid">Mid</option>
                        <option value="senior">Senior</option>
                        <option value="lead">Lead</option>
                        <option value="principal">Principal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select wire:model.live="form.status" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="active">Aktiv</option>
                        <option value="draft">Entwurf</option>
                        <option value="archived">Archiviert</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Owner (Entity)</label>
                <select wire:model.live="form.owner_entity_id" class="w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">– Kein Owner –</option>
                    @foreach($this->availableEntities as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                    @endforeach
                </select>
            </div>

            <x-ui-input-text name="skills" label="Skills (komma-getrennt)" wire:model.live="form.skills" placeholder="PHP, Laravel, PostgreSQL" />
            <x-ui-input-text name="responsibilities" label="Verantwortlichkeiten (komma-getrennt)" wire:model.live="form.responsibilities" placeholder="Code Reviews, Architektur, Mentoring" />

            <div class="grid grid-cols-2 gap-4">
                <x-ui-input-text type="date" name="effective_from" label="Gültig ab" wire:model.live="form.effective_from" />
                <x-ui-input-text type="date" name="effective_to" label="Gültig bis" wire:model.live="form.effective_to" />
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
