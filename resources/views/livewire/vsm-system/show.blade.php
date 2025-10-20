<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="VSM System Details" />
    </x-slot>

    <x-slot name="actions">
        <x-ui-button variant="secondary-outline" wire:click="edit">
            @svg('heroicon-o-pencil', 'w-4 h-4 mr-2')
            Bearbeiten
        </x-ui-button>
        <x-ui-button wire:click="save">
            @svg('heroicon-o-check', 'w-4 h-4 mr-2')
            Speichern
        </x-ui-button>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-3">
                        @if($vsmSystem->is_active)
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
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $vsmSystem->code }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Reihenfolge</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $vsmSystem->sort_order }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $vsmSystem->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Aktualisiert</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $vsmSystem->updated_at->format('d.m.Y H:i') }}</div>
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
                    <x-ui-input-text name="code" label="Code" wire:model.defer="form.code" required />
                    <x-ui-input-text name="name" label="Name" wire:model.defer="form.name" required />
                    <x-ui-input-textarea name="description" label="Beschreibung" wire:model.defer="form.description" />
                    <div class="flex items-center">
                        <input type="checkbox" wire:model.defer="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                        <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Zugeordnete Organisationseinheiten</h2>
                <div class="text-sm text-[var(--ui-muted)]">
                    @if($vsmSystem->entities && $vsmSystem->entities->count() > 0)
                        <div class="space-y-2">
                            @foreach($vsmSystem->entities as $entity)
                                <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded">
                                    <span class="text-sm">{{ $entity->name }}</span>
                                    <x-ui-badge variant="secondary" size="sm">{{ $entity->type->name }}</x-ui-badge>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p>Keine Organisationseinheiten zugeordnet</p>
                    @endif
                </div>
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
