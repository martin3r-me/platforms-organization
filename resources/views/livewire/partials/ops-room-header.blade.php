{{--
    Shared Cybersyn-Header — wird in OpsRoom, OpsRoomLevel, OpsRoomSignal eingebunden.
    Erwartete Variablen im Context:
      - $availablePerspectives  (array von ['id','name'])
      - $perspectiveEntityId    (int|null)
      - $totals                 (array open/escalated/algedonic/vacant_cells)
      - $breadcrumb             (array von ['label','href' (optional)])  — optional
--}}
<header class="flex-shrink-0 border-b border-zinc-800 px-6 py-3 flex items-center gap-6">
    {{-- Heptagonal Mark (7 fiberglass-Stühle in Santiago) — Link zurück zur Brücke --}}
    <a href="{{ route('organization.ops-room') }}" class="flex items-center gap-3 flex-shrink-0 group" title="Zur Brücke">
        <svg viewBox="0 0 100 100" class="w-8 h-8 text-orange-400 group-hover:text-orange-300 transition" fill="none" stroke="currentColor" stroke-width="3">
            <polygon points="50,6 91,28 96,72 71,96 29,96 4,72 9,28" />
            <polygon points="50,22 78,38 81,66 65,82 35,82 19,66 22,38" stroke-width="1.2" opacity="0.4" />
            <circle cx="50" cy="56" r="2.5" fill="currentColor" stroke="none" />
        </svg>
        <div class="leading-tight">
            <div class="text-[9px] tracking-[0.35em] text-zinc-500 uppercase">Operations Room</div>
            <div class="text-sm font-medium tracking-[0.25em] uppercase text-zinc-100 group-hover:text-orange-300 transition">Cybersyn 2.0</div>
        </div>
    </a>

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

    {{-- Breadcrumb (optional) — z.B. PERSP/0001 › S4 › #91 --}}
    @if(!empty($breadcrumb ?? null))
        <nav class="flex items-center gap-2 text-[10px] tracking-[0.3em] text-zinc-500 uppercase font-mono whitespace-nowrap overflow-hidden">
            @foreach($breadcrumb as $i => $crumb)
                @if($i > 0)<span class="text-zinc-700">›</span>@endif
                @if(!empty($crumb['href']))
                    <a href="{{ $crumb['href'] }}" class="hover:text-orange-300 transition truncate">{{ $crumb['label'] }}</a>
                @else
                    <span class="text-zinc-300 truncate">{{ $crumb['label'] }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    {{-- Totals + Uhrzeit (rechtsbündig) --}}
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
