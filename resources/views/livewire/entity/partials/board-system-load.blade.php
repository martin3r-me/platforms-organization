@php
    $vsmColors = $boardData['vsmColors'];
@endphp

<div x-data="{ open: false }" class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
    <button @click="open = !open" class="w-full px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2 hover:bg-white/5 transition-colors">
        @svg('heroicon-o-cpu-chip', 'w-4 h-4 text-cyan-400')
        <span>System Load</span>
        @php $totalOpen = array_sum(array_column($systemLoad, 'open_items')); @endphp
        @if($totalOpen > 0)
            <span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-cyan-500/20 text-cyan-400">{{ $totalOpen }} open</span>
        @endif
        <span class="ml-auto text-gray-600 text-[10px]" x-text="open ? '&#9660;' : '&#9654;'"></span>
    </button>
    <div x-show="open" x-collapse class="p-2 space-y-2">
        @foreach($systemLoad as $code => $load)
            @php
                $pct = $load['items_total'] > 0 ? round(($load['items_done'] / $load['items_total']) * 100) : 0;
                $color = $vsmColors[$code] ?? '#3b82f6';
            @endphp
            <div class="px-1">
                <div class="flex items-center justify-between mb-0.5">
                    <span class="text-[10px] font-bold" style="color:{{ $color }}">{{ $code }}</span>
                    <div class="flex items-center gap-2">
                        @if($load['congested'])
                            <span class="px-1 py-0.5 rounded text-[8px] font-bold bg-red-500/20 text-red-400">CONGESTED</span>
                        @endif
                        <span class="text-[9px] text-gray-500 tabular-nums">{{ $load['items_done'] }}/{{ $load['items_total'] }}</span>
                    </div>
                </div>
                <div class="h-1.5 rounded bg-gray-800 overflow-hidden">
                    <div class="h-full rounded transition-all" style="width:{{ $pct }}%;background:{{ $color }}{{ $load['congested'] ? '' : '80' }}"></div>
                </div>
                <div class="flex items-center justify-between mt-0.5">
                    <span class="text-[9px] text-gray-600">{{ $load['entity_count'] }} Entities</span>
                    <span class="text-[9px] {{ $load['congested'] ? 'text-red-400' : 'text-gray-500' }} tabular-nums">
                        {{ $load['load_per_entity'] }} items/entity
                    </span>
                </div>
            </div>
        @endforeach

        {{-- Autonomy Index (S1 only) --}}
        @if(!empty($autonomyIndex))
            <div class="border-t border-gray-700/30 pt-1.5 mt-1">
                <div class="px-1 text-[9px] uppercase tracking-wider text-gray-500 mb-1">Autonomie (S1)</div>
                @foreach($autonomyIndex as $id => $ai)
                    @php
                        $aiColor = $ai['autonomy_pct'] >= 70 ? 'text-emerald-400' : ($ai['autonomy_pct'] >= 40 ? 'text-amber-400' : 'text-red-400');
                    @endphp
                    <div class="flex items-center gap-2 px-1 py-0.5">
                        <span class="text-[10px] text-gray-400 truncate flex-1">{{ $ai['name'] }}</span>
                        <span class="text-[10px] font-bold tabular-nums {{ $aiColor }}">{{ $ai['autonomy_pct'] }}%</span>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Stability Indicator --}}
        @if(!empty($stabilityIndicator))
            @php
                $unstable = array_filter($stabilityIndicator, fn($s) => $s['status'] !== 'stable');
            @endphp
            @if(!empty($unstable))
                <div class="border-t border-gray-700/30 pt-1.5 mt-1">
                    <div class="px-1 text-[9px] uppercase tracking-wider text-gray-500 mb-1">Stabilität</div>
                    @foreach(array_slice($unstable, 0, 5) as $id => $si)
                        @php
                            $siColor = match($si['status']) {
                                'oscillating' => 'text-red-400',
                                'mixed' => 'text-amber-400',
                                default => 'text-gray-400',
                            };
                        @endphp
                        <div class="flex items-center gap-2 px-1 py-0.5">
                            <span class="text-[10px] text-gray-400 truncate flex-1">{{ $si['name'] }}</span>
                            <span class="text-[9px] {{ $siColor }}">{{ $si['status'] }}</span>
                            <span class="text-[9px] text-gray-600 tabular-nums">({{ $si['oscillation'] }})</span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
