<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$entityType->name" icon="heroicon-o-cube" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-3">
                        @if($entityType->is_active)
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
                            <span class="text-xs text-[var(--ui-muted)]">Code</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                <code>{{ $entityType->code }}</code>
                            </div>
                        </div>
                        @if($entityType->group)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Gruppe</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entityType->group->name }}</div>
                            </div>
                        @endif
                        @if($entityType->description)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Beschreibung</span>
                                <div class="text-sm text-[var(--ui-secondary)]">{{ $entityType->description }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entityType->created_at->format('d.m.Y H:i') }}</div>
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
        <div class="space-y-6">
            <!-- Tabs -->
            <div class="border-b border-[var(--ui-border)]/60">
                <nav class="flex space-x-8">
                    <button 
                        wire:click="$set('activeTab', 'details')"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors
                        {{ $activeTab === 'details' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]' }}"
                    >
                        Details
                    </button>
                    <button 
                        wire:click="$set('activeTab', 'model-mappings')"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors
                        {{ $activeTab === 'model-mappings' ? 'border-[var(--ui-primary)] text-[var(--ui-primary)]' : 'border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]' }}"
                    >
                        Model Mappings
                        @if($this->modelMappings->flatten()->count() > 0)
                            <x-ui-badge variant="info" size="xs" class="ml-2">{{ $this->modelMappings->flatten()->count() }}</x-ui-badge>
                        @endif
                    </button>
                </nav>
            </div>

            <!-- Tab Content: Details -->
            @if($activeTab === 'details')
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Code</label>
                            <code class="block px-3 py-2 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)]/40 text-sm">{{ $entityType->code }}</code>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Name</label>
                            <div class="px-3 py-2 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)]/40 text-sm">{{ $entityType->name }}</div>
                        </div>
                        @if($entityType->description)
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Beschreibung</label>
                                <div class="px-3 py-2 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)]/40 text-sm">{{ $entityType->description }}</div>
                            </div>
                        @endif
                        @if($entityType->icon)
                            <div>
                                <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Icon</label>
                                <div class="px-3 py-2 bg-[var(--ui-muted-5)] rounded border border-[var(--ui-border)]/40 text-sm">{{ $entityType->icon }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Tab Content: Model Mappings -->
            @if($activeTab === 'model-mappings')
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Model Mappings</h2>
                        <x-ui-button variant="primary" size="sm" wire:click="openModelMappingModal">
                            @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                            Neues Mapping
                        </x-ui-button>
                    </div>

                    @if($this->modelMappings->count() > 0)
                        @foreach($this->modelMappings as $moduleKey => $mappings)
                            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                                <h3 class="text-md font-semibold text-[var(--ui-secondary)] mb-4">
                                    {{ $this->modules[$moduleKey] ?? ucfirst($moduleKey) }}
                                </h3>
                                <div class="space-y-3">
                                    @foreach($mappings as $mapping)
                                        <div class="flex items-center justify-between py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                            <div class="flex-1">
                                                <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                                    {{ class_basename($mapping->model_class) }}
                                                </div>
                                                <div class="text-xs text-[var(--ui-muted)] mt-1">
                                                    <code>{{ $mapping->model_class }}</code>
                                                </div>
                                                <div class="flex items-center gap-2 mt-2">
                                                    @if($mapping->is_bidirectional)
                                                        <x-ui-badge variant="success" size="xs">Bidirektional</x-ui-badge>
                                                    @else
                                                        <x-ui-badge variant="warning" size="xs">Nur von Modul-Seite</x-ui-badge>
                                                    @endif
                                                    @if(!$mapping->is_active)
                                                        <x-ui-badge variant="muted" size="xs">Inaktiv</x-ui-badge>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <x-ui-button variant="secondary-outline" size="sm" wire:click="openModelMappingModal({{ $mapping->id }})">
                                                    @svg('heroicon-o-pencil', 'w-4 h-4')
                                                </x-ui-button>
                                                <x-ui-confirm-button 
                                                    variant="danger-outline" 
                                                    size="sm"
                                                    wire:click="deleteModelMapping({{ $mapping->id }})"
                                                    confirm-text="Mapping wirklich löschen?"
                                                >
                                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                                </x-ui-confirm-button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 text-center">
                            <p class="text-[var(--ui-muted)]">Noch keine Model Mappings vorhanden</p>
                            <x-ui-button variant="primary" size="sm" wire:click="openModelMappingModal" class="mt-4">
                                @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                                Erstes Mapping erstellen
                            </x-ui-button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-ui-page-container>

    <!-- Model Mapping Modal -->
    <x-ui-modal
        wire:model="modelMappingModalOpen"
        size="lg"
    >
        <x-slot name="header">
            {{ $editingMappingId ? 'Model Mapping bearbeiten' : 'Neues Model Mapping' }}
        </x-slot>

        <div class="space-y-4">
            <form wire:submit.prevent="saveModelMapping" class="space-y-4">
                <x-ui-input-select
                    name="module_key"
                    label="Modul"
                    wire:model.live="modelMappingForm.module_key"
                    :options="$this->modules"
                    required
                />

                <div>
                    <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">
                        Model Class <span class="text-red-500">*</span>
                    </label>
                    <select 
                        name="model_class"
                        wire:model.live="modelMappingForm.model_class"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                        required
                    >
                        <option value="">Bitte wählen...</option>
                        @foreach($this->availableModels as $model)
                            @if($model['module_key'] === $modelMappingForm['module_key'])
                                <option value="{{ $model['class'] }}">
                                    {{ $model['name'] }} ({{ $model['class'] }})
                                </option>
                            @endif
                        @endforeach
                    </select>
                    @if($modelMappingForm['module_key'] && collect($this->availableModels)->where('module_key', $modelMappingForm['module_key'])->isEmpty())
                        <p class="mt-1 text-sm text-[var(--ui-muted)]">Keine Models für dieses Modul gefunden.</p>
                    @endif
                </div>

                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        wire:model.live="modelMappingForm.is_bidirectional" 
                        id="is_bidirectional"
                        class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                    />
                    <label for="is_bidirectional" class="ml-2 text-sm text-[var(--ui-secondary)]">
                        Bidirektional (kann von beiden Seiten verlinkt werden)
                    </label>
                </div>

                <x-ui-input-number
                    name="sort_order"
                    label="Sortierung"
                    wire:model.live="modelMappingForm.sort_order"
                    min="0"
                />

                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        wire:model.live="modelMappingForm.is_active" 
                        id="is_active"
                        class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                    />
                    <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                </div>
            </form>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    wire:click="closeModelMappingModal"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="saveModelMapping">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    Speichern
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>

