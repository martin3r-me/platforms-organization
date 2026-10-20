<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Settings'],
            ['label' => 'Entity Types', 'href' => route('organization.settings.entity-types.index')],
            ['label' => $entityType->name],
        ]">
            @if($this->isDirty)
                <x-ui-button variant="secondary-ghost" size="sm" wire:click="loadForm">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </x-ui-button>
            @endif
            <x-ui-confirm-button
                variant="danger-outline"
                size="sm"
                wire:click="delete"
                confirm-text="Entity Type wirklich löschen?"
            >
                @svg('heroicon-o-trash', 'w-4 h-4')
            </x-ui-confirm-button>
        </x-ui-page-actionbar>
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
                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                    <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                    <div class="space-y-4">
                        <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />
                        <x-ui-input-text name="code" label="Code" wire:model.live="form.code" required />
                        <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" />
                        <x-ui-input-text name="icon" label="Icon" wire:model.live="form.icon" placeholder="z.B. heroicon-o-cube" />
                        <x-ui-input-number name="sort_order" label="Sortierung" wire:model.live="form.sort_order" min="0" />
                        <div>
                            <label class="block text-sm font-medium text-[var(--ui-secondary)] mb-2">Gruppe</label>
                            <select
                                name="entity_type_group_id"
                                wire:model.live="form.entity_type_group_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                            >
                                <option value="">Keine Gruppe</option>
                                @foreach($this->entityTypeGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                            <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                        </div>
                    </div>
                </div>
        </div>
    </x-ui-page-container>

</x-ui-page>

