{{-- Inline Relation Card — used in Entity Show Relations tab --}}
@php
    $isFrom = $direction === 'from';
    $linkedInterlinks = $relation->interlinks ?? collect();
    $hasInterlinks = $linkedInterlinks->count() > 0;
    $isExpanded = $this->expandedRelationId === $relation->id;
@endphp

<div class="rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
    {{-- Relation Header --}}
    <div class="flex items-center justify-between p-4">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 rounded-lg {{ $isFrom ? 'bg-[var(--ui-primary-5)]' : 'bg-[var(--ui-info-5)]' }} flex items-center justify-center">
                        @svg($isFrom ? 'heroicon-o-arrow-right' : 'heroicon-o-arrow-left', 'w-4 h-4 ' . ($isFrom ? 'text-[var(--ui-primary)]' : 'text-[var(--ui-info)]'))
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-[var(--ui-secondary)]">
                        @if($isFrom)
                            {{ $thisEntity->name }}
                            <span class="font-normal text-[var(--ui-muted)] px-1">{{ $relation->relationType->name ?? '–' }}</span>
                            {{ $otherEntity->name }}
                        @else
                            {{ $otherEntity->name }}
                            <span class="font-normal text-[var(--ui-muted)] px-1">{{ $relation->relationType->name ?? '–' }}</span>
                            {{ $thisEntity->name }}
                        @endif
                    </div>
                    <div class="flex items-center gap-2 text-xs text-[var(--ui-muted)] mt-0.5">
                        <span>{{ $otherEntity->type->name ?? 'Unbekannt' }}</span>
                        @if($relation->valid_from || $relation->valid_to)
                            <span>&middot;</span>
                            @if($relation->valid_from)
                                <span>ab {{ $relation->valid_from->format('d.m.Y') }}</span>
                            @endif
                            @if($relation->valid_to)
                                <span>bis {{ $relation->valid_to->format('d.m.Y') }}</span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            {{-- Interlink Badges (always visible) --}}
            @if($hasInterlinks)
                <div class="flex flex-wrap gap-1.5 mt-2 ml-10">
                    @foreach($linkedInterlinks as $ri)
                        @if($ri->interlink?->url)
                            <a href="{{ $ri->interlink->url }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-[var(--ui-primary-5)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20 hover:bg-[var(--ui-primary-10)] transition-colors">
                                @svg('heroicon-o-puzzle-piece', 'w-3 h-3')
                                {{ $ri->interlink->name ?? '–' }}
                                @if($ri->interlink?->reference)
                                    <span class="text-[var(--ui-muted)]">{{ $ri->interlink->reference }}</span>
                                @endif
                                @svg('heroicon-o-arrow-top-right-on-square', 'w-2.5 h-2.5 text-[var(--ui-muted)]')
                            </a>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-[var(--ui-primary-5)] text-[var(--ui-primary)] border border-[var(--ui-primary)]/20">
                                @svg('heroicon-o-puzzle-piece', 'w-3 h-3')
                                {{ $ri->interlink->name ?? '–' }}
                                @if($ri->interlink?->reference)
                                    <span class="text-[var(--ui-muted)]">{{ $ri->interlink->reference }}</span>
                                @endif
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex-shrink-0 ml-4 flex items-center gap-1">
            <x-ui-button
                variant="{{ $isExpanded ? 'primary-outline' : 'secondary-ghost' }}"
                size="sm"
                wire:click="toggleRelationInterlinks({{ $relation->id }})"
                title="Schnittstellen verwalten"
            >
                @svg('heroicon-o-puzzle-piece', 'w-4 h-4')
                <span class="text-xs">{{ $linkedInterlinks->count() }}</span>
            </x-ui-button>
            <x-ui-confirm-button
                variant="danger-outline"
                size="sm"
                wire:click="deleteRelation({{ $relation->id }})"
                confirm-text="Beziehung wirklich löschen? Alle verknüpften Schnittstellen werden ebenfalls entfernt."
            >
                @svg('heroicon-o-trash', 'w-4 h-4')
            </x-ui-confirm-button>
        </div>
    </div>

    {{-- Interlink Management (expanded) --}}
    @if($isExpanded)
        <div class="border-t border-[var(--ui-border)]/40 p-4 bg-[var(--ui-muted-5)]/50">
            <div class="mb-3">
                <h5 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wider">Schnittstellen (Interlinks)</h5>
                <p class="text-xs text-[var(--ui-muted)] mt-0.5">Konkrete Berührungspunkte dieser Beziehung: Verträge, Systeme, Datenflüsse, etc.</p>
            </div>

            {{-- Existing Interlinks --}}
            @if($hasInterlinks)
                <div class="space-y-1.5 mb-4">
                    @foreach($linkedInterlinks as $ri)
                        <div class="flex items-center justify-between py-2 px-3 rounded-md bg-[var(--ui-surface)] border border-[var(--ui-border)]/40">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-puzzle-piece', 'w-4 h-4 text-[var(--ui-primary)] flex-shrink-0')
                                    <div>
                                        <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                            @if($ri->interlink?->url)
                                                <a href="{{ $ri->interlink->url }}" target="_blank" class="hover:text-[var(--ui-primary)] hover:underline">{{ $ri->interlink->name ?? '–' }}</a>
                                            @else
                                                {{ $ri->interlink->name ?? '–' }}
                                            @endif
                                        </div>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            @if($ri->interlink?->category)
                                                <span>{{ $ri->interlink->category->name }}</span>
                                            @endif
                                            @if($ri->interlink?->type)
                                                <span>&middot; {{ $ri->interlink->type->name }}</span>
                                            @endif
                                            @if($ri->interlink?->reference)
                                                <span>&middot; {{ $ri->interlink->reference }}</span>
                                            @endif
                                            @if($ri->note)
                                                <span>&middot; <em>{{ $ri->note }}</em></span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <x-ui-confirm-button
                                variant="danger-ghost"
                                size="sm"
                                wire:click="unlinkInterlink({{ $ri->id }})"
                                confirm-text="Schnittstelle von dieser Beziehung entfernen?"
                            >
                                @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                            </x-ui-confirm-button>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-3 mb-4 text-center rounded-md border border-dashed border-[var(--ui-border)]/40 bg-[var(--ui-surface)]">
                    <p class="text-xs text-[var(--ui-muted)]">Noch keine Schnittstellen verknüpft. Füge unten die erste hinzu.</p>
                </div>
            @endif

            {{-- Add Interlink --}}
            <div class="pt-3 border-t border-[var(--ui-border)]/30">
                <p class="text-xs font-medium text-[var(--ui-secondary)] mb-2">Schnittstelle hinzufügen</p>
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-ui-input-select
                            name="interlinkForm_interlink_id"
                            label="Schnittstelle"
                            :options="$this->availableInterlinks->map(fn($i) => ['value' => (string) $i->id, 'label' => $i->name . ($i->category ? ' (' . $i->category->name . ')' : '')])->toArray()"
                            nullable
                            nullLabel="– Schnittstelle auswählen –"
                            wire:model.live="interlinkForm.interlink_id"
                            size="sm"
                        />
                    </div>
                    <div class="flex-1">
                        <x-ui-input-text
                            name="interlinkForm_note"
                            label="Notiz (optional)"
                            wire:model.live="interlinkForm.note"
                            placeholder="z.B. Vertragsnr., System-URL"
                            size="sm"
                        />
                    </div>
                    <x-ui-button
                        variant="primary"
                        size="sm"
                        wire:click="linkInterlink({{ $relation->id }})"
                        :disabled="!$this->interlinkForm['interlink_id']"
                        title="Schnittstelle verknüpfen"
                    >
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Hinzufügen</span>
                    </x-ui-button>
                </div>
                @error('interlinkForm.interlink_id')
                    <p class="mt-1 text-xs text-[var(--ui-danger)]">{{ $message }}</p>
                @enderror
            </div>
        </div>
    @endif
</div>
