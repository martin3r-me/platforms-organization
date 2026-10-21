{{-- Inline Relation Card — used in Entity Show Relations tab --}}
@php
    $isFrom = $direction === 'from';
    $linkedInterlinks = $relation->interlinks ?? collect();
    $hasInterlinks = $linkedInterlinks->count() > 0;
    $isExpanded = $this->expandedRelationId === $relation->id;
@endphp

<div class="rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)]">
    {{-- Relation Header --}}
    <div class="flex items-center justify-between gap-3 px-3 py-2.5">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                @svg($isFrom ? 'heroicon-o-arrow-right' : 'heroicon-o-arrow-left', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-[color:var(--nx-text)]">
                        @if($isFrom)
                            {{ $thisEntity?->name ?? '–' }}
                            <span class="px-1 font-normal text-[color:var(--nx-muted)]">{{ $relation->relationType->name ?? '–' }}</span>
                            <span @class(['italic text-[color:var(--nx-muted)]' => ! $otherEntity])>{{ $otherEntity?->name ?? 'Gelöschte Einheit' }}</span>
                        @else
                            <span @class(['italic text-[color:var(--nx-muted)]' => ! $otherEntity])>{{ $otherEntity?->name ?? 'Gelöschte Einheit' }}</span>
                            <span class="px-1 font-normal text-[color:var(--nx-muted)]">{{ $relation->relationType->name ?? '–' }}</span>
                            {{ $thisEntity?->name ?? '–' }}
                        @endif
                    </div>
                    <div class="mt-0.5 flex items-center gap-2 text-xs text-[color:var(--nx-faint)]">
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
                <div class="ml-6 mt-2 flex flex-wrap gap-1.5">
                    @foreach($linkedInterlinks as $ri)
                        @if($ri->interlink?->url)
                            <a href="{{ $ri->interlink->url }}" target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-1 rounded-full bg-[color:var(--nx-accent-soft)] px-2 py-0.5 text-xs text-[color:var(--nx-text)] transition-opacity hover:opacity-80">
                                @svg('heroicon-o-puzzle-piece', 'w-3 h-3 text-[color:var(--nx-muted)]')
                                {{ $ri->interlink->name ?? '–' }}
                                @if($ri->interlink?->reference)
                                    <span class="text-[color:var(--nx-muted)]">{{ $ri->interlink->reference }}</span>
                                @endif
                                @svg('heroicon-o-arrow-top-right-on-square', 'w-2.5 h-2.5 text-[color:var(--nx-faint)]')
                            </a>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-[color:var(--nx-accent-soft)] px-2 py-0.5 text-xs text-[color:var(--nx-text)]">
                                @svg('heroicon-o-puzzle-piece', 'w-3 h-3 text-[color:var(--nx-muted)]')
                                {{ $ri->interlink->name ?? '–' }}
                                @if($ri->interlink?->reference)
                                    <span class="text-[color:var(--nx-muted)]">{{ $ri->interlink->reference }}</span>
                                @endif
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex shrink-0 items-center gap-1">
            <x-nx-button :variant="$isExpanded ? 'secondary' : 'ghost'" size="sm"
                wire:click="toggleRelationInterlinks({{ $relation->id }})" title="Schnittstellen verwalten">
                @svg('heroicon-o-puzzle-piece', 'w-4 h-4')
                <span class="text-xs tabular-nums">{{ $linkedInterlinks->count() }}</span>
            </x-nx-button>
            <x-nx-button variant="ghost" icon size="sm" title="Beziehung löschen"
                wire:click="deleteRelation({{ $relation->id }})"
                wire:confirm="Beziehung wirklich löschen? Alle verknüpften Schnittstellen werden ebenfalls entfernt.">
                @svg('heroicon-o-trash', 'w-4 h-4 text-[color:var(--nx-danger)]')
            </x-nx-button>
        </div>
    </div>

    {{-- Interlink Management (expanded) --}}
    @if($isExpanded)
        <div class="border-t border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-3 py-3">
            <div class="mb-3">
                <div class="text-xs font-medium text-[color:var(--nx-text)]">Schnittstellen</div>
                <p class="mb-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">Konkrete Berührungspunkte dieser Beziehung: Verträge, Systeme, Datenflüsse.</p>
            </div>

            {{-- Existing Interlinks --}}
            @if($hasInterlinks)
                <div class="mb-3 overflow-hidden rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)]">
                    @foreach($linkedInterlinks as $ri)
                        <div class="flex items-center justify-between gap-2 border-b border-[color:var(--nx-line)] px-3 py-2 last:border-0">
                            <div class="flex min-w-0 flex-1 items-center gap-2">
                                @svg('heroicon-o-puzzle-piece', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium text-[color:var(--nx-text)]">
                                        @if($ri->interlink?->url)
                                            <a href="{{ $ri->interlink->url }}" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ $ri->interlink->name ?? '–' }}</a>
                                        @else
                                            {{ $ri->interlink->name ?? '–' }}
                                        @endif
                                    </div>
                                    <div class="truncate text-xs text-[color:var(--nx-muted)]">
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
                            <x-nx-button variant="ghost" icon size="sm" title="Schnittstelle entfernen"
                                wire:click="unlinkInterlink({{ $ri->id }})"
                                wire:confirm="Schnittstelle von dieser Beziehung entfernen?">
                                @svg('heroicon-o-x-mark', 'w-3.5 h-3.5 text-[color:var(--nx-muted)]')
                            </x-nx-button>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mb-3 rounded-[6px] border border-dashed border-[color:var(--nx-line-strong)] px-3 py-2.5 text-center text-xs text-[color:var(--nx-muted)]">
                    Noch keine Schnittstellen verknüpft. Füge unten die erste hinzu.
                </div>
            @endif

            {{-- Add Interlink --}}
            <div class="flex flex-wrap items-end gap-2 border-t border-[color:var(--nx-line)] pt-3">
                <div class="min-w-[12rem] flex-1">
                    <x-nx-input-select
                        name="interlinkForm_interlink_id"
                        label="Schnittstelle hinzufügen"
                        :options="$this->availableInterlinks->map(fn($i) => ['value' => (string) $i->id, 'label' => $i->name . ($i->category ? ' (' . $i->category->name . ')' : '')])->values()->all()"
                        nullable
                        nullLabel="– Schnittstelle auswählen –"
                        wire:model.live="interlinkForm.interlink_id"
                        errorKey="interlinkForm.interlink_id"
                        size="sm"
                    />
                </div>
                <div class="min-w-[12rem] flex-1">
                    <x-nx-input-text
                        name="interlinkForm_note"
                        label="Notiz (optional)"
                        wire:model.live="interlinkForm.note"
                        placeholder="z. B. Vertragsnr., System-URL"
                        size="sm"
                    />
                </div>
                <x-nx-button variant="primary" size="sm"
                    wire:click="linkInterlink({{ $relation->id }})"
                    :disabled="!$this->interlinkForm['interlink_id']"
                    title="Schnittstelle verknüpfen">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Hinzufügen</span>
                </x-nx-button>
            </div>
        </div>
    @endif
</div>
