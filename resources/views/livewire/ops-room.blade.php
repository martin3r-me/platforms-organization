<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    {{-- ╔═══════════════════════════════════════════════════════════════════════╗ --}}
    {{-- ║  CYBERSYN 2.0 — Operations Room                                       ║ --}}
    {{-- ║  Hommage an Stafford Beer, Santiago de Chile, 1971–1973               ║ --}}
    {{-- ║  Fit-to-Viewport: alles auf einen Blick, kein Scrollen.               ║ --}}
    {{-- ╚═══════════════════════════════════════════════════════════════════════╝ --}}

    <div class="flex-1 bg-zinc-900 text-zinc-100 w-full font-sans flex flex-col overflow-hidden min-h-0"
         style="background-image:
            radial-gradient(circle at 50% 0%, rgba(251,146,60,0.04) 0%, transparent 60%),
            radial-gradient(circle at 100% 100%, rgba(220,38,38,0.03) 0%, transparent 50%);">

        @php($p = $this->perspective)
        @php($totals = $this->totals)
        @php($levels = $this->levels)
        @php($s1Units = $this->s1Units)
        @php($availablePerspectives = $this->availablePerspectives)

        {{-- ── Header-Bar (compact) ───────────────────────────────────────── --}}
        <header class="flex-shrink-0 border-b border-zinc-800 px-6 py-3 flex items-center gap-6">
            {{-- Heptagonal Mark (7 fiberglass-Stühle) --}}
            <div class="flex items-center gap-3 flex-shrink-0">
                <svg viewBox="0 0 100 100" class="w-8 h-8 text-orange-400" fill="none" stroke="currentColor" stroke-width="3">
                    <polygon points="50,6 91,28 96,72 71,96 29,96 4,72 9,28" />
                    <polygon points="50,22 78,38 81,66 65,82 35,82 19,66 22,38" stroke-width="1.2" opacity="0.4" />
                    <circle cx="50" cy="56" r="2.5" fill="currentColor" stroke="none" />
                </svg>
                <div class="leading-tight">
                    <div class="text-[9px] tracking-[0.35em] text-zinc-500 uppercase">Operations Room</div>
                    <div class="text-sm font-medium tracking-[0.25em] uppercase text-zinc-100">Cybersyn 2.0</div>
                </div>
            </div>

            {{-- Perspektive-Wechsler --}}
            <div class="flex items-center gap-3 min-w-0">
                <div class="text-[9px] tracking-[0.3em] text-zinc-500 uppercase whitespace-nowrap">Perspective</div>
                <div class="relative">
                    <select
                        wire:change="switchPerspective($event.target.value)"
                        class="appearance-none bg-zinc-800 border border-zinc-700 text-zinc-100 text-xs uppercase tracking-wider pl-3 pr-8 py-1.5 focus:border-orange-400 focus:ring-0 cursor-pointer">
                        @foreach($availablePerspectives as $persp)
                            <option value="{{ $persp['id'] }}" @selected($persp['id'] === $perspectiveEntityId)>{{ $persp['name'] }}</option>
                        @endforeach
                    </select>
                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none text-[10px]">▼</span>
                </div>
            </div>

            {{-- Totals (kompakt) --}}
            <div class="flex-1 flex items-center justify-end gap-6 border-l border-zinc-800 pl-6">
                <div class="text-right leading-tight">
                    <div class="text-[9px] tracking-[0.3em] text-zinc-500 uppercase">Open</div>
                    <div class="text-lg font-mono tabular-nums text-zinc-100">{{ str_pad((string) $totals['open'], 2, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="text-right leading-tight">
                    <div class="text-[9px] tracking-[0.3em] text-zinc-500 uppercase">Escalated</div>
                    <div class="text-lg font-mono tabular-nums {{ $totals['escalated'] > 0 ? 'text-amber-400' : 'text-zinc-700' }}">{{ str_pad((string) $totals['escalated'], 2, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="text-right leading-tight">
                    <div class="text-[9px] tracking-[0.3em] uppercase {{ $totals['algedonic'] > 0 ? 'text-red-400' : 'text-zinc-500' }}">Algedonic</div>
                    <div class="text-lg font-mono tabular-nums {{ $totals['algedonic'] > 0 ? 'text-red-500 animate-pulse' : 'text-zinc-700' }}">{{ str_pad((string) $totals['algedonic'], 2, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="text-right leading-tight">
                    <div class="text-[9px] tracking-[0.3em] text-zinc-500 uppercase">Vacant</div>
                    <div class="text-lg font-mono tabular-nums {{ $totals['vacant_cells'] > 0 ? 'text-amber-400' : 'text-emerald-400' }}">{{ str_pad((string) $totals['vacant_cells'], 2, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="text-right leading-tight border-l border-zinc-800 pl-6 tabular-nums">
                    <div class="text-[9px] tracking-[0.3em] text-zinc-500 uppercase">{{ now()->locale('en')->isoFormat('ddd · DD MMM YYYY') }}</div>
                    <div class="text-lg font-mono text-zinc-100">{{ now()->format('H:i') }}</div>
                </div>
            </div>
        </header>

        {{-- ── VSM-Ebenen S5 → S1 (flex-1, jede Ebene fluid) ───────────────── --}}
        <main class="flex-1 min-h-0 flex flex-col divide-y divide-zinc-800">
            @foreach($levels as $level)
                <section class="flex-1 min-h-0 px-6 py-2 flex items-center gap-6 transition hover:bg-zinc-800/30 group">
                    {{-- Level-Code XXL --}}
                    <div class="w-16 flex-shrink-0">
                        <div class="text-3xl font-light tracking-tighter tabular-nums leading-none {{ $level['vacant'] ? 'text-zinc-700' : 'text-orange-400' }}">
                            {{ $level['display'] }}
                        </div>
                    </div>

                    {{-- Label + Assignees inline --}}
                    <div class="flex-1 min-w-0 flex items-center gap-4">
                        <div class="flex-shrink-0">
                            <div class="text-xs uppercase tracking-[0.25em] text-zinc-300 font-medium">{{ $level['label'] }}</div>
                        </div>

                        <div class="flex-1 min-w-0 flex flex-wrap items-center gap-1.5">
                            @if($level['vacant'])
                                <span class="inline-flex items-center gap-1.5 text-amber-500 text-[10px] uppercase tracking-[0.2em] border border-amber-500/30 px-2 py-0.5">
                                    <span class="w-1 h-1 bg-amber-500 animate-pulse"></span>
                                    Vakant
                                </span>
                            @else
                                @foreach($level['assignees'] as $name)
                                    <span class="px-2 py-0.5 border border-zinc-700 text-zinc-200 text-[11px] tracking-wide font-medium bg-zinc-900/40 whitespace-nowrap">{{ $name }}</span>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    {{-- Counts (kompakt, inline) --}}
                    <div class="flex items-center gap-6 flex-shrink-0">
                        <div class="text-right leading-tight">
                            <div class="text-[9px] tracking-[0.3em] text-zinc-500 uppercase">Open</div>
                            <div class="text-2xl font-mono tabular-nums {{ $level['open'] > 0 ? 'text-zinc-100' : 'text-zinc-700' }}">{{ str_pad((string) $level['open'], 2, '0', STR_PAD_LEFT) }}</div>
                        </div>
                        <div class="text-right leading-tight">
                            <div class="text-[9px] tracking-[0.3em] text-zinc-500 uppercase">Esc</div>
                            <div class="text-2xl font-mono tabular-nums {{ $level['escalated'] > 0 ? 'text-amber-400' : 'text-zinc-700' }}">{{ str_pad((string) $level['escalated'], 2, '0', STR_PAD_LEFT) }}</div>
                        </div>
                        <div class="text-right leading-tight">
                            <div class="text-[9px] tracking-[0.3em] uppercase {{ $level['algedonic'] > 0 ? 'text-red-400' : 'text-zinc-500' }}">Alg</div>
                            <div class="text-2xl font-mono tabular-nums {{ $level['algedonic'] > 0 ? 'text-red-500 animate-pulse' : 'text-zinc-700' }}">{{ str_pad((string) $level['algedonic'], 2, '0', STR_PAD_LEFT) }}</div>
                        </div>
                    </div>
                </section>
            @endforeach
        </main>

        {{-- ── S1-Strip · Operative Einheiten (max ~15% Viewport) ─────────── --}}
        @if(! empty($s1Units))
            <section class="flex-shrink-0 border-t border-zinc-800 px-6 py-2.5 bg-zinc-950/40 overflow-hidden">
                <div class="flex items-baseline justify-between mb-1.5">
                    <h2 class="text-[9px] tracking-[0.35em] text-zinc-500 uppercase">S1 · Operative Einheiten</h2>
                    <span class="text-[10px] font-mono tabular-nums text-zinc-600">{{ count($s1Units) }} aktiv</span>
                </div>
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-1.5">
                    @foreach($s1Units as $unit)
                        <a href="{{ route('organization.entities.show', $unit['id']) }}"
                           class="block border border-zinc-800 hover:border-orange-400 px-2 py-1 transition group bg-zinc-900/60 min-w-0">
                            <div class="text-[11px] text-zinc-200 truncate group-hover:text-orange-300 transition">{{ $unit['name'] }}</div>
                            <div class="flex items-center justify-between mt-0.5">
                                <span class="text-[9px] text-zinc-600 uppercase tracking-[0.2em]">Open</span>
                                <span class="text-[11px] font-mono tabular-nums {{ $unit['open'] > 0 ? 'text-amber-400' : 'text-zinc-700' }}">{{ str_pad((string) $unit['open'], 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ── Cybersyn Footer-Strip ──────────────────────────────────────── --}}
        <footer class="flex-shrink-0 border-t border-zinc-800 px-6 py-1.5 flex items-center justify-between text-[9px] tracking-[0.35em] text-zinc-600 uppercase">
            <div class="flex items-center gap-3">
                <span>Stafford Beer · Santiago de Chile · 1972</span>
                <span class="text-zinc-700">—</span>
                <span class="text-zinc-500">{{ $p?->name ?? '—' }} · 2026</span>
            </div>
            <div class="flex items-center gap-2 font-mono">
                <span class="w-1.5 h-1.5 bg-emerald-500 animate-pulse rounded-full"></span>
                <span class="text-zinc-500">Live</span>
                <span class="text-zinc-700">·</span>
                <span class="text-zinc-600">Persp/{{ str_pad((string) ($perspectiveEntityId ?? 0), 4, '0', STR_PAD_LEFT) }}</span>
            </div>
        </footer>
    </div>
</x-ui-page>
