<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    {{-- ╔═══════════════════════════════════════════════════════════════════════╗ --}}
    {{-- ║  CYBERSYN 2.0 — Level Detail (S1..S5)                                 ║ --}}
    {{-- ╚═══════════════════════════════════════════════════════════════════════╝ --}}

    <div class="flex-1 bg-zinc-900 text-zinc-100 w-full font-sans flex flex-col overflow-hidden min-h-0"
         style="background-image:
            radial-gradient(circle at 50% 0%, rgba(251,146,60,0.04) 0%, transparent 60%),
            radial-gradient(circle at 100% 100%, rgba(220,38,38,0.03) 0%, transparent 50%);"
         x-data
         @keydown.escape.window="window.location.href = @js(route('organization.ops-room'))">

        @php($p = $this->perspective)
        @php($totals = $this->totals)
        @php($level = $this->levelInfo)
        @php($assignees = $this->assignees)
        @php($signals = $this->signals)
        @php($availablePerspectives = $this->availablePerspectives)
        @php($breadcrumb = [
            ['label' => 'PERSP/'.str_pad((string) $perspectiveEntityId, 4, '0', STR_PAD_LEFT), 'href' => route('organization.ops-room')],
            ['label' => $level['display']],
        ])

        @include('organization::livewire.partials.ops-room-header', ['breadcrumb' => $breadcrumb])

        {{-- ── Level-Master/Detail-Pane ───────────────────────────────────── --}}
        <main class="flex-1 min-h-0 flex overflow-hidden">

            {{-- LEFT — Ebenen-Profil --}}
            <aside class="w-80 flex-shrink-0 border-r border-zinc-800 px-6 py-5 flex flex-col gap-5 overflow-y-auto">
                <div>
                    <div class="text-6xl font-light tracking-tighter tabular-nums leading-none text-orange-400">
                        {{ $level['display'] }}
                    </div>
                    <div class="text-xs uppercase tracking-[0.25em] text-zinc-300 font-medium mt-3">{{ $level['label'] }}</div>
                    <div class="text-[11px] text-zinc-500 mt-2 leading-relaxed">{{ $level['description'] }}</div>
                </div>

                {{-- Assignees --}}
                <div>
                    <div class="text-[9px] tracking-[0.3em] text-zinc-500 uppercase mb-2">Owner</div>
                    @if(empty($assignees))
                        <div class="inline-flex items-center gap-2 text-amber-500 text-[10px] uppercase tracking-[0.2em] border border-amber-500/30 px-2.5 py-1">
                            <span class="w-1.5 h-1.5 bg-amber-500 animate-pulse"></span>
                            Vakant
                        </div>
                    @else
                        <ul class="space-y-1.5">
                            @foreach($assignees as $a)
                                <li class="px-2.5 py-1.5 border border-zinc-800 bg-zinc-900/40">
                                    <a href="{{ route('organization.entities.show', $a['entity_id']) }}" class="block">
                                        <div class="text-sm text-zinc-100 hover:text-orange-300 transition">{{ $a['name'] }}</div>
                                        @if(!empty($a['scope']))
                                            <div class="text-[10px] text-zinc-500 uppercase tracking-wider mt-0.5">{{ $a['scope'] }}</div>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Counters Mini --}}
                <div class="grid grid-cols-3 gap-3 mt-auto pt-4 border-t border-zinc-800">
                    <div>
                        <div class="text-[9px] tracking-[0.3em] text-zinc-500 uppercase">Open</div>
                        <div class="text-2xl font-mono tabular-nums text-zinc-100 leading-none mt-1">{{ str_pad((string) count($signals), 2, '0', STR_PAD_LEFT) }}</div>
                    </div>
                    <div>
                        <div class="text-[9px] tracking-[0.3em] text-zinc-500 uppercase">Esc</div>
                        <div class="text-2xl font-mono tabular-nums leading-none mt-1 {{ collect($signals)->where('is_escalated', true)->count() > 0 ? 'text-amber-400' : 'text-zinc-700' }}">
                            {{ str_pad((string) collect($signals)->where('is_escalated', true)->count(), 2, '0', STR_PAD_LEFT) }}
                        </div>
                    </div>
                    <div>
                        <div class="text-[9px] tracking-[0.3em] uppercase {{ collect($signals)->where('is_algedonic', true)->count() > 0 ? 'text-red-400' : 'text-zinc-500' }}">Alg</div>
                        <div class="text-2xl font-mono tabular-nums leading-none mt-1 {{ collect($signals)->where('is_algedonic', true)->count() > 0 ? 'text-red-500 animate-pulse' : 'text-zinc-700' }}">
                            {{ str_pad((string) collect($signals)->where('is_algedonic', true)->count(), 2, '0', STR_PAD_LEFT) }}
                        </div>
                    </div>
                </div>
            </aside>

            {{-- RIGHT — Signal-Liste --}}
            <section class="flex-1 min-w-0 flex flex-col overflow-hidden">
                <div class="px-6 py-2.5 border-b border-zinc-800 flex items-baseline justify-between flex-shrink-0">
                    <h2 class="text-[10px] tracking-[0.35em] text-zinc-500 uppercase">Offene Signale · {{ $level['display'] }}</h2>
                    <span class="text-[10px] font-mono tabular-nums text-zinc-600">{{ count($signals) }} aktuell</span>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto">
                    @forelse($signals as $s)
                        <a href="{{ route('organization.ops-room.signal', ['signal' => $s['id']]) }}"
                           class="block px-6 py-3 border-b border-zinc-800 hover:bg-zinc-800/40 transition group">
                            <div class="flex items-start gap-3">
                                {{-- Severity / Algedonic-Marker --}}
                                <div class="flex-shrink-0 mt-1">
                                    @if($s['is_algedonic'])
                                        <span class="inline-flex items-center justify-center w-5 h-5 bg-red-600 text-white text-[10px] font-bold animate-pulse" title="Algedonic">!</span>
                                    @elseif($s['severity'] === 'critical')
                                        <span class="inline-flex w-2 h-2 bg-red-500 mt-1.5" title="Critical"></span>
                                    @elseif($s['severity'] === 'warning')
                                        <span class="inline-flex w-2 h-2 bg-amber-400 mt-1.5" title="Warning"></span>
                                    @else
                                        <span class="inline-flex w-2 h-2 bg-zinc-600 mt-1.5"></span>
                                    @endif
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <span class="font-mono tabular-nums text-[10px] text-zinc-500">#{{ str_pad((string) $s['id'], 4, '0', STR_PAD_LEFT) }}</span>
                                        @if($s['entity_name'])
                                            <span class="text-xs text-zinc-300 font-medium tracking-wide">{{ $s['entity_name'] }}</span>
                                        @endif
                                        @if($s['is_escalated'])
                                            <span class="text-[9px] uppercase tracking-[0.2em] text-amber-400 border border-amber-500/30 px-1.5 py-0.5">eskaliert</span>
                                        @endif
                                        @if($s['agent_name'])
                                            <span class="text-[9px] uppercase tracking-[0.2em] text-zinc-500">via {{ $s['agent_name'] }}</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-zinc-200 group-hover:text-zinc-100 leading-snug">{{ $s['message_short'] }}</div>
                                </div>

                                {{-- Right meta --}}
                                <div class="flex-shrink-0 text-right text-[10px] uppercase tracking-wider text-zinc-500 font-mono leading-tight">
                                    <div>{{ $s['created_at'] }}</div>
                                    @if($s['deadline_at'])
                                        <div class="{{ $s['is_overdue'] ? 'text-red-400 mt-1' : 'mt-1' }}">
                                            @if($s['is_overdue']) ⚠ @endif {{ $s['deadline_at'] }}
                                        </div>
                                    @endif
                                </div>

                                <span class="text-zinc-700 group-hover:text-orange-400 transition text-lg font-light flex-shrink-0">›</span>
                            </div>
                        </a>
                    @empty
                        <div class="px-6 py-12 text-center">
                            <div class="text-[10px] tracking-[0.35em] text-zinc-600 uppercase">Keine offenen Signale</div>
                            <div class="text-xs text-zinc-700 mt-2">Diese Ebene ist gerade ruhig.</div>
                        </div>
                    @endforelse
                </div>
            </section>
        </main>

        @include('organization::livewire.partials.ops-room-footer')
    </div>
</x-ui-page>
