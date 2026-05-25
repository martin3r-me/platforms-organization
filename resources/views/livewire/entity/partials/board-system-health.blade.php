@php
    $congestedCount = count(array_filter($systemLoad, fn ($l) => $l['congested']));
    $disconnectedCount = count(array_filter($regulationHealth, fn ($r) => $r['status'] === 'disconnected'));
    $oscillatingCount = count(array_filter($stabilityIndicator, fn ($s) => $s['status'] === 'oscillating'));

    $issueCount = $congestedCount + $disconnectedCount + $oscillatingCount;

    if ($issueCount === 0) {
        $trafficLight = 'green';
        $lightColor = '#10b981';
        $diagText = 'System operiert normal';
    } elseif ($issueCount <= 2) {
        $trafficLight = 'amber';
        $lightColor = '#f59e0b';
        $diagText = $issueCount . ' Auff' . ($issueCount === 1 ? 'älligkeit' : 'älligkeiten');
    } else {
        $trafficLight = 'red';
        $lightColor = '#ef4444';
        $diagText = $issueCount . ' Probleme erkannt';
    }
@endphp

<div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
    <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
        @svg('heroicon-o-heart', 'w-4 h-4 text-rose-400')
        <span>System Health</span>
        <span class="ml-auto flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $lightColor }};box-shadow:0 0 6px {{ $lightColor }}80"></span>
        </span>
    </div>
    <div class="p-2 space-y-1.5">
        {{-- Traffic Light --}}
        <div class="flex items-center gap-3 px-1 py-1">
            <div class="flex gap-1">
                <span class="w-3 h-3 rounded-full border {{ $trafficLight === 'red' ? 'bg-red-500 border-red-400 shadow-[0_0_6px_rgba(239,68,68,0.6)]' : 'bg-red-500/20 border-red-500/30' }}"></span>
                <span class="w-3 h-3 rounded-full border {{ $trafficLight === 'amber' ? 'bg-amber-500 border-amber-400 shadow-[0_0_6px_rgba(245,158,11,0.6)]' : 'bg-amber-500/20 border-amber-500/30' }}"></span>
                <span class="w-3 h-3 rounded-full border {{ $trafficLight === 'green' ? 'bg-emerald-500 border-emerald-400 shadow-[0_0_6px_rgba(16,185,129,0.6)]' : 'bg-emerald-500/20 border-emerald-500/30' }}"></span>
            </div>
            <span class="text-[11px]" style="color:{{ $lightColor }}">{{ $diagText }}</span>
        </div>

        {{-- Issue breakdown --}}
        @if($issueCount > 0)
            <div class="space-y-0.5 px-1">
                @if($congestedCount > 0)
                    <div class="flex items-center gap-1.5 text-[10px] text-amber-400">
                        @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                        <span>{{ $congestedCount }} Band{{ $congestedCount > 1 ? 's' : '' }} congested</span>
                    </div>
                @endif
                @if($disconnectedCount > 0)
                    <div class="flex items-center gap-1.5 text-[10px] text-red-400">
                        @svg('heroicon-o-link-slash', 'w-3 h-3')
                        <span>{{ $disconnectedCount }} Loop{{ $disconnectedCount > 1 ? 's' : '' }} disconnected</span>
                    </div>
                @endif
                @if($oscillatingCount > 0)
                    <div class="flex items-center gap-1.5 text-[10px] text-purple-400">
                        @svg('heroicon-o-arrow-path', 'w-3 h-3')
                        <span>{{ $oscillatingCount }} Entity{{ $oscillatingCount > 1 ? 's' : '' }} oszillierend</span>
                    </div>
                @endif
            </div>
        @endif

        {{-- Regulation Health Summary --}}
        <div class="border-t border-gray-700/30 pt-1.5 mt-1.5 space-y-0.5">
            @foreach($regulationHealth as $code => $rh)
                @php
                    $statusIcon = match($rh['status']) {
                        'healthy' => ['text-emerald-400', 'heroicon-o-check-circle'],
                        'stressed' => ['text-amber-400', 'heroicon-o-exclamation-circle'],
                        'disconnected' => ['text-red-400', 'heroicon-o-x-circle'],
                    };
                @endphp
                <div class="flex items-center gap-1.5 px-1">
                    <span class="w-6 text-[10px] font-bold text-gray-500">{{ $code }}</span>
                    @svg($statusIcon[1], 'w-3 h-3 ' . $statusIcon[0])
                    <span class="text-[10px] text-gray-400">{{ $rh['inbound'] }}in/{{ $rh['outbound'] }}out</span>
                    @if($rh['avg_movement'] != 0)
                        <span class="text-[9px] {{ $rh['avg_movement'] > 0 ? 'text-emerald-400' : 'text-red-400' }} ml-auto tabular-nums">
                            {{ $rh['avg_movement'] > 0 ? '+' : '' }}{{ $rh['avg_movement'] }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
