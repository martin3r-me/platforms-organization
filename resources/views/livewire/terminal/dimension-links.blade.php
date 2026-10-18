<div class="flex flex-col h-full text-[var(--t-text)]">
    <div class="px-3 py-2 border-b border-white/10 text-[11px] text-[var(--t-text-muted)]">
        @if($this->hasContext())
            Verknüpfungen von
            <span class="text-[var(--t-text)] font-medium">{{ $contextSubject ?? ('#'.$contextId) }}</span>
            @unless($this->canWrite)
                <span class="ml-1 text-[9px] px-1 py-0.5 rounded bg-white/10 align-middle">nur Lesen</span>
            @endunless
        @else
            Kein Objekt im Kontext.
        @endif
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-3">
        @forelse($this->groups as $group)
            <div wire:key="dim-group-{{ $group['key'] }}">
                <div class="text-[10px] uppercase tracking-wide text-[var(--t-text-muted)] mb-1">
                    {{ $group['label'] }}
                    <span class="opacity-50">· {{ $group['mode'] }}</span>
                </div>
                <ul class="space-y-1">
                    @foreach($group['items'] as $item)
                        <li wire:key="dim-item-{{ $group['key'] }}-{{ $item['id'] }}"
                            class="flex items-center justify-between gap-2 px-2 py-1 rounded bg-white/5 text-[12px]">
                            <span class="truncate">
                                {{ $item['name'] ?? ('#'.$item['id']) }}
                                @if(!empty($item['is_primary']))
                                    <span class="ml-1 text-[9px] px-1 py-0.5 rounded bg-white/10 align-middle">primary</span>
                                @endif
                                @if(!empty($item['percentage']))
                                    <span class="ml-1 opacity-60">{{ $item['percentage'] }}%</span>
                                @endif
                            </span>
                            @if($this->canWrite)
                                <button
                                    type="button"
                                    wire:click="unlink('{{ $group['key'] }}', {{ (int) $item['id'] }})"
                                    wire:confirm="Diese Verknüpfung entfernen?"
                                    class="shrink-0 text-[var(--t-text-muted)] hover:text-red-400 transition"
                                    title="Verknüpfung entfernen"
                                >
                                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                         viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="text-[12px] text-[var(--t-text-muted)] py-6 text-center">
                @if($this->hasContext())
                    Keine Dimension-Verknüpfungen für dieses Objekt.
                @else
                    —
                @endif
            </div>
        @endforelse

        @if($this->hasContext() && $this->canWrite)
            <div class="pt-3 mt-1 border-t border-white/10">
                <div class="text-[10px] uppercase tracking-wide text-[var(--t-text-muted)] mb-1.5">
                    Organisationseinheit verknüpfen
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="suchen…"
                    class="w-full px-2 py-1 rounded bg-white/5 text-[12px] text-[var(--t-text)] placeholder:text-[var(--t-text-muted)] border border-white/10 focus:outline-none focus:border-white/20"
                >
                <ul class="mt-1.5 space-y-1">
                    @forelse($this->candidates as $cand)
                        <li wire:key="cand-{{ $cand['id'] }}"
                            class="flex items-center justify-between gap-2 px-2 py-1 rounded hover:bg-white/5 text-[12px]">
                            <span class="truncate">
                                {{ $cand['name'] }}
                                @if($cand['code'])
                                    <span class="ml-1 opacity-50">{{ $cand['code'] }}</span>
                                @endif
                            </span>
                            <button
                                type="button"
                                wire:click="addLink({{ $cand['id'] }})"
                                class="shrink-0 text-[var(--t-text-muted)] hover:text-emerald-400 transition"
                                title="Verknüpfen"
                            >
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                                </svg>
                            </button>
                        </li>
                    @empty
                        <li class="text-[11px] text-[var(--t-text-muted)] px-2 py-1">
                            @if(trim($search) !== '')
                                Keine passende Einheit mit Schreibrecht.
                            @else
                                Keine verfügbaren Einheiten mit Schreibrecht.
                            @endif
                        </li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>
</div>
