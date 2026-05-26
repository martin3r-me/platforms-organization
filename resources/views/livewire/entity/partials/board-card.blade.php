@php
    $mv = $ent['movement'] ?? [];
    $score = $mv['score'] ?? 0;
    $movementClass = $score > 0 ? 'movement-positive' : ($score < 0 ? 'movement-negative' : '');
    $m = $ent['metrics'] ?? [];
    $itemsPct = ($m['items_total'] ?? 0) > 0 ? round(($m['items_done'] / $m['items_total']) * 100) : 0;
@endphp
<div class="board-card {{ $movementClass }} relative rounded-lg cursor-pointer select-none overflow-hidden shrink-0"
     style="width:190px;background:rgba(15,23,42,0.85);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.06)"
     data-entity-id="{{ $ent['id'] }}">
    {{-- Left accent --}}
    <div class="absolute left-0 top-0 bottom-0 w-[3px] rounded-l-lg" style="background:{{ $bandColor }}"></div>

    <div class="pl-3 pr-2 py-2">
        {{-- Name + Type + Recursive Badge --}}
        <div class="flex items-center gap-1">
            <div class="font-medium text-[11px] text-white truncate leading-tight flex-1">{{ $ent['name'] }}</div>
            <button data-entity-detail="{{ $ent['id'] }}"
                    class="board-card-detail-btn shrink-0 w-5 h-5 flex items-center justify-center rounded bg-white/10 text-[10px] text-gray-400 hover:bg-white/20 hover:text-white transition-all opacity-0"
                    title="Detail"
                    onclick="event.stopPropagation()">&#9654;</button>
            @if(!empty($ent['is_recursive']))
                <a href="{{ route('organization.entities.board', $ent['id']) }}"
                   class="shrink-0 w-5 h-5 flex items-center justify-center rounded bg-white/10 text-[10px] text-cyan-400 hover:bg-cyan-500/20 transition-colors"
                   title="Sub-Board öffnen"
                   onclick="event.stopPropagation()">&#8635;</a>
            @endif
        </div>
        <div class="text-[9px] text-gray-500 truncate">{{ $ent['type'] }}</div>

        {{-- Metrics row --}}
        <div class="flex items-center gap-2 mt-1.5 text-[9px]">
            @if(($m['items_total'] ?? 0) > 0)
                <div class="flex items-center gap-1">
                    <div class="w-12 h-1 rounded bg-gray-700 overflow-hidden">
                        <div class="h-full rounded bg-blue-500/70" style="width:{{ $itemsPct }}%"></div>
                    </div>
                    <span class="text-gray-500 tabular-nums">{{ $itemsPct }}%</span>
                </div>
            @endif
            @if(($m['time_h'] ?? 0) > 0)
                <span class="text-gray-500 tabular-nums">{{ $m['time_h'] }}h</span>
            @endif
            @if(($m['okr_perf'] ?? null) !== null)
                @php $okrColor = $m['okr_perf'] >= 70 ? 'text-emerald-400' : ($m['okr_perf'] >= 30 ? 'text-amber-400' : 'text-red-400'); @endphp
                <span class="{{ $okrColor }} tabular-nums">{{ $m['okr_perf'] }}%</span>
            @endif
        </div>

        {{-- Movement indicator --}}
        @if(($mv['delta_count'] ?? 0) > 0)
            <div class="flex items-center gap-1 mt-1 text-[9px]">
                @if($score > 0)
                    <span class="text-emerald-400">&#9650; +{{ $score }}</span>
                @elseif($score < 0)
                    <span class="text-red-400">&#9660; {{ $score }}</span>
                @else
                    <span class="text-gray-600">&#9644; 0</span>
                @endif
                @if(!empty($mv['top_delta']))
                    <span class="text-gray-600 truncate max-w-[100px]">{{ $mv['top_delta'] }}</span>
                @endif
            </div>
        @endif
    </div>
</div>
