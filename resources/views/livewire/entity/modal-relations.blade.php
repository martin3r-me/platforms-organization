<x-ui-modal size="xl" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-gradient-to-br from-[var(--ui-primary-10)] to-[var(--ui-primary-5)] rounded-xl flex items-center justify-center shadow-sm">
                    @svg('heroicon-o-link', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Relations verwalten</h3>
                @if(isset($entity))
                    <p class="text-sm text-[var(--ui-muted)] mt-1">{{ $entity->name }}</p>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session()->has('message'))
            <div class="p-4 bg-[var(--ui-success-10)] border border-[var(--ui-success)]/30 text-[var(--ui-success)] rounded-lg">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="p-4 bg-[var(--ui-danger-10)] border border-[var(--ui-danger)]/30 text-[var(--ui-danger)] rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <!-- Bestehende Relations: Von dieser Entity ausgehend -->
        <div>
            <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3 flex items-center gap-2">
                @svg('heroicon-o-arrow-right', 'w-4 h-4')
                Relations von dieser Entity
            </h4>
            <div class="space-y-2">
                @forelse($relationsFrom ?? [] as $relation)
                    <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
                        <div class="flex items-center justify-between p-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-lg bg-[var(--ui-primary-5)] flex items-center justify-center">
                                            @svg('heroicon-o-link', 'w-5 h-5 text-[var(--ui-primary)]')
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-[var(--ui-secondary)]">
                                            {{ $entity->name }}
                                            <span class="text-[var(--ui-muted)] font-normal">{{ $relation->relationType->name }}</span>
                                            {{ $relation->toEntity->name }}
                                        </div>
                                        <div class="text-xs text-[var(--ui-muted)] mt-0.5">
                                            {{ $relation->toEntity->type->name ?? 'Unbekannt' }}
                                            @if($relation->valid_from || $relation->valid_to)
                                                &middot;
                                                @if($relation->valid_from)
                                                    Von: {{ $relation->valid_from->format('d.m.Y') }}
                                                @endif
                                                @if($relation->valid_to)
                                                    Bis: {{ $relation->valid_to->format('d.m.Y') }}
                                                @endif
                                            @endif
                                        </div>
                                        {{-- Interlink Badges --}}
                                        @if($relation->interlinks && $relation->interlinks->count() > 0)
                                            <div class="flex flex-wrap gap-1 mt-1.5">
                                                @foreach($relation->interlinks as $ri)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-[var(--ui-primary-5)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20">
                                                        @svg('heroicon-o-puzzle-piece', 'w-3 h-3')
                                                        {{ $ri->interlink->name ?? '–' }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0 ml-4 flex items-center gap-1">
                                <x-ui-button
                                    variant="secondary-ghost"
                                    size="sm"
                                    wire:click="toggleRelationInterlinks({{ $relation->id }})"
                                    title="Interlinks verwalten"
                                >
                                    @svg('heroicon-o-puzzle-piece', 'w-4 h-4')
                                    <span class="text-xs">{{ $relation->interlinks?->count() ?? 0 }}</span>
                                </x-ui-button>
                                <x-ui-button
                                    variant="danger-outline"
                                    size="sm"
                                    wire:click="deleteRelation({{ $relation->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="deleteRelation({{ $relation->id }})"
                                >
                                    <span wire:loading.remove wire:target="deleteRelation({{ $relation->id }})">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </span>
                                    <span wire:loading wire:target="deleteRelation({{ $relation->id }})">
                                        @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                                    </span>
                                </x-ui-button>
                            </div>
                        </div>

                        {{-- Expanded Interlink Section --}}
                        @if($expandedRelationId === $relation->id)
                            <div class="border-t border-[var(--ui-border)]/40 p-4 bg-[var(--ui-muted-5)]/50">
                                <h5 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Verknüpfte Interlinks</h5>

                                {{-- Bestehende Interlinks dieser Relation --}}
                                @if($relation->interlinks && $relation->interlinks->count() > 0)
                                    <div class="space-y-1.5 mb-3">
                                        @foreach($relation->interlinks as $ri)
                                            <div class="flex items-center justify-between py-2 px-3 rounded-md bg-[var(--ui-surface)] border border-[var(--ui-border)]/40">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $ri->interlink->name ?? '–' }}</div>
                                                    <div class="text-xs text-[var(--ui-muted)]">
                                                        @if($ri->interlink?->category)
                                                            {{ $ri->interlink->category->name }}
                                                        @endif
                                                        @if($ri->interlink?->type)
                                                            &middot; {{ $ri->interlink->type->name }}
                                                        @endif
                                                        @if($ri->note)
                                                            &middot; {{ $ri->note }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <x-ui-button
                                                    variant="danger-ghost"
                                                    size="sm"
                                                    wire:click="unlinkInterlink({{ $ri->id }})"
                                                    title="Interlink entfernen"
                                                >
                                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                                </x-ui-button>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-xs text-[var(--ui-muted)] mb-3">Noch keine Interlinks verknüpft.</p>
                                @endif

                                {{-- Inline-Formular zum Hinzufügen --}}
                                <div class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <x-ui-input-select
                                            name="selectedInterlinkId"
                                            label="Interlink"
                                            wire:model.live="selectedInterlinkId"
                                            :options="$availableInterlinks"
                                            optionValue="id"
                                            optionLabel="name"
                                            :nullable="true"
                                            nullLabel="Interlink auswählen..."
                                            size="sm"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <x-ui-input-text
                                            name="interlinkNote"
                                            label="Notiz (optional)"
                                            wire:model.live="interlinkNote"
                                            placeholder="Optionale Notiz"
                                            size="sm"
                                        />
                                    </div>
                                    <x-ui-button
                                        variant="primary"
                                        size="sm"
                                        wire:click="linkInterlink({{ $relation->id }})"
                                        :disabled="!$selectedInterlinkId"
                                    >
                                        @svg('heroicon-o-plus', 'w-4 h-4')
                                    </x-ui-button>
                                </div>
                                @error('selectedInterlinkId')
                                    <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                            @svg('heroicon-o-link', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine ausgehenden Relations</p>
                        <p class="text-xs text-[var(--ui-muted)]">Erstellen Sie eine neue Relation zu einer anderen Entity</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Bestehende Relations: Zu dieser Entity führend -->
        <div>
            <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3 flex items-center gap-2">
                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                Relations zu dieser Entity
            </h4>
            <div class="space-y-2">
                @forelse($relationsTo ?? [] as $relation)
                    <div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
                        <div class="flex items-center justify-between p-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-10 h-10 rounded-lg bg-[var(--ui-primary-5)] flex items-center justify-center">
                                            @svg('heroicon-o-link', 'w-5 h-5 text-[var(--ui-primary)]')
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-[var(--ui-secondary)]">
                                            {{ $relation->fromEntity->name }}
                                            <span class="text-[var(--ui-muted)] font-normal">{{ $relation->relationType->name }}</span>
                                            {{ $entity->name }}
                                        </div>
                                        <div class="text-xs text-[var(--ui-muted)] mt-0.5">
                                            {{ $relation->fromEntity->type->name ?? 'Unbekannt' }}
                                            @if($relation->valid_from || $relation->valid_to)
                                                &middot;
                                                @if($relation->valid_from)
                                                    Von: {{ $relation->valid_from->format('d.m.Y') }}
                                                @endif
                                                @if($relation->valid_to)
                                                    Bis: {{ $relation->valid_to->format('d.m.Y') }}
                                                @endif
                                            @endif
                                        </div>
                                        {{-- Interlink Badges --}}
                                        @if($relation->interlinks && $relation->interlinks->count() > 0)
                                            <div class="flex flex-wrap gap-1 mt-1.5">
                                                @foreach($relation->interlinks as $ri)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-[var(--ui-primary-5)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20">
                                                        @svg('heroicon-o-puzzle-piece', 'w-3 h-3')
                                                        {{ $ri->interlink->name ?? '–' }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0 ml-4 flex items-center gap-1">
                                <x-ui-button
                                    variant="secondary-ghost"
                                    size="sm"
                                    wire:click="toggleRelationInterlinks({{ $relation->id }})"
                                    title="Interlinks verwalten"
                                >
                                    @svg('heroicon-o-puzzle-piece', 'w-4 h-4')
                                    <span class="text-xs">{{ $relation->interlinks?->count() ?? 0 }}</span>
                                </x-ui-button>
                                <x-ui-button
                                    variant="danger-outline"
                                    size="sm"
                                    wire:click="deleteRelation({{ $relation->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="deleteRelation({{ $relation->id }})"
                                >
                                    <span wire:loading.remove wire:target="deleteRelation({{ $relation->id }})">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </span>
                                    <span wire:loading wire:target="deleteRelation({{ $relation->id }})">
                                        @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                                    </span>
                                </x-ui-button>
                            </div>
                        </div>

                        {{-- Expanded Interlink Section --}}
                        @if($expandedRelationId === $relation->id)
                            <div class="border-t border-[var(--ui-border)]/40 p-4 bg-[var(--ui-muted-5)]/50">
                                <h5 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Verknüpfte Interlinks</h5>

                                @if($relation->interlinks && $relation->interlinks->count() > 0)
                                    <div class="space-y-1.5 mb-3">
                                        @foreach($relation->interlinks as $ri)
                                            <div class="flex items-center justify-between py-2 px-3 rounded-md bg-[var(--ui-surface)] border border-[var(--ui-border)]/40">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $ri->interlink->name ?? '–' }}</div>
                                                    <div class="text-xs text-[var(--ui-muted)]">
                                                        @if($ri->interlink?->category)
                                                            {{ $ri->interlink->category->name }}
                                                        @endif
                                                        @if($ri->interlink?->type)
                                                            &middot; {{ $ri->interlink->type->name }}
                                                        @endif
                                                        @if($ri->note)
                                                            &middot; {{ $ri->note }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <x-ui-button
                                                    variant="danger-ghost"
                                                    size="sm"
                                                    wire:click="unlinkInterlink({{ $ri->id }})"
                                                    title="Interlink entfernen"
                                                >
                                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                                </x-ui-button>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-xs text-[var(--ui-muted)] mb-3">Noch keine Interlinks verknüpft.</p>
                                @endif

                                {{-- Inline-Formular --}}
                                <div class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <x-ui-input-select
                                            name="selectedInterlinkId"
                                            label="Interlink"
                                            wire:model.live="selectedInterlinkId"
                                            :options="$availableInterlinks"
                                            optionValue="id"
                                            optionLabel="name"
                                            :nullable="true"
                                            nullLabel="Interlink auswählen..."
                                            size="sm"
                                        />
                                    </div>
                                    <div class="flex-1">
                                        <x-ui-input-text
                                            name="interlinkNote"
                                            label="Notiz (optional)"
                                            wire:model.live="interlinkNote"
                                            placeholder="Optionale Notiz"
                                            size="sm"
                                        />
                                    </div>
                                    <x-ui-button
                                        variant="primary"
                                        size="sm"
                                        wire:click="linkInterlink({{ $relation->id }})"
                                        :disabled="!$selectedInterlinkId"
                                    >
                                        @svg('heroicon-o-plus', 'w-4 h-4')
                                    </x-ui-button>
                                </div>
                                @error('selectedInterlinkId')
                                    <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                            @svg('heroicon-o-link', 'w-8 h-8 text-[var(--ui-muted)]')
                        </div>
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine eingehenden Relations</p>
                        <p class="text-xs text-[var(--ui-muted)]">Andere Entities können Relations zu dieser Entity erstellen</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Neue Relation erstellen -->
        <div class="pt-6 border-t border-[var(--ui-border)]/60">
            <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">Neue Relation erstellen</h4>
            <div class="space-y-4">
                <!-- Ziel-Entity -->
                <div>
                    <x-ui-input-select
                        name="selectedToEntityId"
                        label="Ziel-Entity"
                        wire:model.live="selectedToEntityId"
                        :options="$availableEntities"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="Entity auswählen..."
                    />
                    @error('selectedToEntityId')
                        <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Relation Type -->
                <div>
                    <x-ui-input-select
                        name="selectedRelationTypeId"
                        label="Relation Type"
                        wire:model.live="selectedRelationTypeId"
                        :options="$availableRelationTypes"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="true"
                        nullLabel="Relation Type auswählen..."
                    />
                    @error('selectedRelationTypeId')
                        <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Zeitliche Gültigkeit (optional) -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">
                            Gültig von (optional)
                        </label>
                        <x-ui-input-text
                            name="validFrom"
                            type="date"
                            wire:model="validFrom"
                        />
                        @error('validFrom')
                            <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-[var(--ui-secondary)] mb-2">
                            Gültig bis (optional)
                        </label>
                        <x-ui-input-text
                            name="validTo"
                            type="date"
                            wire:model="validTo"
                        />
                        @error('validTo')
                            <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @error('general')
                    <div class="p-3 bg-[var(--ui-danger-10)] border border-[var(--ui-danger)]/30 text-[var(--ui-danger)] rounded-lg text-sm">
                        {{ $message }}
                    </div>
                @enderror

                <!-- Button -->
                <div>
                    <x-ui-button
                        variant="primary"
                        wire:click="createRelation"
                        wire:loading.attr="disabled"
                        :disabled="!$selectedToEntityId || !$selectedRelationTypeId"
                        class="w-full"
                    >
                        <span wire:loading.remove wire:target="createRelation">
                            Relation erstellen
                        </span>
                        <span wire:loading wire:target="createRelation" class="inline-flex items-center gap-2">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                            Erstellen…
                        </span>
                    </x-ui-button>
                </div>
            </div>
        </div>
    </div>
</x-ui-modal>
