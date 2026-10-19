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

    <div class="flex-1 min-h-0 overflow-y-auto p-3">
      <div class="max-w-2xl mx-auto w-full space-y-4">
        @forelse($this->groups as $group)
            <div wire:key="dim-group-{{ $group['key'] }}">
                <div class="flex items-center justify-between mb-1">
                    <div class="text-[10px] uppercase tracking-wide text-[var(--t-text-muted)]">
                        {{ $group['label'] }}
                    </div>
                    @if($group['mode'] === 'multi_percent')
                        <div class="text-[10px] {{ $group['percent_sum'] > 100 ? 'text-red-400' : 'text-[var(--t-text-muted)]' }}">
                            Σ {{ rtrim(rtrim(number_format($group['percent_sum'], 2), '0'), '.') }}% / 100%
                        </div>
                    @endif
                </div>
                @if($group['mode'] === 'multi_percent')
                    <div class="h-1 rounded bg-white/10 overflow-hidden mb-1.5">
                        <div class="h-full {{ $group['percent_sum'] > 100 ? 'bg-red-400' : 'bg-emerald-400/70' }}"
                             style="width: {{ min(max($group['percent_sum'], 0), 100) }}%"></div>
                    </div>
                @endif

                <ul class="space-y-1">
                    @foreach($group['items'] as $item)
                        @php($dkey = $group['key'].':'.$item['id'])
                        <li wire:key="dim-item-{{ $group['key'] }}-{{ $item['id'] }}"
                            class="flex flex-col gap-1 px-2 py-1.5 rounded bg-white/5 text-[12px]">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate">
                                        {{ $item['name'] ?? ('#'.$item['id']) }}
                                        @unless($this->canWrite)
                                            @if(!empty($item['is_primary']))
                                                <span class="ml-1 text-[9px] px-1 py-0.5 rounded bg-white/10 align-middle">primär</span>
                                            @endif
                                            @if(!empty($item['percentage']))
                                                <span class="ml-1 opacity-60">{{ $item['percentage'] }}%</span>
                                            @endif
                                        @endunless
                                    </div>
                                    @if(!empty($item['path']))
                                        <div class="truncate text-[10px] text-[var(--t-text-muted)] opacity-70">
                                            {{ implode(' › ', $item['path']) }}
                                        </div>
                                    @endif
                                    @if(!$this->canWrite && (!empty($item['start_date']) || !empty($item['end_date'])))
                                        <div class="text-[10px] text-[var(--t-text-muted)] opacity-70">
                                            {{ $item['start_date'] ?? '…' }} – {{ $item['end_date'] ?? '…' }}
                                        </div>
                                    @endif
                                </div>

                                @if($this->canWrite)
                                    <div class="shrink-0 flex items-center gap-1.5">
                                        @if($group['mode'] === 'multi_percent')
                                            <div class="flex items-center">
                                                <input
                                                    type="number" min="0" max="100" step="0.01"
                                                    value="{{ $item['percentage'] }}"
                                                    x-on:change="$wire.setPercentage('{{ $group['key'] }}', {{ (int) $item['id'] }}, $event.target.value)"
                                                    class="w-12 px-1 py-0.5 text-right rounded bg-white/5 border border-white/10 text-[11px] focus:outline-none focus:border-white/20"
                                                    title="Anteil in %"
                                                >
                                                <span class="ml-0.5 text-[10px] opacity-50">%</span>
                                            </div>
                                        @endif

                                        @if(in_array($group['mode'], ['multi', 'multi_percent']))
                                            <button type="button"
                                                wire:click="togglePrimary('{{ $group['key'] }}', {{ (int) $item['id'] }})"
                                                class="transition {{ !empty($item['is_primary']) ? 'text-amber-400' : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)]' }}"
                                                title="{{ !empty($item['is_primary']) ? 'Primär (klicken zum Aufheben)' : 'Als primär markieren' }}">
                                                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg"
                                                     fill="{{ !empty($item['is_primary']) ? 'currentColor' : 'none' }}"
                                                     viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 0 1 1.04 0l2.12 4.3a.56.56 0 0 0 .42.3l4.74.69c.46.07.64.63.31.95l-3.43 3.34a.56.56 0 0 0-.16.5l.81 4.72c.08.46-.4.81-.81.59l-4.24-2.23a.56.56 0 0 0-.52 0l-4.24 2.23c-.41.22-.89-.13-.81-.59l.81-4.72a.56.56 0 0 0-.16-.5L4.24 10.04c-.33-.32-.15-.88.31-.95l4.74-.69a.56.56 0 0 0 .42-.3l2.12-4.3Z"/>
                                                </svg>
                                            </button>
                                        @endif

                                        <button type="button"
                                            wire:click="toggleDates('{{ $group['key'] }}', {{ (int) $item['id'] }}, '{{ $item['start_date'] }}', '{{ $item['end_date'] }}')"
                                            class="transition {{ (!empty($item['start_date']) || !empty($item['end_date'])) ? 'text-sky-400' : 'text-[var(--t-text-muted)] hover:text-[var(--t-text)]' }}"
                                            title="Zeitraum">
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                                            </svg>
                                        </button>

                                        <button type="button"
                                            wire:click="unlink('{{ $group['key'] }}', {{ (int) $item['id'] }})"
                                            wire:confirm="Diese Verknüpfung entfernen?"
                                            class="text-[var(--t-text-muted)] hover:text-red-400 transition"
                                            title="Verknüpfung entfernen">
                                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                 viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                @endif
                            </div>

                            @if($this->canWrite && $datesOpen === $dkey)
                                <div class="flex items-center gap-1.5 text-[11px] pt-1">
                                    <input type="date" wire:model="editStart"
                                        class="px-1 py-0.5 rounded bg-white/5 border border-white/10 focus:outline-none focus:border-white/20">
                                    <span class="opacity-50">–</span>
                                    <input type="date" wire:model="editEnd"
                                        class="px-1 py-0.5 rounded bg-white/5 border border-white/10 focus:outline-none focus:border-white/20">
                                    <button type="button" wire:click="saveDates('{{ $group['key'] }}', {{ (int) $item['id'] }})"
                                        class="ml-1 px-2 py-0.5 rounded bg-white/10 hover:bg-white/20 transition">
                                        Speichern
                                    </button>
                                </div>
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
                            class="flex items-start justify-between gap-2 px-2 py-1 rounded hover:bg-white/5 text-[12px]">
                            <div class="min-w-0 flex-1">
                                <div class="truncate">
                                    {{ $cand['name'] }}
                                    @if($cand['code'])
                                        <span class="ml-1 opacity-50">{{ $cand['code'] }}</span>
                                    @endif
                                </div>
                                @if(!empty($cand['path']))
                                    <div class="truncate text-[10px] text-[var(--t-text-muted)] opacity-70">
                                        {{ implode(' › ', $cand['path']) }}
                                    </div>
                                @endif
                            </div>
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
</div>
