<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'icon' => 'building-office'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Übersicht" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                @php $time = $this->teamTimeAnalytics; @endphp
                @if($time['has_data'])
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Zeiterfassung</h3>
                        <div class="space-y-3">
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                                <span class="text-xs text-[var(--ui-muted)]">Stunden (Monat)</span>
                                <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $time['hours_this_month'] }}</span>
                            </div>
                            @php $summary = $this->teamSnapshotSummary; @endphp
                            @if($summary['has_data'])
                                <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                                    <span class="text-xs text-[var(--ui-muted)]">Items erledigt</span>
                                    <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $summary['items_done'] }}</span>
                                </div>
                                <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                                    <span class="text-xs text-[var(--ui-muted)]">Abrechnungsquote</span>
                                    <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $time['billing_rate'] }}%</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @php $linkDist = $this->linkTypeDistribution; @endphp
                @if(count($linkDist) > 0)
                    <div>
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Verknüpfungs-Typen</h3>
                        <div class="space-y-2">
                            @foreach($linkDist as $dist)
                                <div class="flex items-center gap-3 py-2 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                    <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                                        @svg('heroicon-o-' . $dist['icon'], 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                                    </div>
                                    <span class="text-xs text-[var(--ui-muted)] flex-1 truncate">{{ $dist['label'] }}</span>
                                    <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $dist['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <livewire:organization.activity-feed />
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        @php
            $insights = $this->insightStatements;
            $summary = $this->teamSnapshotSummary;
            $health = $this->entityHealthOverview;
            $time = $this->teamTimeAnalytics;
            $velocity = $this->completionVelocity;
            $trend = $this->teamSnapshotTrend;
            $linkDist = $this->linkTypeDistribution;
            $topEntities = $this->topEntitiesByActivity;
            $personOverview = $this->personOverview;
            $signalOverview = $this->signalOverview;
        @endphp

        {{-- ============================================================ --}}
        {{-- TIER 1: Handlungsbedarf                                      --}}
        {{-- ============================================================ --}}
        @php
            $hasSignals = $signalOverview['total_open'] > 0;
            $hasProblems = count($health['problems']) > 0;
            $hasPersonWarnings = count($personOverview['persons']) > 0;
            $hasHandlungsbedarf = $hasSignals || $hasProblems || $hasPersonWarnings;
        @endphp

        @if($hasHandlungsbedarf)
            <x-ui-panel title="Handlungsbedarf" subtitle="Offene Punkte die Aufmerksamkeit erfordern" class="mb-8">

                {{-- Subsektion A: Offene Signale --}}
                @if($hasSignals)
                    <div class="flex items-center gap-2 mb-3">
                        @svg('heroicon-o-bell-alert', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">Signale</span>
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                            {{ $signalOverview['total_open'] }}
                        </span>
                    </div>
                    <div class="space-y-2 mb-2">
                        @foreach($signalOverview['signals'] as $signal)
                            <div class="flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium flex-shrink-0
                                        @if($signal->severity === 'critical') bg-red-100 text-red-800
                                        @elseif($signal->severity === 'warning') bg-amber-100 text-amber-800
                                        @else bg-blue-100 text-blue-800
                                        @endif
                                    ">
                                        {{ ucfirst($signal->severity) }}
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('organization.signals.show', $signal) }}"
                                           class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate block">
                                            {{ $signal->definition?->name ?? 'Signal' }}
                                        </a>
                                        <div class="text-xs text-[var(--ui-muted)]">
                                            {{ $signal->entity?->name ?? '' }}
                                            &middot; {{ $signal->created_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium flex-shrink-0
                                    @if($signal->status === 'open') bg-yellow-100 text-yellow-800
                                    @else bg-blue-100 text-blue-800
                                    @endif
                                ">
                                    {{ $signal->status === 'open' ? 'Offen' : 'Bestätigt' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Separator --}}
                @if($hasSignals && ($hasProblems || $hasPersonWarnings))
                    <hr class="border-[var(--ui-border)]/40 my-4">
                @endif

                {{-- Subsektion B: Gefährdete Einheiten --}}
                @if($hasProblems)
                    <div class="flex items-center gap-2 mb-3">
                        @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">Einheiten</span>
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            {{ count($health['problems']) }}
                        </span>
                    </div>
                    <div class="space-y-3 mb-2">
                        @foreach($health['problems'] as $problem)
                            <div class="flex items-center gap-3 p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <a href="{{ route('organization.entities.show', $problem['id']) }}"
                                           class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate">
                                            {{ $problem['name'] }}
                                        </a>
                                        <x-ui-badge variant="secondary" size="xs">{{ $problem['type_name'] }}</x-ui-badge>
                                        @if($problem['status'] === 'at_risk')
                                            <x-ui-badge variant="danger" size="xs">gefährdet</x-ui-badge>
                                        @else
                                            <x-ui-badge variant="warning" size="xs">stagnierend</x-ui-badge>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1">
                                            <div class="w-full bg-[var(--ui-muted-5)] rounded-full h-1.5">
                                                <div class="h-1.5 rounded-full {{ $problem['completion_pct'] >= 100 ? 'bg-green-500' : ($problem['completion_pct'] >= 50 ? 'bg-blue-500' : 'bg-amber-500') }}" style="width: {{ min($problem['completion_pct'], 100) }}%"></div>
                                            </div>
                                        </div>
                                        <span class="text-xs text-[var(--ui-muted)] whitespace-nowrap">
                                            {{ $problem['completion_pct'] }}% ({{ $problem['items_done'] }}/{{ $problem['items_total'] }})
                                        </span>
                                    </div>
                                    <p class="text-xs text-[var(--ui-muted)] mt-1">
                                        @if($problem['status'] === 'at_risk')
                                            Scope gewachsen ohne Fortschritt ({{ $problem['open_items'] }} offene Items)
                                        @else
                                            Seit 7 Tagen kein Fortschritt ({{ $problem['open_items'] }} offene Items)
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Separator --}}
                @if($hasProblems && $hasPersonWarnings)
                    <hr class="border-[var(--ui-border)]/40 my-4">
                @endif

                {{-- Subsektion C: Personen-Warnungen --}}
                @if($hasPersonWarnings)
                    <div class="flex items-center gap-2 mb-3">
                        @svg('heroicon-o-users', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">Personen</span>
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                            {{ count($personOverview['persons']) }}
                        </span>
                    </div>
                    <div class="space-y-2">
                        @foreach($personOverview['persons'] as $person)
                            <div class="flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 flex items-center justify-center">
                                        @svg('heroicon-o-user', 'w-4 h-4 text-[var(--ui-muted)]')
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('organization.entities.show', $person['id']) }}"
                                           class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate block">
                                            {{ $person['name'] }}
                                        </a>
                                        <div class="text-xs text-[var(--ui-muted)]">{{ $person['type_name'] }}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    @foreach($personOverview['metric_configs'] as $snapshotKey => $mCfg)
                                        @if(($person['metrics'][$snapshotKey] ?? 0) > 0 && in_array($mCfg['type'], ['warning', 'danger']))
                                            <span class="text-xs font-medium {{ $mCfg['type'] === 'danger' ? 'text-red-600' : 'text-amber-600' }}">
                                                {{ $person['metrics'][$snapshotKey] }} {{ $mCfg['label'] }}
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

            </x-ui-panel>
        @endif

        {{-- ============================================================ --}}
        {{-- TIER 2: Team-Status                                          --}}
        {{-- ============================================================ --}}

        {{-- 2a. Insight Banner --}}
        @if(count($insights) > 0)
            <x-ui-panel class="mb-6">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-center flex-shrink-0 mt-0.5">
                        @svg('heroicon-o-light-bulb', 'w-4 h-4 text-amber-500')
                    </div>
                    <div class="space-y-1.5">
                        @foreach($insights as $insight)
                            <p class="text-sm
                                @if($insight['type'] === 'success') text-green-700
                                @elseif($insight['type'] === 'warning') text-amber-700
                                @else text-[var(--ui-secondary)]
                                @endif
                            ">
                                @if($insight['type'] === 'success')
                                    @svg('heroicon-o-arrow-trending-up', 'w-4 h-4 inline-block -mt-0.5 mr-1')
                                @elseif($insight['type'] === 'warning')
                                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 inline-block -mt-0.5 mr-1')
                                @else
                                    @svg('heroicon-o-information-circle', 'w-4 h-4 inline-block -mt-0.5 mr-1')
                                @endif
                                {{ $insight['text'] }}
                            </p>
                        @endforeach
                    </div>
                </div>
            </x-ui-panel>
        @endif

        {{-- 2b. KPI Tiles --}}
        @php
            $signalCriticalCount = $signalOverview['signals']->where('severity', 'critical')->count();
            $signalVariant = $signalCriticalCount > 0 ? 'danger' : ($signalOverview['total_open'] > 0 ? 'warning' : 'success');
            $signalDescription = $signalCriticalCount > 0
                ? $signalCriticalCount . ' kritisch'
                : ($signalOverview['total_open'] === 0 ? 'Keine offenen Signale' : $signalOverview['total_open'] . ' offen');
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <x-ui-dashboard-tile
                title="Aktive Einheiten"
                :count="$this->activeEntities"
                :description="$this->totalEntities . ' gesamt'"
                icon="building-office"
                variant="primary"
                :href="route('organization.entities.index')"
            />

            @if($summary['has_data'])
                <x-ui-dashboard-tile
                    title="Fortschritt"
                    :count="$summary['completion_rate']"
                    :description="$summary['completion_rate'] . '% abgeschlossen'"
                    icon="check-circle"
                    :variant="$summary['completion_rate'] >= 50 ? 'success' : 'warning'"
                    :trend="$summary['trend_completion'] > 0 ? 'up' : ($summary['trend_completion'] < 0 ? 'down' : null)"
                    :trendValue="$summary['trend_completion'] != 0 ? ($summary['trend_completion'] > 0 ? '+' : '') . $summary['trend_completion'] . '% vs. 7d' : null"
                />
            @else
                <x-ui-dashboard-tile
                    title="Fortschritt"
                    :count="0"
                    icon="check-circle"
                    variant="secondary"
                />
            @endif

            @if($time['has_data'])
                <x-ui-dashboard-tile
                    title="Stunden (Monat)"
                    :count="$time['hours_this_month']"
                    icon="clock"
                    variant="secondary"
                    :trend="$time['trend_hours'] > 0 ? 'up' : ($time['trend_hours'] < 0 ? 'down' : null)"
                    :trendValue="$time['trend_hours'] != 0 ? ($time['trend_hours'] > 0 ? '+' : '') . $time['trend_hours'] . 'h vs. Vormonat' : null"
                />
            @else
                <x-ui-dashboard-tile title="Stunden (Monat)" :count="0" icon="clock" variant="secondary" />
            @endif

            <x-ui-dashboard-tile
                title="Offene Signale"
                :count="$signalOverview['total_open']"
                :description="$signalDescription"
                icon="bell-alert"
                :variant="$signalVariant"
            />
        </div>

        {{-- 2c. Entwicklung (Tabbed: Trend + Bewegung) --}}
        @php
            $hasTrend = !empty($trend) && count($trend['snapshots'] ?? []) >= 1;
            $teamMovement = $this->teamMovement;
            $hasMovement = !empty($teamMovement['metrics']);
        @endphp

        @if($hasTrend || $hasMovement)
            <x-ui-panel title="Entwicklung" class="mb-8" x-data="{ tab: '{{ $hasTrend ? 'trend' : 'movement' }}' }">
                {{-- Tab Buttons --}}
                <div class="flex items-center gap-2 mb-4">
                    @if($hasTrend)
                        <button @click="tab = 'trend'"
                            :class="tab === 'trend' ? 'bg-[var(--ui-primary)] text-white' : 'text-[var(--ui-muted)] hover:text-[var(--ui-text)]'"
                            class="px-3 py-1.5 text-xs font-medium rounded transition-colors">
                            14-Tage Trend
                        </button>
                    @endif
                    @if($hasMovement)
                        <button @click="tab = 'movement'"
                            :class="tab === 'movement' ? 'bg-[var(--ui-primary)] text-white' : 'text-[var(--ui-muted)] hover:text-[var(--ui-text)]'"
                            class="px-3 py-1.5 text-xs font-medium rounded transition-colors">
                            Bewegung (7d)
                        </button>
                    @endif
                </div>

                {{-- Tab: Trend --}}
                @if($hasTrend)
                    <div x-show="tab === 'trend'" x-cloak>
                        <div class="flex items-center justify-end gap-3 mb-4">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                <span class="text-[10px] text-[var(--ui-muted)]">Items erledigt</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-blue-200"></span>
                                <span class="text-[10px] text-[var(--ui-muted)]">Items gesamt</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-violet-400"></span>
                                <span class="text-[10px] text-[var(--ui-muted)]">Stunden</span>
                            </div>
                        </div>

                        <div class="flex items-end gap-1" style="height: 160px;" x-data="{ tooltip: null }">
                            @foreach($trend['snapshots'] as $idx => $snap)
                                @php
                                    $maxItems = max($trend['max_items_total'], 1);
                                    $maxMin = max($trend['max_minutes'], 1);
                                    $totalH = round(($snap['items_total'] / $maxItems) * 130);
                                    $doneH = $snap['items_total'] > 0 ? max(1, round(($snap['items_done'] / $maxItems) * 130)) : 0;
                                    $timeH = $snap['time_total_minutes'] > 0 ? max(2, round(($snap['time_total_minutes'] / $maxMin) * 130)) : 0;
                                @endphp
                                <div class="flex-1 flex flex-col items-center h-full justify-end gap-px relative"
                                     @mouseenter="tooltip = {{ $idx }}"
                                     @mouseleave="tooltip = null">
                                    <div x-show="tooltip === {{ $idx }}" x-cloak
                                         class="absolute bottom-full mb-2 px-2.5 py-1.5 rounded-lg bg-[var(--ui-secondary)] text-white text-[10px] whitespace-nowrap z-10 shadow-lg pointer-events-none"
                                         x-transition.opacity>
                                        {{ $snap['date'] }}: {{ $snap['items_done'] }}/{{ $snap['items_total'] }} Items,
                                        {{ intdiv($snap['time_total_minutes'], 60) }}:{{ str_pad($snap['time_total_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h
                                    </div>

                                    <div class="w-full flex gap-px justify-center flex-1 items-end">
                                        <div class="flex-1 flex flex-col justify-end">
                                            @if($snap['items_total'] > 0)
                                                <div class="w-full bg-blue-200 rounded-t" style="height: {{ $totalH }}px;">
                                                    <div class="w-full bg-blue-500 rounded-t" style="height: {{ $doneH }}px;"></div>
                                                </div>
                                            @else
                                                <div class="w-full bg-[var(--ui-border)]/20 rounded-t" style="height: 1px;"></div>
                                            @endif
                                        </div>
                                        <div class="flex-1 flex flex-col justify-end">
                                            @if($snap['time_total_minutes'] > 0)
                                                <div class="w-full bg-violet-400 rounded-t" style="height: {{ $timeH }}px;"></div>
                                            @else
                                                <div class="w-full bg-[var(--ui-border)]/20 rounded-t" style="height: 1px;"></div>
                                            @endif
                                        </div>
                                    </div>
                                    @if($idx === 0 || $idx === count($trend['snapshots']) - 1 || $idx % 3 === 0)
                                        <div class="text-[9px] text-[var(--ui-muted)] mt-0.5 leading-none">{{ $snap['date'] }}</div>
                                    @else
                                        <div class="h-[11px]"></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if($velocity['avg_per_week'] > 0)
                            <div class="mt-3 pt-3 border-t border-[var(--ui-border)]/40">
                                <p class="text-xs text-[var(--ui-muted)]">
                                    @svg('heroicon-o-arrow-trending-up', 'w-3.5 h-3.5 inline-block -mt-0.5 mr-1')
                                    Erledigungsrate: Ø {{ $velocity['avg_per_week'] }} Items/Woche
                                    @if($velocity['trend'] === 'accelerating')
                                        <span class="text-green-600">(beschleunigend)</span>
                                    @elseif($velocity['trend'] === 'decelerating')
                                        <span class="text-amber-600">(verlangsamend)</span>
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Tab: Movement --}}
                @if($hasMovement)
                    <div x-show="tab === 'movement'" x-cloak>
                        <div class="flex gap-1 mb-4">
                            <button wire:click="$set('dashboardStream', null)"
                                class="px-2 py-1 text-[10px] rounded transition-colors {{ !$dashboardStream ? 'bg-[var(--ui-primary)] text-white' : 'text-[var(--ui-muted)] hover:text-[var(--ui-text)]' }}">
                                Alle
                            </button>
                            @foreach($teamMovement['available_groups'] as $groupKey => $groupLabel)
                                <button wire:click="$set('dashboardStream', '{{ $groupKey }}')"
                                    class="px-2 py-1 text-[10px] rounded transition-colors {{ $dashboardStream === $groupKey ? 'bg-[var(--ui-primary)] text-white' : 'text-[var(--ui-muted)] hover:text-[var(--ui-text)]' }}">
                                    {{ $groupLabel }}
                                </button>
                            @endforeach
                        </div>

                        @foreach($teamMovement['metrics_by_group'] as $groupKey => $metrics)
                            <div class="mb-3">
                                <div class="text-[10px] font-medium text-[var(--ui-muted)] uppercase mb-1.5">
                                    {{ ucfirst($groupKey) }}
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                    @foreach($metrics as $m)
                                        @if($m['current'] > 0 || $m['previous'] > 0)
                                            <div class="py-2 px-2.5 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/20">
                                                <div class="text-sm font-bold text-[var(--ui-text)]">
                                                    {{ $m['current'] }}
                                                    @if($m['delta'] != 0)
                                                        <span class="text-[10px] ml-1
                                                            {{ $m['sentiment'] === 'positive' ? 'text-green-600' : '' }}
                                                            {{ $m['sentiment'] === 'negative' ? 'text-red-600' : '' }}
                                                            {{ $m['sentiment'] === 'neutral' ? 'text-[var(--ui-muted)]' : '' }}
                                                        ">{{ $m['delta_formatted'] }}</span>
                                                    @endif
                                                </div>
                                                <div class="text-[10px] text-[var(--ui-muted)]">{{ $m['label'] }}</div>
                                                @if($m['ratio'])
                                                    <div class="mt-1 h-1 bg-[var(--ui-border)]/30 rounded-full overflow-hidden">
                                                        <div class="h-full bg-blue-500 rounded-full" style="width: {{ min($m['ratio'], 100) }}%"></div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui-panel>
        @endif

        {{-- ============================================================ --}}
        {{-- TIER 3: Kontext                                              --}}
        {{-- ============================================================ --}}

        @if(count($topEntities) > 0)
            <x-ui-panel title="Top Einheiten" subtitle="Aktivste (7 Tage)">
                <div class="space-y-2">
                    @foreach($topEntities as $top)
                        <div class="flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="min-w-0">
                                    <a href="{{ route('organization.entities.show', $top['id']) }}"
                                       class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate block">
                                        {{ $top['name'] }}
                                    </a>
                                    <div class="text-xs text-[var(--ui-muted)]">{{ $top['type_name'] }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0 text-right">
                                @if($top['items_completed_7d'] > 0)
                                    <span class="text-xs text-green-600 font-medium">{{ $top['items_completed_7d'] }} Items</span>
                                @endif
                                @if($top['hours_7d'] > 0)
                                    <span class="text-xs text-[var(--ui-muted)]">{{ $top['hours_7d'] }}h</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui-panel>
        @endif
    </x-ui-page-container>
</x-ui-page>
