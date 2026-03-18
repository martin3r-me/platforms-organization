<div class="space-y-2">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] uppercase tracking-wider flex items-center gap-2">
            @svg($this->getIcon(), 'w-4 h-4')
            {{ $this->getLabel() }}
        </h3>
        @if($this->getMode() === 'multi_percent' && count($linkedItems) > 0)
            @php $sum = $this->getPercentSum(); @endphp
            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ abs($sum - 100) < 0.01 ? 'bg-green-100 text-green-700' : ($sum > 100 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                {{ number_format($sum, 0) }}%
            </span>
        @endif
    </div>

    {{-- Linked Items --}}
    @if(count($linkedItems) > 0)
        <div class="space-y-1">
            @foreach($linkedItems as $item)
                <div class="group flex items-center gap-2 py-1.5 px-3 bg-[var(--ui-muted-5)] rounded text-sm">
                    {{-- Primary Star (multi + multi_percent) --}}
                    @if($this->getMode() !== 'single')
                        <button
                            wire:click="togglePrimary({{ $item['id'] }})"
                            class="flex-shrink-0 transition {{ $item['is_primary'] ? 'text-amber-500' : 'text-[var(--ui-muted)]/30 hover:text-amber-400' }}"
                            title="{{ $item['is_primary'] ? 'Primär' : 'Als primär setzen' }}"
                        >
                            @svg($item['is_primary'] ? 'heroicon-s-star' : 'heroicon-o-star', 'w-3.5 h-3.5')
                        </button>
                    @endif

                    {{-- Name --}}
                    <span class="flex-1 min-w-0 truncate">
                        @if($item['code'])
                            <span class="text-[var(--ui-muted)]">{{ $item['code'] }}</span>
                            <span class="mx-1 text-[var(--ui-muted)]">&middot;</span>
                        @endif
                        <span class="font-medium text-[var(--ui-secondary)]">{{ $item['name'] }}</span>
                    </span>

                    {{-- Prozent-Input (nur multi_percent) --}}
                    @if($this->getMode() === 'multi_percent')
                        <div class="flex items-center gap-1 flex-shrink-0">
                            <input
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                value="{{ $percentages[$item['id']] ?? '' }}"
                                wire:change="savePercentage({{ $item['id'] }}, $event.target.value)"
                                class="w-16 text-xs text-right rounded border-gray-300 py-1 px-1.5 focus:border-[var(--ui-primary)] focus:ring-1 focus:ring-[var(--ui-primary)]"
                                placeholder="%"
                            />
                            <span class="text-xs text-[var(--ui-muted)]">%</span>
                        </div>
                    @endif

                    {{-- Detach --}}
                    <button
                        wire:click="detach({{ $item['id'] }})"
                        class="flex-shrink-0 opacity-0 group-hover:opacity-100 text-[var(--ui-muted)] hover:text-red-500 transition"
                        title="Entfernen"
                    >
                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-xs text-[var(--ui-muted)] py-1">Keine {{ $this->getLabel() }} zugeordnet.</p>
    @endif

    {{-- Search + Attach (versteckt im single-Mode wenn schon belegt, außer bei aktiver Suche) --}}
    @if($this->getMode() !== 'single' || count($linkedItems) === 0 || $search !== '')
        <div class="relative" x-data="{ focused: false }" x-on:click.away="focused = false">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                x-on:focus="focused = true"
                placeholder="{{ $this->getMode() === 'single' && count($linkedItems) > 0 ? 'Ersetzen...' : $this->getLabel() . ' suchen...' }}"
                class="w-full text-xs rounded-md border-gray-300 shadow-sm focus:border-[var(--ui-primary)] focus:ring-1 focus:ring-[var(--ui-primary)] pl-7 py-1.5"
            />
            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                @svg('heroicon-o-magnifying-glass', 'w-3.5 h-3.5 text-gray-400')
            </div>

            {{-- Available Items Dropdown --}}
            @if(count($availableItems) > 0)
                <div
                    x-show="focused || '{{ $search }}' !== ''"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="absolute z-10 mt-1 w-full bg-white rounded-md shadow-lg border border-[var(--ui-border)] max-h-40 overflow-y-auto"
                >
                    @foreach($availableItems as $item)
                        <button
                            wire:click="attach({{ $item['id'] }})"
                            class="flex items-center justify-between w-full py-1.5 px-3 text-xs text-left hover:bg-[var(--ui-primary-5)] transition"
                        >
                            <span>
                                @if($item['code'])
                                    <span class="text-[var(--ui-muted)]">{{ $item['code'] }}</span>
                                    <span class="mx-1 text-[var(--ui-muted)]">&middot;</span>
                                @endif
                                <span class="text-[var(--ui-secondary)]">{{ $item['name'] }}</span>
                            </span>
                            @svg('heroicon-o-plus', 'w-3.5 h-3.5 text-[var(--ui-primary)]')
                        </button>
                    @endforeach
                </div>
            @elseif($search !== '')
                <div
                    x-show="focused"
                    class="absolute z-10 mt-1 w-full bg-white rounded-md shadow-lg border border-[var(--ui-border)] p-3"
                >
                    <p class="text-xs text-[var(--ui-muted)]">Keine Ergebnisse für "{{ $search }}"</p>
                </div>
            @endif
        </div>
    @elseif($this->getMode() === 'single' && count($linkedItems) > 0)
        {{-- Single-Mode: kleiner Wechseln-Button --}}
        <button
            wire:click="$set('search', ' ')"
            class="text-xs text-[var(--ui-primary)] hover:underline"
        >
            Wechseln...
        </button>
    @endif
</div>
