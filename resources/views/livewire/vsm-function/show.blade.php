<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="VSM Funktion Details" />
    </x-slot>

    <x-slot name="actions">
        @if($isEditing)
            <x-ui-button variant="secondary-outline" wire:click="$set('isEditing', false)">
                @svg('heroicon-o-x-mark', 'w-4 h-4 mr-2')
                Abbrechen
            </x-ui-button>
            <x-ui-button variant="primary" wire:click="save">
                @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                Speichern
            </x-ui-button>
        @else
            <x-ui-button variant="secondary-outline" wire:click="edit">
                @svg('heroicon-o-pencil', 'w-4 h-4 mr-2')
                Bearbeiten
            </x-ui-button>
        @endif
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-3">
                        @if($vsmFunction->is_active)
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
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $vsmFunction->code }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Entität</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                @if($vsmFunction->root_entity_id)
                                    <x-ui-badge variant="info" size="sm">Entitätsspezifisch</x-ui-badge>
                                @else
                                    <x-ui-badge variant="secondary" size="sm">Global</x-ui-badge>
                                @endif
                            </div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $vsmFunction->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Aktualisiert</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $vsmFunction->updated_at->format('d.m.Y H:i') }}</div>
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
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                <div class="space-y-4">
                    <x-ui-input-text name="code" label="Code" wire:model.defer="form.code" />
                    <x-ui-input-text name="name" label="Name" wire:model.defer="form.name" required />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.defer="form.description" />
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Entität (Parent)</label>
                        <select 
                            name="root_entity_id"
                            wire:model.defer="form.root_entity_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                        >
                            <option value="">Global (für alle Entitäten)</option>
                            @foreach($this->entities as $entity)
                                <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.defer="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
