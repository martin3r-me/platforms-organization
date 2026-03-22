<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Einheiten', 'href' => route('organization.entities.index')],
            ['label' => $entity->name ?? 'Details'],
        ]">
            @if($this->isDirty())
                <x-ui-button variant="secondary-ghost" size="sm" wire:click="loadForm">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </x-ui-button>
            @else
                <x-ui-button variant="ghost" size="sm" wire:click="edit">
                    @svg('heroicon-o-pencil', 'w-4 h-4')
                    <span>Bearbeiten</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="openCreateTeamModal">
                    @svg('heroicon-o-user-group', 'w-4 h-4')
                    <span>Team erstellen</span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-3">
                        @if($entity->is_active)
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        @if($entity->code)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Code</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->code }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Typ</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->type->name }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">{{ $entity->type->group->name }}</div>
                        </div>
                        @if($entity->vsmSystem)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">VSM System</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->vsmSystem->name }}</div>
                            </div>
                        @endif
                        @if($entity->costCenter)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Kostenstelle</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->costCenter->name }}</div>
                            </div>
                        @endif
                        @if($entity->parent)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Übergeordnet</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->parent->name }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">{{ $entity->parent->type->name }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Aktualisiert</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->updated_at->format('d.m.Y H:i') }}</div>
                        </div>
                    </div>
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
        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                {{ session('error') }}
            </div>
        @endif

        <div class="space-y-6">
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                <div class="space-y-4">
                    <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />
                    <x-ui-input-text name="code" label="Code" wire:model.live="form.code" placeholder="Optional: Code oder Nummer" />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" />
                    <x-ui-input-select
                        name="entity_type_id"
                        label="Typ"
                        :options="$this->entityTypes->flatten()"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="false"
                        wire:model.live="form.entity_type_id"
                        required
                    />
                    <x-ui-input-select
                        name="vsm_system_id"
                        label="VSM System (optional)"
                        :options="$this->vsmSystems"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="Kein VSM System"
                        wire:model.live="form.vsm_system_id"
                    />
                    <x-ui-input-select
                        name="cost_center_id"
                        label="Kostenstelle (optional)"
                        :options="$this->costCenters"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="Keine Kostenstelle"
                        wire:model.live="form.cost_center_id"
                    />
                    <x-ui-input-select
                        name="parent_entity_id"
                        label="Übergeordnete Einheit (optional)"
                        :options="$this->parentEntities"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="Keine übergeordnete Einheit"
                        wire:model.live="form.parent_entity_id"
                    />
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                    </div>
                </div>
            </div>

            <!-- Dimensionen -->
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Dimensionen</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Kostenstellen (interaktiv) -->
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Kostenstellen</h3>
                        <livewire:organization.dimension-linker
                            dimension="cost-centers"
                            :contextType="$entity::class"
                            :contextId="$entity->id"
                            :key="'dim-cost-centers-'.$entity->id"
                        />
                    </div>

                    <!-- VSM Funktionen (read-only, Hierarchie-basiert) -->
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">VSM Funktionen</h3>
                        <div class="space-y-2">
                            @if($this->availableVsmFunctions->count() > 0)
                                @foreach($this->availableVsmFunctions as $vsmFunction)
                                    <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded">
                                        <div>
                                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $vsmFunction->name }}</div>
                                            <div class="text-xs text-[var(--ui-muted)]">{{ $vsmFunction->code }}</div>
                                        </div>
                                        @if($vsmFunction->isGlobal())
                                            <x-ui-badge variant="secondary" size="sm">Global</x-ui-badge>
                                        @else
                                            <x-ui-badge variant="info" size="sm">Entitätsspezifisch</x-ui-badge>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <div class="text-sm text-[var(--ui-muted)] py-2">Keine VSM Funktionen verfügbar</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Verknüpfungen -->
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Verknüpfungen</h2>
                @if($this->entityLinksGrouped->count() > 0)
                    <div class="space-y-4">
                        @foreach($this->entityLinksGrouped as $type => $group)
                            <div>
                                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-2 flex items-center gap-2">
                                    @svg('heroicon-o-' . $group['icon'], 'w-4 h-4 text-[var(--ui-muted)]')
                                    {{ $group['label'] }}
                                    <x-ui-badge variant="secondary" size="xs">{{ $group['items']->count() }}</x-ui-badge>
                                </h3>
                                <div class="space-y-1">
                                    @foreach($group['items'] as $link)
                                        @php $linkable = $link->linkable; @endphp
                                        @if($group['route'] && $linkable)
                                            <a href="{{ route($group['route'], $linkable) }}" class="flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)] hover:bg-[var(--ui-muted-10)] transition-colors">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $linkable->name ?? $linkable->title ?? '—' }}</span>
                                                    @if($linkable->status ?? null)
                                                        <x-ui-badge variant="secondary" size="xs">{{ $linkable->status }}</x-ui-badge>
                                                    @endif
                                                </div>
                                                @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4 text-[var(--ui-muted)]')
                                            </a>
                                        @else
                                            <div class="flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $linkable->name ?? $linkable->title ?? '—' }}</span>
                                                    @if($linkable->status ?? null)
                                                        <x-ui-badge variant="secondary" size="xs">{{ $linkable->status }}</x-ui-badge>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                            @svg('heroicon-o-puzzle-piece', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine externen Verknüpfungen</p>
                        <p class="text-xs text-[var(--ui-muted)]">Diese Einheit ist noch nicht mit Projekten, Tickets oder anderen Elementen verknüpft</p>
                    </div>
                @endif
            </div>

            <!-- Relations -->
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Relations</h2>
                    <x-ui-button 
                        variant="primary-outline" 
                        size="sm"
                        wire:click="$dispatch('open-relations-modal', { entityId: {{ $entity->id }} })"
                    >
                        @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                        Relation hinzufügen
                    </x-ui-button>
                </div>
                <div class="space-y-4">
                    @php
                        $relationsFrom = $entity->relationsFrom->whereNull('deleted_at');
                        $relationsTo = $entity->relationsTo->whereNull('deleted_at');
                    @endphp
                    
                    @if($relationsFrom->count() > 0 || $relationsTo->count() > 0)
                        @if($relationsFrom->count() > 0)
                            <div>
                                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-2 flex items-center gap-2">
                                    @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                    Von dieser Entity ({{ $relationsFrom->count() }})
                                </h3>
                                <div class="space-y-2">
                                    @foreach($relationsFrom as $relation)
                                        <div class="flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->name }}</span>
                                                <span class="text-sm text-[var(--ui-muted)]">{{ $relation->relationType->name }}</span>
                                                <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $relation->toEntity->name }}</span>
                                                <x-ui-badge variant="secondary" size="xs">{{ $relation->toEntity->type->name }}</x-ui-badge>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        
                        @if($relationsTo->count() > 0)
                            <div>
                                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-2 flex items-center gap-2">
                                    @svg('heroicon-o-arrow-left', 'w-4 h-4')
                                    Zu dieser Entity ({{ $relationsTo->count() }})
                                </h3>
                                <div class="space-y-2">
                                    @foreach($relationsTo as $relation)
                                        <div class="flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $relation->fromEntity->name }}</span>
                                                <span class="text-sm text-[var(--ui-muted)]">{{ $relation->relationType->name }}</span>
                                                <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->name }}</span>
                                                <x-ui-badge variant="secondary" size="xs">{{ $relation->fromEntity->type->name }}</x-ui-badge>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                                @svg('heroicon-o-link', 'w-8 h-8 text-[var(--ui-muted)]')
                            </div>
                            <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine Relations vorhanden</p>
                            <p class="text-xs text-[var(--ui-muted)]">Erstellen Sie eine Relation zu einer anderen Entity</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Untergeordnete Einheiten -->
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Untergeordnete Einheiten</h2>
                @if($entity->children && $entity->children->count() > 0)
                    <div class="space-y-2">
                        @foreach($entity->children as $child)
                            <a href="{{ route('organization.entities.show', $child) }}" class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded hover:bg-[var(--ui-muted-10)] transition-colors">
                                <div class="flex items-center">
                                    @if($child->type->icon)
                                        @php
                                            $iconName = str_replace('heroicons.', '', $child->type->icon);
                                            $iconMap = [
                                                'user-check' => 'user',
                                                'folder-kanban' => 'folder',
                                                'briefcase-globe' => 'briefcase',
                                                'server-cog' => 'server',
                                                'package-check' => 'package',
                                                'badge-check' => 'badge',
                                            ];
                                            $iconName = $iconMap[$iconName] ?? $iconName;
                                        @endphp
                                        @svg('heroicon-o-' . $iconName, 'w-4 h-4 text-[var(--ui-muted)] mr-2')
                                    @endif
                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $child->name }}</span>
                                    @if($child->code)
                                        <span class="ml-2 text-xs text-[var(--ui-muted)]">{{ $child->code }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($child->children->count() > 0)
                                        <x-ui-badge variant="info" size="xs">{{ $child->children->count() }} Kinder</x-ui-badge>
                                    @endif
                                    <x-ui-badge variant="secondary" size="sm">{{ $child->type->name }}</x-ui-badge>
                                    @svg('heroicon-o-chevron-right', 'w-4 h-4 text-[var(--ui-muted)]')
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                            @svg('heroicon-o-rectangle-group', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine untergeordneten Einheiten</p>
                        <p class="text-xs text-[var(--ui-muted)]">Diese Einheit hat keine Kinder-Entities</p>
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>

    <!-- Relations Modal -->
    <livewire:organization.entity.modal-relations/>

    <!-- Create Team Modal -->
    <x-ui-modal
        wire:model="showCreateTeamModal"
        size="md"
    >
        <x-slot name="header">
            Team aus Entität erstellen
        </x-slot>

        <div class="space-y-4">
            <div class="space-y-4">
                <x-ui-input-text
                    name="team_name"
                    label="Team-Name"
                    wire:model.live="newTeam.name"
                    required
                    placeholder="Name des Teams"
                />
                
                <x-ui-input-select
                    name="parent_team_id"
                    label="Eltern-Team (optional)"
                    :options="$this->availableTeams"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    nullLabel="Kein Eltern-Team"
                    wire:model.live="newTeam.parent_team_id"
                />
            </div>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    wire:click="closeCreateTeamModal"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="createTeam">
                    @svg('heroicon-o-user-group', 'w-4 h-4 mr-2')
                    Team erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>

