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
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Quick Stats</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Gesamt</span>
                            <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->totalEntities }}</span>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Aktiv</span>
                            <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->activeEntities }}</span>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Root</span>
                            <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->rootEntities }}</span>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 flex items-center justify-between">
                            <span class="text-xs text-[var(--ui-muted)]">Leaf</span>
                            <span class="text-lg font-bold text-[var(--ui-secondary)]">{{ $this->leafEntities }}</span>
                        </div>
                    </div>
                </div>

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
        @endphp

        {{-- 1. Insight Banner --}}
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

        {{-- 2. KPI Tiles --}}
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
                    :count="$summary['completion_rate'] . '%'"
                    icon="check-circle"
                    :variant="$summary['completion_rate'] >= 50 ? 'success' : 'warning'"
                    :trend="$summary['trend_completion'] > 0 ? 'up' : ($summary['trend_completion'] < 0 ? 'down' : null)"
                    :trendValue="$summary['trend_completion'] != 0 ? ($summary['trend_completion'] > 0 ? '+' : '') . $summary['trend_completion'] . '% vs. 7d' : null"
                />
            @else
                <x-ui-dashboard-tile
                    title="Fortschritt"
                    count="—"
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

                <x-ui-dashboard-tile
                    title="Abrechnung"
                    :count="$time['billing_rate'] . '%'"
                    icon="banknotes"
                    :variant="$time['billing_rate'] >= 50 ? 'success' : 'warning'"
                    :trend="$time['trend_billing'] > 0 ? 'up' : ($time['trend_billing'] < 0 ? 'down' : null)"
                    :trendValue="$time['trend_billing'] != 0 ? ($time['trend_billing'] > 0 ? '+' : '') . $time['trend_billing'] . '% vs. Vormonat' : null"
                />
            @else
                <x-ui-dashboard-tile title="Stunden (Monat)" count="—" icon="clock" variant="secondary" />
                <x-ui-dashboard-tile title="Abrechnung" count="—" icon="banknotes" variant="secondary" />
            @endif
        </div>

        {{-- 3. Einheiten-Gesundheit --}}
        @if(array_sum($health['counts']) > 0)
            <x-ui-panel title="Einheiten-Gesundheit" subtitle="Fortschritt-Klassifizierung" class="mb-8">
                {{-- Summary Badges --}}
                <div class="flex items-center gap-3 mb-4 flex-wrap">
                    @if($health['counts']['progressing'] > 0)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            Fortschreitend: {{ $health['counts']['progressing'] }}
                        </span>
                    @endif
                    @if($health['counts']['completed'] > 0)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            Abgeschlossen: {{ $health['counts']['completed'] }}
                        </span>
                    @endif
                    @if($health['counts']['stalled'] > 0)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            Stagnierend: {{ $health['counts']['stalled'] }}
                        </span>
                    @endif
                    @if($health['counts']['at_risk'] > 0)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                            Gefährdet: {{ $health['counts']['at_risk'] }}
                        </span>
                    @endif
                </div>

                {{-- Problem Entities --}}
                @if(count($health['problems']) > 0)
                    <div class="space-y-3">
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
                                            <x-ui-progress-bar :value="$problem['completion_pct']" variant="success" height="xs" />
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
            </x-ui-panel>
        @endif

        {{-- 4. 14-Tage Trend Chart --}}
        @if(!empty($trend) && count($trend['snapshots'] ?? []) >= 1)
            <x-ui-panel title="14-Tage Trend" subtitle="Team-weite Aggregation" class="mb-8">
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
                            {{-- Tooltip --}}
                            <div x-show="tooltip === {{ $idx }}" x-cloak
                                 class="absolute bottom-full mb-2 px-2.5 py-1.5 rounded-lg bg-[var(--ui-secondary)] text-white text-[10px] whitespace-nowrap z-10 shadow-lg pointer-events-none"
                                 x-transition.opacity>
                                {{ $snap['date'] }}: {{ $snap['items_done'] }}/{{ $snap['items_total'] }} Items,
                                {{ intdiv($snap['time_total_minutes'], 60) }}:{{ str_pad($snap['time_total_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h
                            </div>

                            <div class="w-full flex gap-px justify-center flex-1 items-end">
                                {{-- Items bar --}}
                                <div class="flex-1 flex flex-col justify-end">
                                    @if($snap['items_total'] > 0)
                                        <div class="w-full bg-blue-200 rounded-t" style="height: {{ $totalH }}px;">
                                            <div class="w-full bg-blue-500 rounded-t" style="height: {{ $doneH }}px;"></div>
                                        </div>
                                    @else
                                        <div class="w-full bg-[var(--ui-border)]/20 rounded-t" style="height: 1px;"></div>
                                    @endif
                                </div>
                                {{-- Time bar --}}
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

                {{-- Trend insight line --}}
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
            </x-ui-panel>
        @endif

        {{-- 5. Zwei-Spalten-Grid --}}
        @if(count($linkDist) > 0 || count($topEntities) > 0)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                {{-- Link-Typ-Verteilung --}}
                @if(count($linkDist) > 0)
                    <x-ui-panel title="Verknüpfungs-Typen" subtitle="Verteilung">
                        <div class="space-y-3">
                            @foreach($linkDist as $dist)
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 flex items-center justify-center flex-shrink-0">
                                        @svg('heroicon-o-' . $dist['icon'], 'w-4 h-4 text-[var(--ui-muted)]')
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $dist['label'] }}</span>
                                            <span class="text-sm font-bold text-[var(--ui-secondary)] ml-2">{{ $dist['count'] }}</span>
                                        </div>
                                        <div class="w-full bg-[var(--ui-muted-5)] rounded-full h-1.5">
                                            <div class="bg-[var(--ui-primary)] h-1.5 rounded-full" style="width: {{ $dist['percentage'] }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-ui-panel>
                @endif

                {{-- Top Entities --}}
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
            </div>
        @endif

        {{-- 6. Verteilung nach Typen + Neueste Entitäten --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-ui-panel title="Verteilung nach Entitätstyp" subtitle="Nach Typ gruppiert">
                <div class="space-y-2">
                    @php($byType = $this->entitiesByType)
                    @forelse(($byType ?? collect())->take(5) as $row)
                        <div class="group flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/60 flex items-center justify-center text-xs font-semibold text-[var(--ui-secondary)]">
                                    @svg('heroicon-o-building-office','w-4 h-4')
                                </div>
                                <div class="min-w-0">
                                    <div class="font-medium text-[var(--ui-secondary)] truncate">{{ $row->name }}</div>
                                    <div class="text-xs text-[var(--ui-muted)]">Typ-ID: {{ $row->id }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $row->count }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-[var(--ui-muted)] p-4 text-center">Keine Entitäten vorhanden.</div>
                    @endforelse
                </div>
            </x-ui-panel>

            <x-ui-panel title="Neueste Entitäten" subtitle="Top 5">
                <div class="space-y-2">
                    @php($recent = $this->recentEntities)
                    @forelse(($recent ?? collect())->take(5) as $entity)
                        <div class="group flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-surface)] hover:border-[var(--ui-primary)]/60 hover:bg-[var(--ui-primary-5)] transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-2 h-2 rounded-full {{ $entity->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                <div class="min-w-0">
                                    <a href="{{ route('organization.entities.show', $entity) }}"
                                       class="font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate block">
                                        {{ $entity->name }}
                                    </a>
                                    <div class="text-xs text-[var(--ui-muted)]">
                                        {{ $entity->type?->name ?? 'Typ' }}
                                        @if($entity->vsmSystem)
                                            • {{ $entity->vsmSystem->name }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <x-ui-badge variant="secondary" size="sm">{{ $entity->created_at?->format('d.m.Y') }}</x-ui-badge>
                        </div>
                    @empty
                        <div class="text-sm text-[var(--ui-muted)] p-4 text-center">Keine Entitäten vorhanden.</div>
                    @endforelse
                </div>
            </x-ui-panel>
        </div>
    </x-ui-page-container>
</x-ui-page>
