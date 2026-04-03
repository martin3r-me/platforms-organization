<div class="h-full flex flex-col">
    {{-- Timeline --}}
    <div class="flex-1 overflow-y-auto p-4 space-y-3">
        @forelse($this->feedItems as $activity)
            <div class="flex gap-3">
                <div class="flex-shrink-0 mt-0.5">
                    @if($activity->activity_type === 'manual')
                        <div class="w-6 h-6 rounded-full bg-[var(--ui-primary-10)] flex items-center justify-center">
                            @svg('heroicon-s-pencil', 'w-3 h-3 text-[var(--ui-primary)]')
                        </div>
                    @elseif($activity->name === 'created')
                        <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                            @svg('heroicon-s-plus', 'w-3 h-3 text-green-600')
                        </div>
                    @elseif($activity->name === 'updated')
                        <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center">
                            @svg('heroicon-s-pencil-square', 'w-3 h-3 text-blue-600')
                        </div>
                    @elseif($activity->name === 'deleted')
                        <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center">
                            @svg('heroicon-s-trash', 'w-3 h-3 text-red-600')
                        </div>
                    @else
                        <div class="w-6 h-6 rounded-full bg-[var(--ui-muted-10)] flex items-center justify-center">
                            @svg('heroicon-s-cog-6-tooth', 'w-3 h-3 text-[var(--ui-muted)]')
                        </div>
                    @endif
                </div>
                <div class="flex-grow min-w-0">
                    @if($activity->activity_type === 'manual')
                        <p class="text-sm text-[var(--ui-secondary)]">{{ $activity->message }}</p>
                    @elseif($activity->name === 'created')
                        @if($activity->activityable_type === \Platform\Organization\Models\OrganizationTimeEntry::class)
                            @php
                                $props = $activity->properties ?? [];
                                $minutes = $props['minutes'] ?? 0;
                            @endphp
                            <p class="text-sm text-[var(--ui-muted)]">
                                Zeitbuchung:
                                <span class="font-medium text-[var(--ui-secondary)]">{{ intdiv($minutes, 60) }}:{{ str_pad($minutes % 60, 2, '0', STR_PAD_LEFT) }}h</span>
                                @if($props['note'] ?? null)
                                    — <span class="italic">"{{ \Illuminate\Support\Str::limit($props['note'], 60) }}"</span>
                                @endif
                            </p>
                        @else
                            <p class="text-sm text-[var(--ui-muted)]">Erstellt</p>
                        @endif
                    @elseif($activity->name === 'updated' && is_array($activity->properties))
                        <p class="text-sm text-[var(--ui-muted)]">
                            {{ collect($activity->properties)->keys()->map(fn($k) => ucfirst(str_replace('_', ' ', $k)))->implode(', ') }} geändert
                        </p>
                    @elseif($activity->name === 'deleted')
                        <p class="text-sm text-[var(--ui-muted)]">Gelöscht</p>
                    @endif

                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="text-[10px] text-[var(--ui-muted)]">
                            {{ $activity->user?->name ?? 'System' }} &middot; {{ $activity->created_at->diffForHumans() }}
                        </span>
                        @if($activity->activity_type === 'manual' && $activity->user_id === auth()->id())
                            <button
                                wire:click="deleteNote({{ $activity->id }})"
                                wire:confirm="Notiz wirklich löschen?"
                                class="text-[var(--ui-muted)] hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100"
                            >
                                @svg('heroicon-o-trash', 'w-3 h-3')
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-[var(--ui-muted)] text-center py-4">Keine Aktivitäten vorhanden.</p>
        @endforelse
    </div>

    {{-- Notiz-Eingabe (nur auf Entity-Seiten) --}}
    @if($entityId)
        <div class="flex-shrink-0 border-t border-[var(--ui-border)] p-3">
            <form wire:submit="addNote" class="flex items-end gap-2">
                <textarea
                    wire:model="newNote"
                    rows="2"
                    class="flex-1 text-sm rounded-lg border border-[var(--ui-border)] bg-[var(--ui-surface)] px-3 py-2 text-[var(--ui-secondary)] placeholder:text-[var(--ui-muted)] focus:border-[var(--ui-primary)] focus:ring-1 focus:ring-[var(--ui-primary)] resize-none"
                    placeholder="Notiz hinzufügen..."
                ></textarea>
                <button
                    type="submit"
                    class="flex-shrink-0 w-8 h-8 rounded-lg bg-[var(--ui-primary)] text-white flex items-center justify-center hover:opacity-90 transition-opacity disabled:opacity-50"
                    @if(!$newNote) disabled @endif
                >
                    @svg('heroicon-s-arrow-up', 'w-4 h-4')
                </button>
            </form>
        </div>
    @endif
</div>
