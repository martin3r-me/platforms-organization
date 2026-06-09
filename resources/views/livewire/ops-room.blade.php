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

        @include('organization::livewire.partials.ops-room-header', ['breadcrumb' => null])

        {{-- ── VSM-Ebenen S5 → S1 (flex-1, jede Ebene fluid, klickbar) ───── --}}
        <main class="flex-1 min-h-0 flex flex-col divide-y divide-zinc-800">
            @foreach($levels as $level)
                <a href="{{ route('organization.ops-room.level', ['perspective' => $perspectiveEntityId, 'vsm' => $level['code']]) }}"
                   class="flex-1 min-h-0 px-6 py-2 flex items-center gap-6 transition hover:bg-zinc-800/40 group cursor-pointer"
                   title="Ebene {{ $level['display'] }} im Detail">
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
                                @foreach($level['assignees'] as $a)
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 border text-[11px] tracking-wide font-medium whitespace-nowrap
                                        {{ $a['is_agent']
                                            ? 'border-zinc-800 bg-zinc-900/40 text-zinc-500'
                                            : 'border-zinc-700 bg-zinc-900/40 text-zinc-200' }}"
                                        title="{{ $a['is_agent'] ? 'Agent — erfüllt Funktion, traegt aber keine Verantwortung' : ($a['role'] ?? 'Person') }}">
                                        @if($a['is_agent'])
                                            <span class="w-1 h-1 bg-zinc-600"></span>
                                        @endif
                                        <span>{{ $a['name'] }}</span>
                                        @if($a['role'])
                                            <span class="text-[9px] tracking-[0.2em] uppercase text-zinc-500">· {{ $a['role'] }}</span>
                                        @endif
                                    </span>
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
                        <span class="text-zinc-700 group-hover:text-orange-400 transition text-xl font-light">›</span>
                    </div>
                </a>
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

        @include('organization::livewire.partials.ops-room-footer')
    </div>
</x-ui-page>
