<div class="flex flex-col h-full text-[var(--t-text)]">
    <div class="px-3 py-2 border-b border-white/10 text-[11px] text-[var(--t-text-muted)]">
        @if($this->hasContext())
            Verknüpfungen von
            <span class="text-[var(--t-text)] font-medium">{{ $contextSubject ?? ('#'.$contextId) }}</span>
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
    </div>
</div>
