<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    {{-- ╔═══════════════════════════════════════════════════════════════════════╗ --}}
    {{-- ║  CYBERSYN 2.0 — Operations Room                                       ║ --}}
    {{-- ║  Hommage an Stafford Beer, Santiago de Chile, 1971–1973               ║ --}}
    {{-- ╚═══════════════════════════════════════════════════════════════════════╝ --}}

    <div class="flex-1 bg-zinc-900 text-zinc-100 overflow-y-auto w-full font-sans"
         style="background-image:
            radial-gradient(circle at 50% 0%, rgba(251,146,60,0.04) 0%, transparent 60%),
            radial-gradient(circle at 100% 100%, rgba(220,38,38,0.03) 0%, transparent 50%);">

        @php($p = $this->perspective)
        @php($totals = $this->totals)
        @php($levels = $this->levels)
        @php($s1Units = $this->s1Units)
        @php($availablePerspectives = $this->availablePerspectives)

        {{-- ── Header-Bar ─────────────────────────────────────────────────── --}}
        <header class="border-b border-zinc-800 px-8 py-5 flex items-center gap-6">
            {{-- Heptagonal Mark (7-Eck = die 7 fiberglass-Stühle in Santiago) --}}
            <div class="flex items-center gap-4 flex-shrink-0">
                <svg viewBox="0 0 100 100" class="w-10 h-10 text-orange-400" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polygon points="50,6 91,28 96,72 71,96 29,96 4,72 9,28" />
                    <polygon points="50,22 78,38 81,66 65,82 35,82 19,66 22,38" stroke-width="1" opacity="0.4" />
                    <circle cx="50" cy="56" r="3" fill="currentColor" stroke="none" />
                </svg>
                <div class="leading-tight">
                    <div class="text-[10px] tracking-[0.35em] text-zinc-500 uppercase">Operations Room</div>
                    <div class="text-lg font-medium tracking-[0.2em] uppercase text-zinc-100">Cybersyn 2.0</div>
                </div>
            </div>

            {{-- Perspektive-Wechsler --}}
            <div class="flex-1 flex items-center gap-4 min-w-0">
                <div class="text-[10px] tracking-[0.3em] text-zinc-500 uppercase whitespace-nowrap">Perspective</div>
                <div class="relative">
                    <select
                        wire:change="switchPerspective($event.target.value)"
                        class="appearance-none bg-zinc-800 border border-zinc-700 text-zinc-100 text-sm uppercase tracking-wider pl-3 pr-10 py-2 focus:border-orange-400 focus:ring-0 cursor-pointer">
                        @foreach($availablePerspectives as $persp)
                            <option value="{{ $persp['id'] }}" @selected($persp['id'] === $perspectiveEntityId)>{{ $persp['name'] }}</option>
                        @endforeach
                    </select>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none">▼</span>
                </div>
            </div>

            {{-- Totals als CRT-Display --}}
            <div class="flex items-center gap-8 border-l border-zinc-800 pl-8">
                <div class="text-right">
                    <div class="text-[10px] tracking-[0.3em] text-zinc-500 uppercase">Open</div>
                    <div class="text-2xl font-mono tabular-nums text-zinc-100 leading-none mt-1">{{ str_pad((string) $totals['open'], 2, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] tracking-[0.3em] text-zinc-500 uppercase">Escalated</div>
                    <div class="text-2xl font-mono tabular-nums leading-none mt-1 {{ $totals['escalated'] > 0 ? 'text-amber-400' : 'text-zinc-700' }}">{{ str_pad((string) $totals['escalated'], 2, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] tracking-[0.3em] uppercase {{ $totals['algedonic'] > 0 ? 'text-red-400' : 'text-zinc-500' }}">Algedonic</div>
                    <div class="text-2xl font-mono tabular-nums leading-none mt-1 {{ $totals['algedonic'] > 0 ? 'text-red-500 animate-pulse' : 'text-zinc-700' }}">{{ str_pad((string) $totals['algedonic'], 2, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] tracking-[0.3em] text-zinc-500 uppercase">Vacant</div>
                    <div class="text-2xl font-mono tabular-nums leading-none mt-1 {{ $totals['vacant_cells'] > 0 ? 'text-amber-400' : 'text-emerald-400' }}">{{ str_pad((string) $totals['vacant_cells'], 2, '0', STR_PAD_LEFT) }}</div>
                </div>
            </div>

            {{-- Datum / Uhrzeit --}}
            <div class="text-right border-l border-zinc-800 pl-6 leading-tight">
                <div class="text-[10px] tracking-[0.3em] text-zinc-500 uppercase tabular-nums">{{ now()->locale('en')->isoFormat('ddd · DD MMM YYYY') }}</div>
                <div class="text-xl font-mono tabular-nums text-zinc-100 mt-1">{{ now()->format('H:i') }}</div>
            </div>
        </header>

        {{-- ── VSM-Ebenen S5 → S1 ─────────────────────────────────────────── --}}
        <main class="divide-y divide-zinc-800">
            @foreach($levels as $level)
                <section class="px-8 py-7 transition hover:bg-zinc-800/30 group">
                    <div class="flex items-start gap-8">
                        {{-- Level-Code XXL --}}
                        <div class="w-24 flex-shrink-0 pt-1">
                            <div class="text-5xl font-light tracking-tighter tabular-nums leading-none {{ $level['vacant'] ? 'text-zinc-700' : 'text-orange-400' }}">
                                {{ $level['display'] }}
                            </div>
                        </div>

                        {{-- Label + Description + Assignees --}}
                        <div class="flex-1 min-w-0">
                            <div class="text-sm uppercase tracking-[0.25em] text-zinc-300 font-medium">{{ $level['label'] }}</div>
                            <div class="text-xs text-zinc-500 mt-1.5 max-w-2xl leading-relaxed">{{ $level['description'] }}</div>

                            <div class="mt-4">
                                @if($level['vacant'])
                                    <div class="inline-flex items-center gap-2 text-amber-500 text-xs uppercase tracking-[0.2em] border border-amber-500/30 px-2.5 py-1">
                                        <span class="w-1.5 h-1.5 bg-amber-500 animate-pulse"></span>
                                        Vakant
                                    </div>
                                @else
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($level['assignees'] as $name)
                                            <span class="px-2.5 py-1 border border-zinc-700 text-zinc-200 text-xs tracking-wide font-medium bg-zinc-900/40">{{ $name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Counts (Right-aligned, CRT-Display-Stil) --}}
                        <div class="flex items-start gap-10 flex-shrink-0 pt-1">
                            <div class="text-right">
                                <div class="text-[10px] tracking-[0.3em] text-zinc-500 uppercase">Open</div>
                                <div class="text-4xl font-mono tabular-nums leading-none mt-1 {{ $level['open'] > 0 ? 'text-zinc-100' : 'text-zinc-700' }}">{{ str_pad((string) $level['open'], 2, '0', STR_PAD_LEFT) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] tracking-[0.3em] text-zinc-500 uppercase">Escalated</div>
                                <div class="text-4xl font-mono tabular-nums leading-none mt-1 {{ $level['escalated'] > 0 ? 'text-amber-400' : 'text-zinc-700' }}">{{ str_pad((string) $level['escalated'], 2, '0', STR_PAD_LEFT) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-[10px] tracking-[0.3em] uppercase {{ $level['algedonic'] > 0 ? 'text-red-400' : 'text-zinc-500' }}">Algedonic</div>
                                <div class="text-4xl font-mono tabular-nums leading-none mt-1 {{ $level['algedonic'] > 0 ? 'text-red-500 animate-pulse' : 'text-zinc-700' }}">{{ str_pad((string) $level['algedonic'], 2, '0', STR_PAD_LEFT) }}</div>
                            </div>
                        </div>
                    </div>
                </section>
            @endforeach
        </main>

        {{-- ── S1-Strip · Operative Einheiten ──────────────────────────────── --}}
        @if(! empty($s1Units))
            <section class="border-t border-zinc-800 px-8 py-7 bg-zinc-950/40">
                <div class="flex items-baseline justify-between mb-4">
                    <h2 class="text-[10px] tracking-[0.35em] text-zinc-500 uppercase">S1 · Operative Einheiten</h2>
                    <span class="text-xs font-mono tabular-nums text-zinc-600">{{ count($s1Units) }} aktiv</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2">
                    @foreach($s1Units as $unit)
                        <a href="{{ route('organization.entities.show', $unit['id']) }}"
                           class="block border border-zinc-800 hover:border-orange-400 px-3 py-2.5 transition group bg-zinc-900/60">
                            <div class="text-sm text-zinc-200 truncate group-hover:text-orange-300 transition">{{ $unit['name'] }}</div>
                            <div class="mt-1.5 flex items-center justify-between">
                                <span class="text-[9px] text-zinc-600 uppercase tracking-[0.2em]">Open</span>
                                <span class="text-sm font-mono tabular-nums {{ $unit['open'] > 0 ? 'text-amber-400' : 'text-zinc-700' }}">{{ str_pad((string) $unit['open'], 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ── Cybersyn Footer-Strip ──────────────────────────────────────── --}}
        <footer class="border-t border-zinc-800 px-8 py-4 flex items-center justify-between text-[10px] tracking-[0.35em] text-zinc-600 uppercase">
            <div class="flex items-center gap-4">
                <span>Stafford Beer · Santiago de Chile · 1972</span>
                <span class="text-zinc-700">—</span>
                <span class="text-zinc-500">{{ $p?->name ?? '—' }} · 2026</span>
            </div>
            <div class="flex items-center gap-3 font-mono">
                <span class="w-1.5 h-1.5 bg-emerald-500 animate-pulse rounded-full"></span>
                <span class="text-zinc-500">LIVE</span>
                <span class="text-zinc-700">·</span>
                <span class="text-zinc-600">PERSP/{{ str_pad((string) ($perspectiveEntityId ?? 0), 4, '0', STR_PAD_LEFT) }}</span>
            </div>
        </footer>
    </div>
</x-ui-page>
