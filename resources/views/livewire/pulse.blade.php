@php
    $colorTones = [
        'red' => ['fg' => 'text-rose-600', 'bg' => 'bg-rose-50', 'border' => 'border-rose-200', 'fill' => 'bg-rose-500', 'label' => 'Brennt'],
        'yellow' => ['fg' => 'text-amber-700', 'bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'fill' => 'bg-amber-400', 'label' => 'Achtung'],
        'green' => ['fg' => 'text-emerald-700', 'bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'fill' => 'bg-emerald-500', 'label' => 'Stabil'],
        'gray' => ['fg' => 'text-zinc-600', 'bg' => 'bg-zinc-50', 'border' => 'border-zinc-200', 'fill' => 'bg-zinc-400', 'label' => 'Ohne Daten'],
    ];
    $tone = fn ($c) => $colorTones[$c ?: 'gray'] ?? $colorTones['gray'];
    $moduleIcon = [
        'planner' => 'heroicon-o-rectangle-stack',
        'helpdesk' => 'heroicon-o-lifebuoy',
        'dev' => 'heroicon-o-cube',
    ];
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Pulse" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'icon' => 'building-office'],
            ['label' => 'Pulse', 'icon' => 'signal'],
        ]">
            <div class="text-xs text-[var(--ui-muted)] flex items-center gap-3">
                @if($rootTeam)
                    <span class="inline-flex items-center gap-1">
                        @svg('heroicon-o-building-office-2', 'w-3.5 h-3.5')
                        <span class="font-medium text-[var(--ui-secondary)]">{{ $rootTeam->name }}</span>
                        <span>· {{ $teamCount }} {{ $teamCount === 1 ? 'Team' : 'Teams' }}</span>
                    </span>
                @endif
                @if($snapshotStand)
                    <span>Stand: {{ \Illuminate\Support\Carbon::parse($snapshotStand)->diffForHumans() }}</span>
                @else
                    <span>Noch keine Snapshots</span>
                @endif
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Confidence" width="w-72" :defaultOpen="true" side="left">
            <div class="p-5 space-y-5">
                {{-- Confidence-Verteilung --}}
                <div>
                    <h3 class="text-xs font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Vertrauen in Daten</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center justify-between py-2 px-3 rounded-md bg-emerald-50 border border-emerald-200">
                            <span class="text-emerald-700">Hoch (75–100)</span>
                            <span class="font-bold text-emerald-700">{{ $byConfidence['high_75_100'] }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 px-3 rounded-md bg-amber-50 border border-amber-200">
                            <span class="text-amber-700">Mittel (50–74)</span>
                            <span class="font-bold text-amber-700">{{ $byConfidence['medium_50_74'] }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 px-3 rounded-md bg-orange-50 border border-orange-200">
                            <span class="text-orange-700">Niedrig (25–49)</span>
                            <span class="font-bold text-orange-700">{{ $byConfidence['low_25_49'] }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 px-3 rounded-md bg-zinc-50 border border-zinc-200">
                            <span class="text-zinc-600">Keine (0–24)</span>
                            <span class="font-bold text-zinc-600">{{ $byConfidence['none_0_24'] }}</span>
                        </div>
                    </div>
                </div>

                {{-- Per-Modul-Übersicht --}}
                @if(!empty($perModule))
                    <div>
                        <h3 class="text-xs font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Pro Modul</h3>
                        <div class="space-y-3">
                            @foreach($perModule as $mod => $stats)
                                <div class="p-3 rounded-md border border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-1.5">
                                            @svg($moduleIcon[$mod] ?? 'heroicon-o-cube', 'w-4 h-4 text-[var(--ui-secondary)]')
                                            <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $stats['label'] }}</span>
                                        </div>
                                        <span class="text-xs text-[var(--ui-muted)]">{{ $stats['total'] }}</span>
                                    </div>
                                    @php
                                        $total = max(1, $stats['total']);
                                        $rPct = round($stats['byColor']['red'] / $total * 100);
                                        $yPct = round($stats['byColor']['yellow'] / $total * 100);
                                        $gPct = round($stats['byColor']['green'] / $total * 100);
                                        $xPct = max(0, 100 - $rPct - $yPct - $gPct);
                                    @endphp
                                    <div class="flex h-2 rounded overflow-hidden">
                                        @if($rPct > 0)<div class="bg-rose-500" style="width: {{ $rPct }}%"></div>@endif
                                        @if($yPct > 0)<div class="bg-amber-400" style="width: {{ $yPct }}%"></div>@endif
                                        @if($gPct > 0)<div class="bg-emerald-500" style="width: {{ $gPct }}%"></div>@endif
                                        @if($xPct > 0)<div class="bg-zinc-300" style="width: {{ $xPct }}%"></div>@endif
                                    </div>
                                    @if($stats['avgScore'] !== null)
                                        <div class="text-[10px] text-[var(--ui-muted)] mt-2">
                                            Ø Score: <span class="font-semibold text-[var(--ui-secondary)]">{{ round($stats['avgScore']) }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <div class="p-6 space-y-6" wire:poll.60s>

        {{-- ═══════════ Top-Tiles: globale Ampel-Summen ═══════════ --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-5">
                <div class="text-xs uppercase tracking-wider text-rose-600 font-semibold">Brennt</div>
                <div class="text-4xl font-bold text-rose-600 mt-1">{{ $totalByColor['red'] }}</div>
                <div class="text-xs text-rose-600/70 mt-1">von {{ $totalAll }} insgesamt</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                <div class="text-xs uppercase tracking-wider text-amber-700 font-semibold">Achtung</div>
                <div class="text-4xl font-bold text-amber-700 mt-1">{{ $totalByColor['yellow'] }}</div>
                <div class="text-xs text-amber-700/70 mt-1">brauchen Blick</div>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">
                <div class="text-xs uppercase tracking-wider text-emerald-700 font-semibold">Stabil</div>
                <div class="text-4xl font-bold text-emerald-700 mt-1">{{ $totalByColor['green'] }}</div>
                <div class="text-xs text-emerald-700/70 mt-1">grün</div>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-5">
                <div class="text-xs uppercase tracking-wider text-zinc-600 font-semibold">Ohne Daten</div>
                <div class="text-4xl font-bold text-zinc-600 mt-1">{{ $totalByColor['gray'] }}</div>
                <div class="text-xs text-zinc-600/70 mt-1">Karteileichen</div>
            </div>
        </div>

        {{-- ═══════════ Brennt-Liste ═══════════ --}}
        @if($brennt->isNotEmpty())
            <div class="rounded-lg border border-rose-200 bg-white">
                <div class="px-5 py-3 border-b border-rose-100 bg-rose-50/50 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-rose-700 uppercase tracking-wider inline-flex items-center gap-2">
                        @svg('heroicon-o-fire', 'w-4 h-4')
                        Brennt jetzt
                    </h2>
                    <span class="text-xs text-rose-600/70">{{ $brennt->count() }} rot</span>
                </div>
                <ul class="divide-y divide-rose-100">
                    @foreach($brennt as $s)
                        <li class="px-5 py-3 hover:bg-rose-50/40 transition flex items-center gap-3">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded bg-rose-100">
                                @svg($moduleIcon[$s->module] ?? 'heroicon-o-cube', 'w-3.5 h-3.5 text-rose-600')
                            </span>
                            <div class="flex-1 min-w-0">
                                <a href="{{ $s->container_id ? route($s->container_route, $s->container_id) : '#' }}"
                                   wire:navigate
                                   class="text-sm font-medium text-zinc-900 hover:text-rose-700 truncate">
                                    {{ $s->container_name }}
                                </a>
                                <div class="text-[11px] text-zinc-500 mt-0.5">
                                    {{ $s->module_label }}
                                    @if($s->team_name) · <span class="text-zinc-600">{{ $s->team_name }}</span>@endif
                                    @if($s->worst_axis) · schlimmste Achse: <span class="font-medium">{{ $s->worst_axis }}</span>@endif
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xl font-bold text-rose-600">{{ $s->health_score ?? '—' }}</div>
                                @if($s->delta_health_score !== null && $s->delta_health_score < 0)
                                    <div class="text-[10px] text-rose-600 font-semibold">{{ $s->delta_health_score }}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ═══════════ Bewegung: Gewinner + Verlierer ═══════════ --}}
        @if($gewinner->isNotEmpty() || $verlierer->isNotEmpty())
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Gewinner --}}
                <div class="rounded-lg border border-emerald-200 bg-white">
                    <div class="px-5 py-3 border-b border-emerald-100 bg-emerald-50/50">
                        <h2 class="text-sm font-bold text-emerald-700 uppercase tracking-wider inline-flex items-center gap-2">
                            @svg('heroicon-o-arrow-trending-up', 'w-4 h-4')
                            Bewegung nach oben
                        </h2>
                    </div>
                    @if($gewinner->isEmpty())
                        <div class="px-5 py-6 text-center text-sm text-zinc-400">Keine Aufsteiger</div>
                    @else
                        <ul class="divide-y divide-emerald-50">
                            @foreach($gewinner as $s)
                                <li class="px-5 py-3 flex items-center gap-3">
                                    @svg($moduleIcon[$s->module] ?? 'heroicon-o-cube', 'w-4 h-4 text-emerald-600 flex-shrink-0')
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-zinc-900 truncate">{{ $s->container_name }}</div>
                                        <div class="text-[11px] text-zinc-500">{{ $s->module_label }}@if($s->team_name) · {{ $s->team_name }}@endif</div>
                                    </div>
                                    <div class="text-sm font-bold text-emerald-600">+{{ $s->delta_health_score }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Verlierer --}}
                <div class="rounded-lg border border-rose-200 bg-white">
                    <div class="px-5 py-3 border-b border-rose-100 bg-rose-50/50">
                        <h2 class="text-sm font-bold text-rose-700 uppercase tracking-wider inline-flex items-center gap-2">
                            @svg('heroicon-o-arrow-trending-down', 'w-4 h-4')
                            Bewegung nach unten
                        </h2>
                    </div>
                    @if($verlierer->isEmpty())
                        <div class="px-5 py-6 text-center text-sm text-zinc-400">Keine Absteiger</div>
                    @else
                        <ul class="divide-y divide-rose-50">
                            @foreach($verlierer as $s)
                                <li class="px-5 py-3 flex items-center gap-3">
                                    @svg($moduleIcon[$s->module] ?? 'heroicon-o-cube', 'w-4 h-4 text-rose-600 flex-shrink-0')
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-zinc-900 truncate">{{ $s->container_name }}</div>
                                        <div class="text-[11px] text-zinc-500">{{ $s->module_label }}@if($s->team_name) · {{ $s->team_name }}@endif</div>
                                    </div>
                                    <div class="text-sm font-bold text-rose-600">{{ $s->delta_health_score }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        {{-- ═══════════ Karteileichen ═══════════ --}}
        @if($karteileichen->isNotEmpty())
            <div class="rounded-lg border border-zinc-200 bg-white">
                <div class="px-5 py-3 border-b border-zinc-100 bg-zinc-50/50 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-zinc-600 uppercase tracking-wider inline-flex items-center gap-2">
                        @svg('heroicon-o-question-mark-circle', 'w-4 h-4')
                        Karteileichen
                    </h2>
                    <span class="text-xs text-zinc-500">{{ $karteileichen->count() }} ohne belastbare Daten</span>
                </div>
                <ul class="divide-y divide-zinc-100">
                    @foreach($karteileichen as $s)
                        <li class="px-5 py-2.5 flex items-center gap-3">
                            @svg($moduleIcon[$s->module] ?? 'heroicon-o-cube', 'w-4 h-4 text-zinc-400 flex-shrink-0')
                            <div class="flex-1 min-w-0">
                                <div class="text-sm text-zinc-700 truncate">{{ $s->container_name }}</div>
                                <div class="text-[11px] text-zinc-400">
                                    {{ $s->module_label }}@if($s->team_name) · {{ $s->team_name }}@endif · {{ $s->confidence_reason ?? 'keine Begründung' }}
                                </div>
                            </div>
                            <div class="text-xs text-zinc-500">Conf {{ $s->confidence_score }}</div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Empty state --}}
        @if($totalAll === 0)
            <div class="rounded-lg border border-[var(--ui-border)] bg-white p-12 text-center">
                @svg('heroicon-o-signal-slash', 'w-12 h-12 text-zinc-300 mx-auto')
                <h3 class="mt-4 text-lg font-semibold text-zinc-700">Noch keine Snapshots</h3>
                <p class="mt-1 text-sm text-zinc-500">Sobald Planner-, Helpdesk- oder Dev-Snapshots laufen, erscheint hier die Live-Lage.</p>
            </div>
        @endif

    </div>
</x-ui-page>
