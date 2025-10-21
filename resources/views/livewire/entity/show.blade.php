<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Organisationseinheit Details" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <!-- Action Buttons -->
                <div class="pb-4 border-b border-[var(--ui-border)]/40">
                    @if($this->isDirty())
                        <div class="flex space-x-2">
                            <x-ui-button variant="secondary-outline" wire:click="loadForm" size="sm" class="flex-1">
                                @svg('heroicon-o-x-mark', 'w-4 h-4 mr-2')
                                Abbrechen
                            </x-ui-button>
                            <x-ui-button variant="primary" wire:click="save" size="sm" class="flex-1">
                                @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                                Speichern
                            </x-ui-button>
                        </div>
                    @else
                        <x-ui-button variant="secondary-outline" wire:click="edit" size="sm" class="w-full">
                            @svg('heroicon-o-pencil', 'w-4 h-4 mr-2')
                            Bearbeiten
                        </x-ui-button>
                    @endif
                </div>
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

            <!-- Verfügbare Dimensionen -->
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Verfügbare Dimensionen</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Kostenstellen -->
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Kostenstellen</h3>
                        <div class="space-y-2">
                            @if($this->availableCostCenters->count() > 0)
                                @foreach($this->availableCostCenters as $costCenter)
                                    <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded">
                                        <div>
                                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $costCenter->name }}</div>
                                            <div class="text-xs text-[var(--ui-muted)]">{{ $costCenter->code }}</div>
                                        </div>
                                        @if($costCenter->isGlobal())
                                            <x-ui-badge variant="secondary" size="sm">Global</x-ui-badge>
                                        @else
                                            <x-ui-badge variant="info" size="sm">Entitätsspezifisch</x-ui-badge>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <div class="text-sm text-[var(--ui-muted)] py-2">Keine Kostenstellen verfügbar</div>
                            @endif
                        </div>
                    </div>

                    <!-- VSM Funktionen -->
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

            @if($entity->children && $entity->children->count() > 0)
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Untergeordnete Einheiten</h2>
                    <div class="space-y-2">
                        @foreach($entity->children as $child)
                            <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded">
                                <div class="flex items-center">
                                    @if($child->type->icon)
                                        @svg('heroicon-o-' . str_replace('heroicons.', '', $child->type->icon), 'w-4 h-4 text-[var(--ui-muted)] mr-2')
                                    @endif
                                    <span class="text-sm font-medium">{{ $child->name }}</span>
                                </div>
                                <x-ui-badge variant="secondary" size="sm">{{ $child->type->name }}</x-ui-badge>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
