@php
    $typeLabels = app(\Platform\Organization\Services\EntityLinkRegistry::class)->activityTypeLabels();
@endphp
<div class="flex h-full flex-col">
    <div class="flex-1 space-y-3 overflow-y-auto p-4">
        @forelse ($this->feedItems as $activity)
            @php
                $isTimeEntry = $activity->activityable_type === \Platform\Organization\Models\OrganizationTimeEntry::class;
                $isEntity = $activity->activityable_type === \Platform\Organization\Models\OrganizationEntity::class;
                $shortType = $typeLabels[$activity->activityable_type] ?? class_basename($activity->activityable_type ?? '');
                [$glyph, $tone] = match (true) {
                    $activity->activity_type === 'manual' => ['heroicon-s-pencil', 'var(--nx-accent)'],
                    $activity->name === 'created' => ['heroicon-s-plus', 'var(--nx-success)'],
                    $activity->name === 'updated' => ['heroicon-s-pencil-square', 'var(--nx-info)'],
                    $activity->name === 'deleted' => ['heroicon-s-trash', 'var(--nx-danger)'],
                    default => ['heroicon-s-cog-6-tooth', 'var(--nx-faint)'],
                };
            @endphp
            <div class="group flex gap-3">
                <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[color:var(--nx-accent-soft)]">
                    @svg($glyph, 'w-3 h-3', ['style' => 'color:' . $tone])
                </div>
                <div class="min-w-0 flex-grow text-sm">
                    @if ($activity->activity_type === 'manual')
                        <p class="m-0 text-[color:var(--nx-text)]">{{ $activity->message }}</p>
                    @elseif ($activity->name === 'created')
                        @if ($isTimeEntry)
                            @php
                                $props = $activity->properties ?? [];
                                $minutes = $props['minutes'] ?? 0;
                            @endphp
                            <p class="m-0 text-[color:var(--nx-muted)]">
                                Zeitbuchung
                                <span class="font-medium tabular-nums text-[color:var(--nx-text)]">{{ intdiv($minutes, 60) }}:{{ str_pad($minutes % 60, 2, '0', STR_PAD_LEFT) }} h</span>
                                @if ($props['note'] ?? null)
                                    — <span class="italic">„{{ \Illuminate\Support\Str::limit($props['note'], 60) }}“</span>
                                @endif
                            </p>
                        @elseif ($isEntity)
                            <p class="m-0 text-[color:var(--nx-muted)]">Einheit angelegt</p>
                        @else
                            <p class="m-0 text-[color:var(--nx-muted)]"><span class="font-medium text-[color:var(--nx-text)]">{{ $shortType }}</span> angelegt</p>
                        @endif
                    @elseif ($activity->name === 'updated' && is_array($activity->properties))
                        <p class="m-0 text-[color:var(--nx-muted)]">
                            @if (! $isEntity && ! $isTimeEntry)
                                <span class="font-medium text-[color:var(--nx-text)]">{{ $shortType }}:</span>
                            @endif
                            {{ collect($activity->properties)->keys()->map(fn ($k) => ucfirst(str_replace('_', ' ', $k)))->implode(', ') }} geändert
                        </p>
                    @elseif ($activity->name === 'deleted')
                        <p class="m-0 text-[color:var(--nx-muted)]">
                            @if (! $isEntity && ! $isTimeEntry)
                                <span class="font-medium text-[color:var(--nx-text)]">{{ $shortType }}</span>
                            @endif
                            gelöscht
                        </p>
                    @endif

                    <div class="mt-0.5 flex items-center gap-2">
                        <span class="text-[11px] text-[color:var(--nx-faint)]">{{ $activity->user?->name ?? 'System' }} · {{ $activity->created_at->diffForHumans() }}</span>
                        @if ($activity->activity_type === 'manual' && $activity->user_id === auth()->id())
                            <button type="button" wire:click="deleteNote({{ $activity->id }})" wire:confirm="Notiz wirklich löschen?" title="Notiz löschen"
                                class="text-[color:var(--nx-faint)] opacity-0 transition-opacity hover:text-[color:var(--nx-danger)] group-hover:opacity-100">
                                @svg('heroicon-o-trash', 'w-3 h-3')
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="m-0 py-4 text-center text-sm text-[color:var(--nx-faint)]">Noch keine Aktivität.</p>
        @endforelse
    </div>

    {{-- Notiz-Eingabe (nur auf Entity-Seiten) --}}
    @if ($entityId)
        <div class="shrink-0 border-t border-[color:var(--nx-line)] p-3">
            <form wire:submit="addNote" class="flex items-end gap-2">
                <textarea wire:model="newNote" rows="2" placeholder="Notiz hinzufügen…"
                    class="flex-1 resize-none rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-3 py-2 text-sm text-[color:var(--nx-text)] placeholder:text-[color:var(--nx-faint)] focus:border-[color:var(--nx-accent)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]"></textarea>
                <x-nx-button type="submit" variant="primary" icon title="Notiz speichern">
                    @svg('heroicon-s-arrow-up', 'w-4 h-4')
                </x-nx-button>
            </form>
        </div>
    @endif
</div>
