<div x-data="{ perspectiveOpen: false }"
     @open-perspective-switcher.window="perspectiveOpen = true; $wire.openSwitcher()"
     class="relative">

    <button x-ref="perspectiveTrigger" @click="perspectiveOpen = !perspectiveOpen; if(perspectiveOpen) $wire.openSwitcher()"
        class="inline-flex items-center gap-1.5 px-2 py-1 h-7 rounded-md border transition text-xs
        text-[var(--ui-secondary)] border-[var(--ui-border)]/60 hover:bg-[var(--ui-muted-5)]"
        title="Perspektive wechseln">
        @svg('heroicon-o-eye', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
        <span class="truncate max-w-[10rem]">
            {{ $currentPerspectiveName ?? 'Perspektive' }}
        </span>
        @if($entitiesInViewCount > 0)
            <span class="text-[0.5rem] opacity-50 leading-none">({{ $entitiesInViewCount }})</span>
        @endif
        <svg viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3 text-[var(--ui-muted)]">
            <path d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" fill-rule="evenodd" />
        </svg>
    </button>

    <template x-teleport="body">
        <div x-show="perspectiveOpen" x-cloak x-transition
            @click.outside="perspectiveOpen = false"
            x-effect="if(perspectiveOpen) { $nextTick(() => { const r = $refs.perspectiveTrigger.getBoundingClientRect(); $el.style.top = (r.bottom + 8) + 'px'; $el.style.right = (window.innerWidth - r.right) + 'px'; }) }"
            class="fixed z-[99] w-72 bg-[var(--ui-surface)] rounded-lg border border-[var(--ui-border)]/60 shadow-lg max-h-[60vh] overflow-y-auto">
            <div class="p-2">
                <h3 class="text-[0.625rem] font-semibold text-[var(--ui-muted)] mb-2 px-2 uppercase tracking-wider">Perspektive</h3>
                <div class="space-y-0.5">
                    @forelse($perspectives as $perspective)
                        <button type="button"
                            wire:click="switchPerspective({{ $perspective['id'] }})"
                            class="w-full group flex items-center gap-2 px-2 py-1.5 rounded-md transition text-xs
                            {{ $perspective['is_active']
                                ? 'bg-[var(--ui-primary-5)] border border-[var(--ui-primary)]/60'
                                : 'hover:bg-[var(--ui-muted-5)]' }}">
                            <div class="flex-shrink-0">
                                @if($perspective['is_active'])
                                    @svg('heroicon-o-eye', 'w-4 h-4 text-[var(--ui-primary)]')
                                @else
                                    @svg('heroicon-o-eye-slash', 'w-4 h-4 text-[var(--ui-muted)]')
                                @endif
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <div class="font-medium text-[var(--ui-secondary)] truncate text-xs">
                                    {{ $perspective['name'] }}
                                </div>
                                @if($perspective['description'])
                                    <div class="text-[0.625rem] text-[var(--ui-muted)] truncate">
                                        {{ $perspective['description'] }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex-shrink-0 flex items-center gap-1">
                                @if($perspective['is_default'])
                                    <span class="text-[0.5rem] px-1 py-0.5 rounded bg-[var(--ui-muted-10)] text-[var(--ui-muted)]">Standard</span>
                                @endif
                                @if($perspective['is_active'])
                                    @svg('heroicon-o-check', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="px-2 py-3 text-xs text-[var(--ui-muted)] text-center">
                            Keine Perspektiven vorhanden
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </template>
</div>
