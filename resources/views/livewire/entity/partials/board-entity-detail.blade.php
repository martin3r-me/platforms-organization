@php
    $detail = $this->entityDetail;
    if (!$detail) return;
    $ent = $detail['entity'];
    $bandCode = $detail['bandCode'];
    $bandColor = $detail['bandColor'];
    $movement = $detail['movement'];
    $timeSeries = $detail['timeSeries'];
    $incoming = $detail['incomingRelationships'];
    $outgoing = $detail['outgoingRelationships'];
    $stability = $detail['stability'];
    $autonomy = $detail['autonomy'];
    $children = $detail['children'];
    $isRecursive = $detail['isRecursive'];
    $typeGroup = $detail['typeGroup'];
    $metrics = $ent['metrics'] ?? [];
    $itemsPct = ($metrics['items_total'] ?? 0) > 0 ? round(($metrics['items_done'] / $metrics['items_total']) * 100) : 0;
@endphp

<div class="absolute inset-0 w-full h-full z-10 flex flex-col">
    {{-- Header --}}
    <div class="shrink-0 h-12 flex items-center justify-between px-4 border-b border-gray-700/40 bg-gray-900/60 backdrop-blur-md">
        <div class="flex items-center gap-3">
            <button wire:click="showSystem" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 transition-all text-xs">
                @svg('heroicon-o-arrow-left', 'w-3.5 h-3.5')
                <span>Board</span>
            </button>
            <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full ring-2 ring-white/20" style="background:{{ $bandColor }};box-shadow:0 0 8px {{ $bandColor }}"></span>
                <span class="font-bold text-white text-sm">{{ $ent['name'] }}</span>
                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-white/10 text-gray-400">{{ $ent['type'] }}</span>
                @if($bandCode)
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:{{ $bandColor }}20;color:{{ $bandColor }}">{{ $bandCode }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Content: 3-column layout --}}
    <div class="flex-1 min-h-0 overflow-y-auto">
        <div class="flex h-full">

            {{-- Left Column (1/4): Metrics, Stability, Autonomy, Type --}}
            <div class="w-64 shrink-0 p-3 space-y-3 overflow-y-auto border-r border-gray-700/30">

                {{-- Metrics Cards --}}
                @if(($metrics['items_total'] ?? 0) > 0)
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl p-3">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-2">Items</div>
                        <div class="text-2xl font-bold text-white tabular-nums">{{ $metrics['items_done'] }}<span class="text-gray-600 text-lg">/{{ $metrics['items_total'] }}</span></div>
                        <div class="w-full h-2 rounded bg-gray-800 overflow-hidden mt-2">
                            <div class="h-full rounded" style="width:{{ $itemsPct }}%;background:{{ $bandColor }}"></div>
                        </div>
                        <div class="text-[10px] text-gray-500 mt-1 tabular-nums">{{ $itemsPct }}% erledigt</div>
                    </div>
                @endif

                @if(($metrics['time_h'] ?? 0) > 0)
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl p-3">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-2">Zeit</div>
                        <div class="text-2xl font-bold text-white tabular-nums">{{ $metrics['time_h'] }}<span class="text-gray-600 text-lg">h</span></div>
                    </div>
                @endif

                @if(($metrics['okr_perf'] ?? null) !== null)
                    @php $okrColor = $metrics['okr_perf'] >= 70 ? '#10b981' : ($metrics['okr_perf'] >= 30 ? '#f59e0b' : '#ef4444'); @endphp
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl p-3">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-2">OKR Performance</div>
                        <div class="text-2xl font-bold tabular-nums" style="color:{{ $okrColor }}">{{ $metrics['okr_perf'] }}%</div>
                    </div>
                @endif

                {{-- Stability --}}
                @if($stability)
                    @php
                        $stabColor = match($stability['status']) {
                            'stable' => 'text-emerald-400',
                            'mixed' => 'text-amber-400',
                            default => 'text-red-400',
                        };
                    @endphp
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl p-3">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-2">Stability</div>
                        <div class="flex items-center gap-2">
                            <span class="{{ $stabColor }} font-bold">&#9679; {{ ucfirst($stability['status']) }}</span>
                        </div>
                        <div class="text-[10px] text-gray-500 mt-1">Oscillation: {{ $stability['oscillation'] }}</div>
                        <div class="text-[10px] text-gray-500">+{{ $stability['positive'] }} / -{{ $stability['negative'] }}</div>
                    </div>
                @endif

                {{-- Autonomy --}}
                @if($autonomy)
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl p-3">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-2">Autonomy</div>
                        <div class="text-2xl font-bold text-emerald-400 tabular-nums">{{ $autonomy['autonomy_pct'] }}%</div>
                        <div class="w-full h-2 rounded bg-gray-800 overflow-hidden mt-2">
                            <div class="h-full rounded bg-emerald-500/70" style="width:{{ $autonomy['autonomy_pct'] }}%"></div>
                        </div>
                        <div class="text-[10px] text-gray-500 mt-1">Self: {{ $autonomy['self_regulated'] }} / S3: {{ $autonomy['s3_regulated'] }}</div>
                    </div>
                @endif

                {{-- Type Group --}}
                @if($typeGroup)
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl p-3">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-2">Typ</div>
                        <div class="text-sm text-white font-bold">{{ ucfirst($typeGroup) }}</div>
                        <div class="text-[10px] text-gray-600 mt-1">
                            @if($typeGroup === 'team')
                                Kapazitäts-Fokus
                            @elseif($typeGroup === 'person')
                                Skills / Load
                            @elseif($typeGroup === 'project')
                                Timeline / Deliverables
                            @else
                                {{ ucfirst($typeGroup) }}
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Main Column (2/4): Movement, Sparkline, Insights, Children --}}
            <div class="flex-1 min-w-0 p-4 space-y-4 overflow-y-auto">

                {{-- Sparkline: 14-day time series --}}
                @if(!empty($timeSeries))
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl p-4">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-3">Movement (14 Tage)</div>
                        @php
                            $values = array_map(fn($d) => $d['items_done'] ?? 0, $timeSeries);
                            $maxVal = max(1, max($values ?: [1]));
                            $minVal = min($values ?: [0]);
                            $range = max(1, $maxVal - $minVal);
                            $width = 500;
                            $height = 80;
                            $points = [];
                            $count = count($values);
                            if ($count > 1) {
                                foreach ($values as $i => $v) {
                                    $x = round(($i / ($count - 1)) * $width, 1);
                                    $y = round($height - (($v - $minVal) / $range) * ($height - 10) - 5, 1);
                                    $points[] = "$x,$y";
                                }
                            }
                            $pointsStr = implode(' ', $points);

                            // Gradient fill points
                            $fillPoints = $pointsStr . " $width,$height 0,$height";

                            // Date labels
                            $dates = array_map(fn($d) => $d['date'] ?? '', $timeSeries);
                        @endphp
                        <svg viewBox="0 0 {{ $width }} {{ $height + 20 }}" class="w-full h-auto" preserveAspectRatio="xMidYMid meet">
                            {{-- Gradient --}}
                            <defs>
                                <linearGradient id="sparkGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" style="stop-color:{{ $bandColor }};stop-opacity:0.3" />
                                    <stop offset="100%" style="stop-color:{{ $bandColor }};stop-opacity:0.02" />
                                </linearGradient>
                            </defs>
                            @if(count($points) > 1)
                                {{-- Fill area --}}
                                <polygon points="{{ $fillPoints }}" fill="url(#sparkGrad)" />
                                {{-- Line --}}
                                <polyline points="{{ $pointsStr }}" fill="none" stroke="{{ $bandColor }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                {{-- Dots --}}
                                @foreach($points as $i => $pt)
                                    @php [$px, $py] = explode(',', $pt); @endphp
                                    <circle cx="{{ $px }}" cy="{{ $py }}" r="2.5" fill="{{ $bandColor }}" opacity="0.8" />
                                @endforeach
                            @endif
                            {{-- Date labels --}}
                            @if(count($dates) > 1)
                                <text x="0" y="{{ $height + 14 }}" fill="#6b7280" font-size="8" font-family="system-ui">{{ \Illuminate\Support\Str::substr($dates[0] ?? '', 5) }}</text>
                                <text x="{{ $width }}" y="{{ $height + 14 }}" fill="#6b7280" font-size="8" font-family="system-ui" text-anchor="end">{{ \Illuminate\Support\Str::substr(end($dates) ?: '', 5) }}</text>
                            @endif
                        </svg>
                    </div>
                @endif

                {{-- Movement Delta Table --}}
                @if(!empty($movement['metrics']))
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl p-4">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-3">Bewegung (7 Tage)</div>
                        <div class="space-y-1">
                            @foreach($movement['metrics'] as $metric)
                                @if(($metric['delta'] ?? 0) != 0)
                                    @php
                                        $sentimentColor = match($metric['sentiment'] ?? 'neutral') {
                                            'positive' => 'text-emerald-400',
                                            'negative' => 'text-red-400',
                                            default => 'text-gray-400',
                                        };
                                        $arrow = ($metric['delta'] ?? 0) > 0 ? '&#9650;' : '&#9660;';
                                    @endphp
                                    <div class="flex items-center gap-3 px-2 py-1.5 rounded bg-gray-800/40 text-xs">
                                        <span class="text-gray-400 flex-1 truncate">{{ $metric['label'] ?? $metric['key'] ?? '' }}</span>
                                        <span class="text-white tabular-nums shrink-0">{{ $metric['current_formatted'] ?? $metric['current'] ?? '' }}</span>
                                        <span class="{{ $sentimentColor }} tabular-nums shrink-0 font-bold">{!! $arrow !!} {{ $metric['delta_formatted'] ?? $metric['delta'] ?? '' }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Insights / Summary --}}
                @if(!empty($movement['summary']))
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl p-4">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-3">Insights</div>
                        <div class="space-y-1.5">
                            @foreach($movement['summary'] as $insight)
                                @php
                                    $isPositive = ($insight['type'] ?? '') === 'positive' || ($insight['sentiment'] ?? '') === 'positive';
                                    $icon = $isPositive ? '&#10003;' : '&#9888;';
                                    $color = $isPositive ? 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20' : 'text-amber-400 bg-amber-500/10 border-amber-500/20';
                                @endphp
                                <div class="flex items-start gap-2 px-2 py-1.5 rounded border text-xs {{ $color }}">
                                    <span class="shrink-0 mt-0.5">{!! $icon !!}</span>
                                    <span>{{ $insight['text'] ?? $insight['message'] ?? '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Children (recursive entities) --}}
                @if(!empty($children))
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl p-4">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-3">Children ({{ count($children) }})</div>
                        <div class="grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr))">
                            @foreach($children as $child)
                                @php
                                    $cmv = $child['movement'] ?? [];
                                    $cscore = $cmv['score'] ?? 0;
                                    $cm = $child['metrics'] ?? [];
                                    $cItemsPct = ($cm['items_total'] ?? 0) > 0 ? round(($cm['items_done'] / $cm['items_total']) * 100) : 0;
                                @endphp
                                <div class="board-card relative rounded-lg cursor-pointer select-none overflow-hidden"
                                     style="background:rgba(15,23,42,0.85);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.06)"
                                     wire:click="showEntity({{ $child['id'] }})">
                                    <div class="absolute left-0 top-0 bottom-0 w-[3px] rounded-l-lg" style="background:{{ $bandColor }}60"></div>
                                    <div class="pl-3 pr-2 py-2">
                                        <div class="font-medium text-[11px] text-white truncate">{{ $child['name'] }}</div>
                                        <div class="text-[9px] text-gray-500">{{ $child['type'] }}</div>
                                        <div class="flex items-center gap-2 mt-1 text-[9px]">
                                            @if(($cm['items_total'] ?? 0) > 0)
                                                <span class="text-gray-400 tabular-nums">{{ $cItemsPct }}%</span>
                                            @endif
                                            @if($cscore != 0)
                                                <span class="{{ $cscore > 0 ? 'text-emerald-400' : 'text-red-400' }}">{{ $cscore > 0 ? '&#9650;' : '&#9660;' }} {{ $cscore > 0 ? '+' : '' }}{{ $cscore }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right Column (1/4): Relationships, Actions --}}
            <div class="w-64 shrink-0 p-3 space-y-3 overflow-y-auto border-l border-gray-700/30">

                {{-- Incoming Relationships --}}
                <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
                        @svg('heroicon-o-arrow-down-on-square', 'w-4 h-4 text-cyan-400')
                        <span>Eingehend</span>
                        <span class="ml-auto text-gray-600 tabular-nums">{{ count($incoming) }}</span>
                    </div>
                    <div class="p-2 space-y-1">
                        @forelse($incoming as $rel)
                            <div class="flex items-center gap-2 px-2 py-1 rounded bg-gray-800/40">
                                <span class="text-gray-500">&larr;</span>
                                <span class="text-gray-300 truncate flex-1">{{ $rel['entity_name'] }}</span>
                                <span class="text-[9px] px-1 py-0.5 rounded" style="color:{{ $rel['color'] }};background:{{ $rel['color'] }}15">{{ $rel['category'] }}</span>
                            </div>
                        @empty
                            <div class="text-gray-600 text-[10px] px-2 py-1 italic">Keine</div>
                        @endforelse
                    </div>
                </div>

                {{-- Outgoing Relationships --}}
                <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
                        @svg('heroicon-o-arrow-up-on-square', 'w-4 h-4 text-orange-400')
                        <span>Ausgehend</span>
                        <span class="ml-auto text-gray-600 tabular-nums">{{ count($outgoing) }}</span>
                    </div>
                    <div class="p-2 space-y-1">
                        @forelse($outgoing as $rel)
                            <div class="flex items-center gap-2 px-2 py-1 rounded bg-gray-800/40">
                                <span class="text-gray-500">&rarr;</span>
                                <span class="text-gray-300 truncate flex-1">{{ $rel['entity_name'] }}</span>
                                <span class="text-[9px] px-1 py-0.5 rounded" style="color:{{ $rel['color'] }};background:{{ $rel['color'] }}15">{{ $rel['category'] }}</span>
                            </div>
                        @empty
                            <div class="text-gray-600 text-[10px] px-2 py-1 italic">Keine</div>
                        @endforelse
                    </div>
                </div>

                {{-- Actions --}}
                <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
                        @svg('heroicon-o-bolt', 'w-4 h-4 text-amber-400')
                        <span>Aktionen</span>
                    </div>
                    <div class="p-2 space-y-1.5">
                        <a href="{{ route('organization.entities.show', $ent['id']) }}"
                           class="w-full px-3 py-2 rounded-lg bg-white/5 text-gray-300 hover:bg-white/10 hover:text-white transition-all flex items-center gap-2">
                            @svg('heroicon-o-arrow-top-right-on-square', 'w-3.5 h-3.5')
                            <span>Entity öffnen</span>
                        </a>
                        <a href="{{ route('organization.entities.mindmap', $ent['id']) }}"
                           class="w-full px-3 py-2 rounded-lg bg-white/5 text-gray-300 hover:bg-white/10 hover:text-white transition-all flex items-center gap-2">
                            @svg('heroicon-o-globe-alt', 'w-3.5 h-3.5')
                            <span>Mindmap</span>
                        </a>
                        @if($isRecursive)
                            <a href="{{ route('organization.entities.board', $ent['id']) }}"
                               class="w-full px-3 py-2 rounded-lg bg-cyan-500/10 text-cyan-400 hover:bg-cyan-500/20 transition-all flex items-center gap-2 border border-cyan-500/20">
                                @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                                <span>VSM Board &rarr;</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
