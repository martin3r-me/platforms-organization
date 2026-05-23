<div x-data="{ open: false }" class="px-3 py-2">
    {{-- Trigger Button --}}
    <button @click="open = !open; if(open) $wire.openSwitcher()"
        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg border transition text-sm
        text-[var(--ui-secondary)] border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)] bg-[var(--ui-surface)]"
        title="Perspektive wechseln">
        @svg('heroicon-o-eye', 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
        <span class="truncate flex-1 text-left text-xs font-medium">
            {{ $currentPerspectiveName ?? 'Perspektive' }}
        </span>
        @if($entitiesInViewCount > 0)
            <span class="text-[0.625rem] text-[var(--ui-muted)] tabular-nums">{{ $entitiesInViewCount }}</span>
        @endif
        <svg viewBox="0 0 20 20" fill="currentColor" class="w-3.5 h-3.5 text-[var(--ui-muted)] flex-shrink-0 transition" :class="open && 'rotate-180'">
            <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </button>

    {{-- Dropdown --}}
    <div x-show="open" x-cloak x-transition.opacity
        @click.outside="open = false"
        class="mt-1 w-full bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 shadow-lg max-h-[50vh] overflow-y-auto">
        <div class="p-1.5">
            <div class="space-y-0.5">
                @forelse($perspectives as $perspective)
                    <button type="button"
                        wire:click="switchPerspective({{ $perspective['id'] }})"
                        @click="open = false"
                        class="w-full flex items-center gap-2 px-2.5 py-2 rounded-md transition text-xs
                        {{ $perspective['is_active']
                            ? 'bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/40'
                            : 'hover:bg-[var(--ui-muted-5)]' }}">
                        <div class="flex-shrink-0">
                            @if($perspective['is_active'])
                                @svg('heroicon-s-check-circle', 'w-4 h-4 text-[var(--ui-primary)]')
                            @else
                                @svg('heroicon-o-eye', 'w-4 h-4 text-[var(--ui-muted)]')
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 text-left">
                            <div class="font-medium text-[var(--ui-secondary)] truncate">
                                {{ $perspective['name'] }}
                            </div>
                            @if($perspective['description'])
                                <div class="text-[0.625rem] text-[var(--ui-muted)] truncate leading-tight mt-0.5">
                                    {{ $perspective['description'] }}
                                </div>
                            @endif
                        </div>
                        @if($perspective['is_default'])
                            <span class="flex-shrink-0 text-[0.5rem] px-1.5 py-0.5 rounded-full bg-[var(--ui-muted-10)] text-[var(--ui-muted)] uppercase tracking-wide font-semibold">Standard</span>
                        @endif
                    </button>
                @empty
                    <div class="px-2 py-3 text-xs text-[var(--ui-muted)] text-center">
                        Keine Perspektiven vorhanden
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
