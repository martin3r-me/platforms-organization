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
            $focusedSignals = $this->focusedSignals;

            $hasSignals = ($signalOverview['total_open'] ?? 0) > 0;
            $hasProblems = count($health['problems'] ?? []) > 0;
            $hasPersonWarnings = count($personOverview['persons'] ?? []) > 0;
            $hasHandlungsbedarf = $hasSignals || $hasProblems || $hasPersonWarnings;
        @endphp

        {{-- ============================================================ --}}
        {{-- FOKUS-SIGNALE (persönlich, oberste Priorität)                --}}
        {{-- ============================================================ --}}
        @if($focusedSignals->isNotEmpty())
            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50/40 p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-s-star', 'w-5 h-5 text-amber-500')
                        <h2 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">Fokus-Signale</h2>
                        <span class="text-xs text-[var(--ui-muted)]">({{ $focusedSignals->count() }})</span>
                    </div>
                    <a href="{{ route('organization.signals.index', ['focusOnly' => 1]) }}" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] inline-flex items-center gap-1">
                        Alle anzeigen
                        @svg('heroicon-o-arrow-right', 'w-3 h-3')
                    </a>
                </div>
                <div class="space-y-2">
                    @foreach($focusedSignals as $signal)
                        <div class="flex items-start gap-3 bg-white rounded-md border border-amber-200/60 p-3 hover:border-amber-400 transition">
                            <button
                                wire:click="unfocusSignal({{ $signal->id }})"
                                type="button"
                                class="flex-shrink-0 mt-0.5 text-amber-500 hover:text-amber-700 transition"
                                title="Aus Fokus entfernen"
                            >
                                @svg('heroicon-s-star', 'w-4 h-4')
                            </button>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    @if($signal->entity)
                                        <a href="{{ route('organization.entities.show', $signal->entity) }}" class="link text-sm font-medium">
                                            {{ $signal->entity->name }}
                                        </a>
                                    @endif
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium
                                        @if($signal->severity === 'critical' || $signal->severity === 'algedonic') bg-red-100 text-red-800
                                        @elseif($signal->severity === 'warning') bg-amber-100 text-amber-800
                                        @else bg-blue-100 text-blue-800
                                        @endif
                                    ">{{ ucfirst($signal->severity) }}</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium
                                        @if($signal->status === 'open') bg-yellow-100 text-yellow-800
                                        @elseif($signal->status === 'acknowledged') bg-blue-100 text-blue-800
                                        @elseif($signal->status === 'resolved') bg-green-100 text-green-800
                                        @else bg-gray-100 text-gray-600
                                        @endif
                                    ">
                                        @switch($signal->status)
                                            @case('open') Offen @break
                                            @case('acknowledged') Bestätigt @break
                                            @case('resolved') Gelöst @break
                                            @case('dismissed') Verworfen @break
                                        @endswitch
                                    </span>
                                </div>
                                <a href="{{ route('organization.signals.show', $signal) }}" class="text-sm text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] block">
                                    {{ \Illuminate\Support\Str::limit($signal->message, 140) }}
                                </a>
                                <p class="text-xs text-[var(--ui-muted)] mt-1">{{ $signal->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- ALERT BAR: Handlungsbedarf (compact)                         --}}
        {{-- ============================================================ --}}
        @if($hasHandlungsbedarf)
            <div class="mb-6 rounded-lg border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-4" x-data="{ expanded: false }">
                <div class="flex items-center justify-between cursor-pointer" @click="expanded = !expanded">
                    <div class="flex items-center gap-3">
                        @svg('heroicon-o-exclamation-triangle', 'w-5 h-5 text-amber-500 flex-shrink-0')
                        <span class="text-sm font-semibold text-amber-800">Handlungsbedarf</span>
                        <div class="flex items-center gap-2">
                            @if($hasSignals)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                                    @svg('heroicon-o-bell-alert', 'w-3 h-3')
                                    {{ $signalOverview['total_open'] }} Signale
                                </span>
                            @endif
                            @if($hasProblems)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                    @svg('heroicon-o-exclamation-triangle', 'w-3 h-3')
                                    {{ count($health['problems']) }} Einheiten
                                </span>
                            @endif
                            @if($hasPersonWarnings)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200">
                                    @svg('heroicon-o-users', 'w-3 h-3')
                                    {{ count($personOverview['persons']) }} Personen
                                </span>
                            @endif
                        </div>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        class="w-4 h-4 text-amber-500 transition-transform duration-200"
                        :class="{ 'rotate-180': expanded }">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </div>

                {{-- Expanded Details --}}
                <div x-show="expanded" x-collapse x-cloak class="mt-4 space-y-4">
                    {{-- Offene Signale --}}
                    @if($hasSignals)
                        <div>
                            <div class="text-xs font-medium text-amber-700 uppercase tracking-wider mb-2">Signale</div>
                            <div class="space-y-1.5">
                                @foreach($signalOverview['signals'] as $signal)
                                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-white/60 border border-amber-100">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium flex-shrink-0
                                                @if($signal->severity === 'critical') bg-red-100 text-red-800
                                                @elseif($signal->severity === 'warning') bg-amber-100 text-amber-800
                                                @else bg-blue-100 text-blue-800
                                                @endif
                                            ">{{ ucfirst($signal->severity) }}</span>
                                            <a href="{{ route('organization.signals.show', $signal) }}"
                                               class="text-sm text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate">
                                                {{ $signal->definition?->name ?? 'Signal' }}
                                            </a>
                                            <span class="text-xs text-[var(--ui-muted)]">{{ $signal->entity?->name ?? '' }}</span>
                                        </div>
                                        <span class="text-xs text-[var(--ui-muted)] flex-shrink-0">{{ $signal->created_at->diffForHumans() }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Gefährdete Einheiten --}}
                    @if($hasProblems)
                        <div>
                            <div class="text-xs font-medium text-red-700 uppercase tracking-wider mb-2">Gefährdete Einheiten</div>
                            <div class="space-y-1.5">
                                @foreach($health['problems'] as $problem)
                                    <div class="flex items-center gap-3 py-2 px-3 rounded-lg bg-white/60 border border-amber-100">
                                        <a href="{{ route('organization.entities.show', $problem['id']) }}"
                                           class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate">
                                            {{ $problem['name'] }}
                                        </a>
                                        @if($problem['status'] === 'at_risk')
                                            <x-ui-badge variant="danger" size="xs">gefährdet</x-ui-badge>
                                        @else
                                            <x-ui-badge variant="warning" size="xs">stagnierend</x-ui-badge>
                                        @endif
                                        <div class="ml-auto flex items-center gap-2 flex-shrink-0">
                                            <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-full {{ $problem['completion_pct'] >= 50 ? 'bg-blue-500' : 'bg-amber-500' }} rounded-full" style="width: {{ min($problem['completion_pct'], 100) }}%"></div>
                                            </div>
                                            <span class="text-xs text-[var(--ui-muted)]">{{ $problem['completion_pct'] }}%</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Personen-Warnungen --}}
                    @if($hasPersonWarnings)
                        <div>
                            <div class="text-xs font-medium text-orange-700 uppercase tracking-wider mb-2">Personen</div>
                            <div class="space-y-1.5">
                                @foreach($personOverview['persons'] as $person)
                                    <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-white/60 border border-amber-100">
                                        <a href="{{ route('organization.entities.show', $person['id']) }}"
                                           class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate">
                                            {{ $person['name'] }}
                                        </a>
                                        <div class="flex items-center gap-2 flex-shrink-0">
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
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- QUICK ACCESS: Top Einheiten + KPIs                           --}}
        {{-- ============================================================ --}}

        {{-- Top Einheiten (prominenter Quick-Access) --}}
        @if(count($topEntities) > 0)
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-3">
                    @svg('heroicon-o-bolt', 'w-4 h-4 text-[var(--ui-primary)]')
                    <h3 class="text-sm font-semibold text-[var(--ui-secondary)]">Aktive Einheiten</h3>
                    <span class="text-xs text-[var(--ui-muted)]">(7 Tage)</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($topEntities as $top)
                        <a href="{{ route('organization.entities.show', $top['id']) }}"
                           class="flex items-center gap-3 p-3 rounded-lg border border-[var(--ui-border)]/60 bg-white hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors group">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-[var(--ui-secondary)] group-hover:text-[var(--ui-primary)] truncate">{{ $top['name'] }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">{{ $top['type_name'] }}</div>
                            </div>
                            <div class="flex flex-col items-end flex-shrink-0">
                                @if($top['items_completed_7d'] > 0)
                                    <span class="text-xs text-green-600 font-medium">{{ $top['items_completed_7d'] }} Items</span>
                                @endif
                                @if($top['hours_7d'] > 0)
                                    <span class="text-[10px] text-[var(--ui-muted)]">{{ $top['hours_7d'] }}h</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- KPI Tiles --}}
        @php
            $signalCriticalCount = $signalOverview['signals']->where('severity', 'critical')->count();
            $signalVariant = $signalCriticalCount > 0 ? 'danger' : ($signalOverview['total_open'] > 0 ? 'warning' : 'success');
            $signalDescription = $signalCriticalCount > 0
                ? $signalCriticalCount . ' kritisch'
                : ($signalOverview['total_open'] === 0 ? 'Keine offenen Signale' : $signalOverview['total_open'] . ' offen');
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
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

        {{-- Insight Banner (compact) --}}
        @if(count($insights) > 0)
            <div class="mb-6 p-4 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40">
                <div class="flex items-start gap-3">
                    @svg('heroicon-o-light-bulb', 'w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5')
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        @foreach($insights as $insight)
                            <span class="text-sm
                                @if($insight['type'] === 'success') text-green-700
                                @elseif($insight['type'] === 'warning') text-amber-700
                                @else text-[var(--ui-secondary)]
                                @endif
                            ">
                                @if($insight['type'] === 'success')
                                    @svg('heroicon-o-arrow-trending-up', 'w-3.5 h-3.5 inline-block -mt-0.5 mr-0.5')
                                @elseif($insight['type'] === 'warning')
                                    @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5 inline-block -mt-0.5 mr-0.5')
                                @else
                                    @svg('heroicon-o-information-circle', 'w-3.5 h-3.5 inline-block -mt-0.5 mr-0.5')
                                @endif
                                {{ $insight['text'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- ============================================================ --}}
        {{-- ANALYSE: Trends + Bewegung + Umwelt (collapsible)            --}}
        {{-- ============================================================ --}}
        @php
            $hasTrend = !empty($trend) && count($trend['snapshots'] ?? []) >= 1;
            $teamMovement = $this->teamMovement;
            $hasMovement = !empty($teamMovement['metrics']);
            $envRadar = $this->environmentRadar;
        @endphp

        @if($hasTrend || $hasMovement || $envRadar['has_data'])
            <div x-data="{ analyseOpen: false }">
                <button @click="analyseOpen = !analyseOpen"
                    class="w-full flex items-center justify-between p-4 rounded-lg border border-[var(--ui-border)]/60 bg-white hover:bg-[var(--ui-muted-5)] transition-colors mb-4">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-chart-bar-square', 'w-4 h-4 text-[var(--ui-primary)]')
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">Analyse & Trends</span>
                        <span class="text-xs text-[var(--ui-muted)]">
                            @if($hasTrend) 14d-Trend @endif
                            @if($hasMovement) &middot; Bewegung @endif
                            @if($envRadar['has_data']) &middot; Umwelt @endif
                        </span>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                        :class="{ 'rotate-180': analyseOpen }">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <div x-show="analyseOpen" x-collapse x-cloak class="space-y-6">
                    {{-- Entwicklung (Trend + Bewegung) --}}
                    @if($hasTrend || $hasMovement)
                        <x-ui-panel title="Entwicklung" x-data="{ tab: '{{ $hasTrend ? 'trend' : 'movement' }}' }">
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

                    {{-- Umwelt-Radar --}}
                    @if($envRadar['has_data'])
                        <x-ui-panel title="Umwelt-Radar" subtitle="Externe Signale">
                            <div class="space-y-2">
                                @foreach($envRadar['sources'] as $src)
                                    <div class="p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)]">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            @php
                                                $badgeColors = [
                                                    'indigo' => 'bg-indigo-100 text-indigo-700',
                                                    'cyan' => 'bg-cyan-100 text-cyan-700',
                                                    'amber' => 'bg-amber-100 text-amber-700',
                                                    'rose' => 'bg-rose-100 text-rose-700',
                                                    'red' => 'bg-red-100 text-red-700',
                                                    'sky' => 'bg-sky-100 text-sky-700',
                                                    'orange' => 'bg-orange-100 text-orange-700',
                                                    'gray' => 'bg-gray-100 text-gray-700',
                                                ];
                                                $bc = $badgeColors[$src['category_color']] ?? $badgeColors['gray'];
                                            @endphp
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $bc }}">
                                                {{ ucfirst($src['category']) }}
                                            </span>
                                            <span class="text-sm font-medium text-[var(--ui-secondary)] truncate flex-1">{{ $src['name'] }}</span>
                                            @if($src['sentiment_score'] !== null)
                                                @php
                                                    $sentColor = $src['sentiment_score'] >= 0.6 ? 'bg-green-500' : ($src['sentiment_score'] >= 0.3 ? 'bg-yellow-500' : 'bg-red-500');
                                                @endphp
                                                <span class="w-2.5 h-2.5 rounded-full {{ $sentColor }} shrink-0" title="Sentiment: {{ round($src['sentiment_score'] * 100) }}%"></span>
                                            @endif
                                            @if($src['delta'] && $src['delta']['sentiment_change'] != 0)
                                                @if($src['delta']['sentiment_change'] > 0)
                                                    <span class="text-green-500 text-xs">&#9650;</span>
                                                @else
                                                    <span class="text-red-500 text-xs">&#9660;</span>
                                                @endif
                                            @endif
                                        </div>

                                        @if($src['relevance_score'] !== null)
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <span class="text-[10px] text-[var(--ui-muted)] w-12 shrink-0">Relevanz</span>
                                                <div class="flex-1 h-1 bg-[var(--ui-border)]/30 rounded-full overflow-hidden">
                                                    <div class="h-full bg-blue-500 rounded-full" style="width: {{ min(round($src['relevance_score'] * 100), 100) }}%"></div>
                                                </div>
                                                <span class="text-[10px] text-[var(--ui-muted)] tabular-nums">{{ round($src['relevance_score'] * 100) }}%</span>
                                            </div>
                                        @endif

                                        @if($src['summary'])
                                            <p class="text-xs text-[var(--ui-muted)] line-clamp-2 mb-1.5">{{ $src['summary'] }}</p>
                                        @endif

                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex flex-wrap gap-1 min-w-0">
                                                @foreach(array_slice($src['topics'] ?? [], 0, 4) as $topic)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-[var(--ui-muted-5)] text-[9px] text-[var(--ui-muted)] border border-[var(--ui-border)]/30">{{ $topic }}</span>
                                                @endforeach
                                            </div>
                                            @if($src['last_pulled_at'])
                                                <span class="text-[9px] text-[var(--ui-muted)] whitespace-nowrap shrink-0">{{ $src['last_pulled_at'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-ui-panel>
                    @endif
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
