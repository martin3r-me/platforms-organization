@php
    $detail = $this->bandDetail;
    if (!$detail) return;
    $code = $detail['code'];
    $color = $detail['color'];
    $entities = $detail['entities'];
    $load = $detail['load'];
    $regulation = $detail['regulation'];
    $variety = $detail['variety'];
    $stability = $detail['stability'];
    $autonomy = $detail['autonomy'];
    $internalRels = $detail['internalRelationships'];
    $crossRels = $detail['crossBandRelationships'];
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
                <span class="w-3 h-3 rounded-sm" style="background:{{ $color }}"></span>
                <span class="font-bold text-white text-sm">{{ $detail['label'] }}</span>
                <span class="text-xs text-gray-500">({{ count($entities) }} Entities)</span>
            </div>
        </div>
        @if($regulation)
            @php
                $statusColor = match($regulation['status']) {
                    'healthy' => 'text-emerald-400',
                    'stressed' => 'text-red-400',
                    default => 'text-gray-500',
                };
            @endphp
            <div class="flex items-center gap-2 text-xs">
                <span class="{{ $statusColor }}">&#9679; {{ ucfirst($regulation['status']) }}</span>
            </div>
        @endif
    </div>

    {{-- Content --}}
    <div class="flex-1 min-h-0 overflow-y-auto">
        <div class="flex h-full">
            {{-- Main Area (2/3) --}}
            <div class="flex-1 min-w-0 p-4">
                {{-- Entity Grid --}}
                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr))">
                    @foreach($entities as $ent)
                        @php
                            $mv = $ent['movement'] ?? [];
                            $score = $mv['score'] ?? 0;
                            $movementClass = $score > 0 ? 'movement-positive' : ($score < 0 ? 'movement-negative' : '');
                            $m = $ent['metrics'] ?? [];
                            $itemsPct = ($m['items_total'] ?? 0) > 0 ? round(($m['items_done'] / $m['items_total']) * 100) : 0;
                        @endphp
                        <div class="board-card {{ $movementClass }} relative rounded-xl cursor-pointer select-none overflow-hidden"
                             style="background:rgba(15,23,42,0.85);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.08)"
                             wire:click="showEntity({{ $ent['id'] }})">
                            <div class="absolute left-0 top-0 bottom-0 w-[3px] rounded-l-xl" style="background:{{ $color }}"></div>
                            <div class="pl-4 pr-3 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="font-medium text-sm text-white truncate flex-1">{{ $ent['name'] }}</div>
                                    @if(!empty($ent['is_recursive']))
                                        <span class="shrink-0 w-5 h-5 flex items-center justify-center rounded bg-cyan-500/20 text-[10px] text-cyan-400" title="Rekursiv">&#8635;</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-gray-500 mt-0.5">{{ $ent['type'] }}</div>

                                <div class="flex items-center gap-3 mt-2 text-[10px]">
                                    @if(($m['items_total'] ?? 0) > 0)
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-16 h-1.5 rounded bg-gray-700 overflow-hidden">
                                                <div class="h-full rounded bg-blue-500/70" style="width:{{ $itemsPct }}%"></div>
                                            </div>
                                            <span class="text-gray-400 tabular-nums">{{ $m['items_done'] }}/{{ $m['items_total'] }}</span>
                                        </div>
                                    @endif
                                    @if(($m['time_h'] ?? 0) > 0)
                                        <span class="text-gray-500 tabular-nums">{{ $m['time_h'] }}h</span>
                                    @endif
                                    @if(($m['okr_perf'] ?? null) !== null)
                                        @php $okrColor = $m['okr_perf'] >= 70 ? 'text-emerald-400' : ($m['okr_perf'] >= 30 ? 'text-amber-400' : 'text-red-400'); @endphp
                                        <span class="{{ $okrColor }} tabular-nums">OKR {{ $m['okr_perf'] }}%</span>
                                    @endif
                                </div>

                                @if(($mv['delta_count'] ?? 0) > 0)
                                    <div class="flex items-center gap-1.5 mt-1.5 text-[10px]">
                                        @if($score > 0)
                                            <span class="text-emerald-400">&#9650; +{{ $score }}</span>
                                        @elseif($score < 0)
                                            <span class="text-red-400">&#9660; {{ $score }}</span>
                                        @else
                                            <span class="text-gray-600">&#9644; 0</span>
                                        @endif
                                        @if(!empty($mv['top_delta']))
                                            <span class="text-gray-600 truncate">{{ $mv['top_delta'] }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(empty($entities))
                    <div class="flex items-center justify-center h-40 text-gray-600 text-sm">
                        Keine Entities in diesem Band zugeordnet.
                    </div>
                @endif

                {{-- Internal Relationships --}}
                @if(!empty($internalRels))
                    <div class="mt-6">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-2">Interne Beziehungen</div>
                        <div class="space-y-1">
                            @foreach($internalRels as $rel)
                                @php
                                    $fromName = collect($entities)->firstWhere('id', $rel['from'])['name'] ?? '?';
                                    $toName = collect($entities)->firstWhere('id', $rel['to'])['name'] ?? '?';
                                @endphp
                                <div class="flex items-center gap-2 text-xs px-2 py-1 rounded bg-gray-900/50">
                                    <span class="text-gray-300">{{ $fromName }}</span>
                                    <span style="color:{{ $rel['color'] }}">&#8594;</span>
                                    <span class="text-gray-300">{{ $toName }}</span>
                                    <span class="text-gray-600 text-[10px]">({{ $rel['label'] }})</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Cross-Band Relationships --}}
                @if(!empty($crossRels))
                    <div class="mt-4">
                        <div class="text-[10px] uppercase tracking-wider font-bold text-gray-500 mb-2">Cross-Band Beziehungen</div>
                        <div class="space-y-1">
                            @foreach(array_slice($crossRels, 0, 10) as $rel)
                                @php
                                    $fromName = collect($entities)->firstWhere('id', $rel['from'])['name'] ?? null;
                                    $toName = collect($entities)->firstWhere('id', $rel['to'])['name'] ?? null;
                                    $isOutgoing = $fromName !== null;
                                @endphp
                                <div class="flex items-center gap-2 text-xs px-2 py-1 rounded bg-gray-900/50">
                                    @if($isOutgoing)
                                        <span class="text-gray-300">{{ $fromName }}</span>
                                        <span style="color:{{ $rel['color'] }}">&#8594;</span>
                                        <span class="text-gray-500 italic">extern</span>
                                    @else
                                        <span class="text-gray-500 italic">extern</span>
                                        <span style="color:{{ $rel['color'] }}">&#8594;</span>
                                        <span class="text-gray-300">{{ $toName }}</span>
                                    @endif
                                    <span class="text-gray-600 text-[10px]">({{ $rel['label'] }})</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar (1/3) --}}
            <div class="w-72 shrink-0 p-3 space-y-3 overflow-y-auto border-l border-gray-700/30">

                {{-- Band Load --}}
                @if($load)
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
                        <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
                            @svg('heroicon-o-cpu-chip', 'w-4 h-4 text-blue-400')
                            <span>Band Load</span>
                        </div>
                        <div class="p-3 space-y-2">
                            @php
                                $loadPct = $load['items_total'] > 0 ? round(($load['items_done'] / $load['items_total']) * 100) : 0;
                            @endphp
                            <div>
                                <div class="flex justify-between text-[10px] mb-1">
                                    <span class="text-gray-400">Fortschritt</span>
                                    <span class="text-white tabular-nums">{{ $loadPct }}%</span>
                                </div>
                                <div class="w-full h-2 rounded bg-gray-800 overflow-hidden">
                                    <div class="h-full rounded transition-all" style="width:{{ $loadPct }}%;background:{{ $color }}"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-[10px]">
                                <div class="px-2 py-1.5 rounded bg-gray-800/50">
                                    <div class="text-gray-500">Offen</div>
                                    <div class="text-white font-bold tabular-nums">{{ $load['open_items'] }}</div>
                                </div>
                                <div class="px-2 py-1.5 rounded bg-gray-800/50">
                                    <div class="text-gray-500">Load/Entity</div>
                                    <div class="text-white font-bold tabular-nums {{ $load['congested'] ? 'text-red-400' : '' }}">{{ $load['load_per_entity'] }}</div>
                                </div>
                                <div class="px-2 py-1.5 rounded bg-gray-800/50">
                                    <div class="text-gray-500">Zeit</div>
                                    <div class="text-white font-bold tabular-nums">{{ $load['time_h'] }}h</div>
                                </div>
                                <div class="px-2 py-1.5 rounded bg-gray-800/50">
                                    <div class="text-gray-500">Entities</div>
                                    <div class="text-white font-bold tabular-nums">{{ $load['entity_count'] }}</div>
                                </div>
                            </div>
                            @if($load['congested'])
                                <div class="text-[10px] text-red-400 px-1">&#9888; Band ist congested</div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Regulation Health --}}
                @if($regulation)
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
                        <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4 text-cyan-400')
                            <span>Regulation</span>
                        </div>
                        <div class="p-3 space-y-2">
                            @php
                                $regStatusColor = match($regulation['status']) {
                                    'healthy' => 'text-emerald-400',
                                    'stressed' => 'text-red-400',
                                    default => 'text-amber-400',
                                };
                            @endphp
                            <div class="flex items-center gap-2">
                                <span class="{{ $regStatusColor }}">&#9679;</span>
                                <span class="{{ $regStatusColor }} font-bold">{{ ucfirst($regulation['status']) }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-[10px]">
                                <div class="px-2 py-1.5 rounded bg-gray-800/50">
                                    <div class="text-gray-500">Inbound</div>
                                    <div class="text-white font-bold tabular-nums">{{ $regulation['inbound'] }}</div>
                                </div>
                                <div class="px-2 py-1.5 rounded bg-gray-800/50">
                                    <div class="text-gray-500">Outbound</div>
                                    <div class="text-white font-bold tabular-nums">{{ $regulation['outbound'] }}</div>
                                </div>
                            </div>
                            <div class="text-[10px] text-gray-500">
                                Avg Movement: <span class="{{ $regulation['avg_movement'] > 0 ? 'text-emerald-400' : ($regulation['avg_movement'] < 0 ? 'text-red-400' : 'text-gray-400') }}">{{ $regulation['avg_movement'] > 0 ? '+' : '' }}{{ $regulation['avg_movement'] }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Variety --}}
                @if($variety)
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
                        <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
                            @svg('heroicon-o-variable', 'w-4 h-4 text-purple-400')
                            <span>Variety</span>
                        </div>
                        <div class="p-3 space-y-2">
                            @php
                                $gapColor = match($variety['gap']) {
                                    'balanced' => 'text-emerald-400',
                                    'marginal' => 'text-amber-400',
                                    default => 'text-red-400',
                                };
                            @endphp
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Status</span>
                                <span class="{{ $gapColor }} font-bold">{{ ucfirst($variety['gap']) }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-[10px]">
                                <div class="px-2 py-1.5 rounded bg-gray-800/50">
                                    <div class="text-gray-500">Required</div>
                                    <div class="text-white font-bold tabular-nums">{{ $variety['required'] }}</div>
                                </div>
                                <div class="px-2 py-1.5 rounded bg-gray-800/50">
                                    <div class="text-gray-500">Available</div>
                                    <div class="text-white font-bold tabular-nums">{{ $variety['available'] }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Stability --}}
                @if(!empty($stability))
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
                        <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
                            @svg('heroicon-o-chart-bar', 'w-4 h-4 text-amber-400')
                            <span>Stability</span>
                        </div>
                        <div class="p-3 space-y-1.5">
                            @foreach($stability as $eid => $stab)
                                @php
                                    $stabColor = match($stab['status']) {
                                        'stable' => 'text-emerald-400',
                                        'mixed' => 'text-amber-400',
                                        default => 'text-red-400',
                                    };
                                @endphp
                                <div class="flex items-center justify-between px-1 py-0.5">
                                    <span class="text-gray-300 truncate flex-1">{{ $stab['name'] }}</span>
                                    <span class="{{ $stabColor }} text-[10px] font-bold ml-2">{{ $stab['status'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Autonomy (S1 only) --}}
                @if(!empty($autonomy))
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
                        <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
                            @svg('heroicon-o-shield-check', 'w-4 h-4 text-emerald-400')
                            <span>Autonomy</span>
                        </div>
                        <div class="p-3 space-y-2">
                            @foreach($autonomy as $eid => $aut)
                                <div class="px-1">
                                    <div class="flex items-center justify-between mb-0.5">
                                        <span class="text-gray-300 truncate text-[10px]">{{ $aut['name'] }}</span>
                                        <span class="text-white font-bold tabular-nums text-[10px]">{{ $aut['autonomy_pct'] }}%</span>
                                    </div>
                                    <div class="w-full h-1.5 rounded bg-gray-800 overflow-hidden">
                                        <div class="h-full rounded bg-emerald-500/70" style="width:{{ $aut['autonomy_pct'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
