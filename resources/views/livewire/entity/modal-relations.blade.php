<x-ui-modal size="xl" wire:model="open" :closeButton="true">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-gradient-to-br from-[var(--ui-primary-10)] to-[var(--ui-primary-5)] rounded-xl flex items-center justify-center shadow-sm">
                    @svg('heroicon-o-arrows-right-left', 'w-6 h-6 text-[var(--ui-primary)]')
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-xl font-bold text-[var(--ui-secondary)]">Beziehungen & Schnittstellen</h3>
                @if(isset($entity))
                    <p class="text-sm text-[var(--ui-muted)] mt-0.5">{{ $entity->name }}{{ $entity->type ? ' ('.$entity->type->name.')' : '' }}</p>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Konzept-Erklärung --}}
        <div class="bg-[var(--ui-info-5)] border border-[var(--ui-info-20)] rounded-lg p-4">
            <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">So funktioniert es</h4>
            <p class="text-sm text-[var(--ui-muted)]">
                <strong>Beziehungen</strong> beschreiben, wie zwei Organisationseinheiten zusammenhängen (z.B. "liefert an", "beauftragt", "ist Dienstleister für").
                An jede Beziehung können <strong>Schnittstellen</strong> (Interlinks) gehängt werden — das sind die konkreten Berührungspunkte: Verträge, Ticketsysteme, Datenflüsse, APIs usw.
                Pro Beziehung sind <strong>mehrere Schnittstellen</strong> möglich.
            </p>
        </div>

        @if (session()->has('message'))
            <div class="p-3 bg-[var(--ui-success-10)] border border-[var(--ui-success)]/30 text-[var(--ui-success)] rounded-lg text-sm">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="p-3 bg-[var(--ui-danger-10)] border border-[var(--ui-danger)]/30 text-[var(--ui-danger)] rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- ── Ausgehende Beziehungen ──────────────────────────── --}}
        <div>
            <div class="mb-3">
                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] flex items-center gap-2">
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-primary)]')
                    Ausgehende Beziehungen
                </h4>
                <p class="text-xs text-[var(--ui-muted)] mt-0.5 ml-6">Beziehungen, die von <strong>{{ $entity->name ?? 'dieser Entity' }}</strong> zu anderen Einheiten ausgehen.</p>
            </div>

            <div class="space-y-2">
                @forelse($relationsFrom ?? [] as $relation)
                    @include('organization::livewire.entity.partials.relation-card', [
                        'relation' => $relation,
                        'direction' => 'from',
                        'thisEntity' => $entity,
                        'otherEntity' => $relation->toEntity,
                    ])
                @empty
                    <div class="p-6 text-center rounded-lg border border-dashed border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        @svg('heroicon-o-arrow-right', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-0.5">Keine ausgehenden Beziehungen</p>
                        <p class="text-xs text-[var(--ui-muted)]">Erstelle unten eine neue Beziehung zu einer anderen Organisationseinheit.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ── Eingehende Beziehungen ──────────────────────────── --}}
        <div>
            <div class="mb-3">
                <h4 class="text-sm font-semibold text-[var(--ui-secondary)] flex items-center gap-2">
                    @svg('heroicon-o-arrow-left', 'w-4 h-4 text-[var(--ui-info)]')
                    Eingehende Beziehungen
                </h4>
                <p class="text-xs text-[var(--ui-muted)] mt-0.5 ml-6">Beziehungen, die von anderen Einheiten auf <strong>{{ $entity->name ?? 'diese Entity' }}</strong> zeigen.</p>
            </div>

            <div class="space-y-2">
                @forelse($relationsTo ?? [] as $relation)
                    @include('organization::livewire.entity.partials.relation-card', [
                        'relation' => $relation,
                        'direction' => 'to',
                        'thisEntity' => $entity,
                        'otherEntity' => $relation->fromEntity,
                    ])
                @empty
                    <div class="p-6 text-center rounded-lg border border-dashed border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                        @svg('heroicon-o-arrow-left', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-0.5">Keine eingehenden Beziehungen</p>
                        <p class="text-xs text-[var(--ui-muted)]">Andere Organisationseinheiten können Beziehungen zu dieser Entity anlegen.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ── Neue Beziehung erstellen ────────────────────────── --}}
        <div class="pt-6 border-t border-[var(--ui-border)]/60">
            <div class="mb-4">
                <h4 class="text-sm font-semibold text-[var(--ui-secondary)]">Neue Beziehung erstellen</h4>
                <p class="text-xs text-[var(--ui-muted)] mt-0.5">Erstellt eine ausgehende Beziehung von <strong>{{ $entity->name ?? 'dieser Entity' }}</strong> zur gewählten Ziel-Entity. Schnittstellen können danach hinzugefügt werden.</p>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-ui-input-select
                            name="selectedToEntityId"
                            label="Ziel-Entity"
                            wire:model.live="selectedToEntityId"
                            :options="$availableEntities"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="– Entity auswählen –"
                        />
                        <p class="text-xs text-[var(--ui-muted)] mt-1">Mit welcher Organisationseinheit besteht die Beziehung?</p>
                        @error('selectedToEntityId')
                            <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <x-ui-input-select
                            name="selectedRelationTypeId"
                            label="Art der Beziehung"
                            wire:model.live="selectedRelationTypeId"
                            :options="$availableRelationTypes"
                            optionValue="id"
                            optionLabel="name"
                            :nullable="true"
                            nullLabel="– Beziehungstyp auswählen –"
                        />
                        <p class="text-xs text-[var(--ui-muted)] mt-1">Wie ist die Beziehung charakterisiert? (z.B. "liefert an", "beauftragt")</p>
                        @error('selectedRelationTypeId')
                            <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-text name="validFrom" label="Gültig von (optional)" type="date" wire:model="validFrom" />
                    <x-ui-input-text name="validTo" label="Gültig bis (optional)" type="date" wire:model="validTo" />
                </div>
                @error('validFrom') <p class="text-xs text-[var(--ui-danger)]">{{ $message }}</p> @enderror
                @error('validTo') <p class="text-xs text-[var(--ui-danger)]">{{ $message }}</p> @enderror

                @error('general')
                    <div class="p-3 bg-[var(--ui-danger-10)] border border-[var(--ui-danger)]/30 text-[var(--ui-danger)] rounded-lg text-sm">
                        {{ $message }}
                    </div>
                @enderror

                <x-ui-button
                    variant="primary"
                    wire:click="createRelation"
                    wire:loading.attr="disabled"
                    :disabled="!$selectedToEntityId || !$selectedRelationTypeId"
                    class="w-full"
                >
                    <span wire:loading.remove wire:target="createRelation">
                        @svg('heroicon-o-plus', 'w-4 h-4 mr-1')
                        Beziehung erstellen
                    </span>
                    <span wire:loading wire:target="createRelation" class="inline-flex items-center gap-2">
                        @svg('heroicon-o-arrow-path', 'w-4 h-4 animate-spin')
                        Erstellen...
                    </span>
                </x-ui-button>
            </div>
        </div>
    </div>
</x-ui-modal>
