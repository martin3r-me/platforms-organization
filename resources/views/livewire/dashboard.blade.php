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
    $teamMovement = $this->teamMovement;
    $envRadar = $this->environmentRadar;

    $hasSignals = ($signalOverview['total_open'] ?? 0) > 0;
    $hasProblems = count($health['problems'] ?? []) > 0;
    $hasPersonWarnings = count($personOverview['persons'] ?? []) > 0;
    $hasTrend = ! empty($trend) && count($trend['snapshots'] ?? []) >= 1;
    $hasMovement = ! empty($teamMovement['metrics']);

    $signalCritical = $signalOverview['signals']->where('severity', 'critical')->count();
    $signalAccent = $signalCritical > 0 ? 'var(--nx-danger)' : ($hasSignals ? 'var(--nx-warning)' : 'var(--nx-success)');
    $signalHint = $signalCritical > 0
        ? $signalCritical . ' kritisch'
        : ($hasSignals ? $signalOverview['total_open'] . ' offen' : 'keine offenen');

    $signed = fn ($v, $suffix) => ($v > 0 ? '+' : '') . $v . $suffix;

    $sevVariant = fn ($s) => match ($s) {
        'critical', 'algedonic' => 'danger',
        'warning' => 'warning',
        default => 'info',
    };
    $sevLabel = fn ($s) => match ($s) {
        'critical' => 'Kritisch',
        'algedonic' => 'Algedonisch',
        'warning' => 'Warnung',
        default => 'Info',
    };
    $statusBadge = fn ($s) => match ($s) {
        'open' => ['warning', 'Offen'],
        'acknowledged' => ['info', 'Bestätigt'],
        'resolved' => ['success', 'Gelöst'],
        'dismissed' => ['neutral', 'Verworfen'],
        default => ['neutral', ucfirst((string) $s)],
    };
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Übersicht" icon="heroicon-o-building-office" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'icon' => 'building-office'],
        ]">
            <x-nx-button size="sm" :href="route('organization.entities.index')" wire:navigate>
                @svg('heroicon-o-rectangle-stack', 'w-4 h-4')
                <span>Einheiten</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            <div class="flex flex-wrap items-baseline gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">Übersicht</h1>
                <span class="text-sm text-[color:var(--nx-muted)]">{{ now()->locale('de')->isoFormat('dddd, D. MMMM') }}</span>
            </div>

            {{-- Kennzahlen --}}
            <x-nx-stat-grid cols="4">
                <x-nx-stat label="Aktive Einheiten" :value="$this->activeEntities" :hint="$this->totalEntities . ' gesamt'"
                    icon="heroicon-o-building-office" :href="route('organization.entities.index')" wire:navigate />
                <x-nx-stat label="Fortschritt" :value="$summary['has_data'] ? $summary['completion_rate'] . ' %' : '–'"
                    :hint="$summary['has_data'] && $summary['trend_completion'] != 0 ? $signed($summary['trend_completion'], ' % ggü. 7 Tagen') : null"
                    icon="heroicon-o-check-circle" />
                <x-nx-stat label="Stunden (Monat)" :value="$time['has_data'] ? $time['hours_this_month'] : '–'"
                    :hint="$time['has_data'] && $time['trend_hours'] != 0 ? $signed($time['trend_hours'], ' h ggü. Vormonat') : null"
                    icon="heroicon-o-clock" :href="route('organization.time-entries.index')" wire:navigate />
                <x-nx-stat label="Offene Signale" :value="$signalOverview['total_open']" :hint="$signalHint"
                    icon="heroicon-o-bell-alert" :accent="$signalAccent" :href="route('organization.signals.index')" wire:navigate />
            </x-nx-stat-grid>

            @if (count($insights) > 0)
                <x-nx-callout variant="neutral" icon="heroicon-o-light-bulb">
                    <div class="flex flex-col gap-1">
                        @foreach ($insights as $insight)
                            <span @class([
                                'text-[color:var(--nx-success)]' => $insight['type'] === 'success',
                                'text-[color:var(--nx-warning)]' => $insight['type'] === 'warning',
                                'text-[color:var(--nx-text)]' => ! in_array($insight['type'], ['success', 'warning']),
                            ])>{{ $insight['text'] }}</span>
                        @endforeach
                    </div>
                </x-nx-callout>
            @endif

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                {{-- Hauptspalte --}}
                <div class="min-w-0 space-y-5 lg:col-span-2">
                    @if ($focusedSignals->isNotEmpty())
                        <x-nx-panel flush title="Fokus" :subtitle="$focusedSignals->count() . ' markierte Signale'">
                            <x-slot name="actions">
                                <a href="{{ route('organization.signals.index', ['focusOnly' => 1]) }}" wire:navigate class="text-xs font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle</a>
                            </x-slot>
                            @foreach ($focusedSignals as $signal)
                                @php
                                    [$stV, $stL] = $statusBadge($signal->status);
                                @endphp
                                <div class="flex items-start gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-0">
                                    <button type="button" wire:click="unfocusSignal({{ $signal->id }})" title="Aus Fokus entfernen"
                                        class="mt-0.5 shrink-0 text-[color:var(--nx-warning)] hover:opacity-70">
                                        @svg('heroicon-s-star', 'w-4 h-4')
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('organization.signals.show', $signal) }}" wire:navigate class="block text-sm text-[color:var(--nx-text)] hover:underline">
                                            {{ \Illuminate\Support\Str::limit($signal->message, 140) }}
                                        </a>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-[color:var(--nx-faint)]">
                                            <x-nx-badge :variant="$sevVariant($signal->severity)">{{ $sevLabel($signal->severity) }}</x-nx-badge>
                                            <x-nx-badge :variant="$stV" dot>{{ $stL }}</x-nx-badge>
                                            @if ($signal->entity)
                                                <a href="{{ route('organization.entities.show', $signal->entity) }}" wire:navigate class="hover:underline">{{ $signal->entity->name }}</a> ·
                                            @endif
                                            <span>{{ $signal->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </x-nx-panel>
                    @endif

                    @if ($hasSignals || $hasProblems || $hasPersonWarnings)
                        <x-nx-panel flush title="Handlungsbedarf">
                            <x-slot name="actions">
                                <div class="flex flex-wrap gap-1">
                                    @if ($hasSignals)<x-nx-badge variant="warning">{{ $signalOverview['total_open'] }} Signale</x-nx-badge>@endif
                                    @if ($hasProblems)<x-nx-badge variant="danger">{{ count($health['problems']) }} Einheiten</x-nx-badge>@endif
                                    @if ($hasPersonWarnings)<x-nx-badge variant="warning">{{ count($personOverview['persons']) }} Personen</x-nx-badge>@endif
                                </div>
                            </x-slot>

                            @if ($hasSignals)
                                <div class="px-4 pb-1 pt-3 text-xs font-medium text-[color:var(--nx-faint)]">Signale</div>
                                @foreach ($signalOverview['signals'] as $signal)
                                    <div class="flex items-center gap-2 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm">
                                        <x-nx-badge :variant="$sevVariant($signal->severity)">{{ $sevLabel($signal->severity) }}</x-nx-badge>
                                        <a href="{{ route('organization.signals.show', $signal) }}" wire:navigate class="truncate text-[color:var(--nx-text)] hover:underline">{{ $signal->definition?->name ?? 'Signal' }}</a>
                                        <span class="truncate text-xs text-[color:var(--nx-faint)]">{{ $signal->entity?->name }}</span>
                                        <span class="ml-auto shrink-0 text-xs text-[color:var(--nx-faint)]">{{ $signal->created_at->diffForHumans() }}</span>
                                    </div>
                                @endforeach
                            @endif

                            @if ($hasProblems)
                                <div class="px-4 pb-1 pt-3 text-xs font-medium text-[color:var(--nx-faint)]">Gefährdete Einheiten</div>
                                @foreach ($health['problems'] as $problem)
                                    <div class="flex items-center gap-3 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm">
                                        <a href="{{ route('organization.entities.show', $problem['id']) }}" wire:navigate class="truncate font-medium text-[color:var(--nx-text)] hover:underline">{{ $problem['name'] }}</a>
                                        @if ($problem['status'] === 'at_risk')
                                            <x-nx-badge variant="danger">gefährdet</x-nx-badge>
                                        @else
                                            <x-nx-badge variant="warning">stagnierend</x-nx-badge>
                                        @endif
                                        <div class="ml-auto flex shrink-0 items-center gap-2">
                                            <div class="h-1.5 w-16 overflow-hidden rounded-full bg-[color:var(--nx-accent-soft)]">
                                                <div class="h-full rounded-full bg-[color:var(--nx-accent)]" style="width: {{ min($problem['completion_pct'], 100) }}%"></div>
                                            </div>
                                            <span class="w-9 text-right text-xs tabular-nums text-[color:var(--nx-muted)]">{{ $problem['completion_pct'] }} %</span>
                                        </div>
                                    </div>
                                @endforeach
                            @endif

                            @if ($hasPersonWarnings)
                                <div class="px-4 pb-1 pt-3 text-xs font-medium text-[color:var(--nx-faint)]">Personen</div>
                                @foreach ($personOverview['persons'] as $person)
                                    <div class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm last:border-0">
                                        <a href="{{ route('organization.entities.show', $person['id']) }}" wire:navigate class="truncate font-medium text-[color:var(--nx-text)] hover:underline">{{ $person['name'] }}</a>
                                        <div class="flex shrink-0 flex-wrap justify-end gap-2 text-xs">
                                            @foreach ($personOverview['metric_configs'] as $snapshotKey => $mCfg)
                                                @if (($person['metrics'][$snapshotKey] ?? 0) > 0 && in_array($mCfg['type'], ['warning', 'danger']))
                                                    <span class="{{ $mCfg['type'] === 'danger' ? 'text-[color:var(--nx-danger)]' : 'text-[color:var(--nx-warning)]' }}">{{ $person['metrics'][$snapshotKey] }} {{ $mCfg['label'] }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </x-nx-panel>
                    @endif

                    @if (count($topEntities) > 0)
                        <x-nx-panel flush title="Aktive Einheiten" subtitle="letzte 7 Tage">
                            @foreach ($topEntities as $top)
                                <a href="{{ route('organization.entities.show', $top['id']) }}" wire:navigate
                                   class="flex items-center gap-3 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm transition-colors last:border-0 hover:bg-[color:var(--nx-hover)]">
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-medium text-[color:var(--nx-text)]">{{ $top['name'] }}</div>
                                        <div class="text-xs text-[color:var(--nx-faint)]">{{ $top['type_name'] }}</div>
                                    </div>
                                    <div class="shrink-0 text-right text-xs tabular-nums">
                                        @if ($top['items_completed_7d'] > 0)
                                            <div class="text-[color:var(--nx-success)]">{{ $top['items_completed_7d'] }} erledigt</div>
                                        @endif
                                        @if ($top['hours_7d'] > 0)
                                            <div class="text-[color:var(--nx-muted)]">{{ $top['hours_7d'] }} h</div>
                                        @endif
                                    </div>
                                </a>
                            @endforeach
                        </x-nx-panel>
                    @endif

                    @if ($hasTrend || $hasMovement)
                        <x-nx-panel title="Entwicklung" x-data="{ tab: '{{ $hasTrend ? 'trend' : 'movement' }}' }">
                            <x-slot name="actions">
                                <div class="flex gap-1">
                                    @if ($hasTrend)
                                        <button type="button" x-on:click="tab = 'trend'"
                                            :class="tab === 'trend' ? 'bg-[color:var(--nx-active)] text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]'"
                                            class="rounded-[6px] px-2.5 py-1 text-xs font-medium">14 Tage</button>
                                    @endif
                                    @if ($hasMovement)
                                        <button type="button" x-on:click="tab = 'movement'"
                                            :class="tab === 'movement' ? 'bg-[color:var(--nx-active)] text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]'"
                                            class="rounded-[6px] px-2.5 py-1 text-xs font-medium">Bewegung</button>
                                    @endif
                                </div>
                            </x-slot>

                            @if ($hasTrend)
                                <div x-show="tab === 'trend'" x-cloak>
                                    <div class="mb-3 flex items-center justify-end gap-3 text-[11px] text-[color:var(--nx-muted)]">
                                        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[color:var(--nx-accent)]"></span>erledigt</span>
                                        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[color:var(--nx-accent-soft)]"></span>gesamt</span>
                                        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[color:var(--nx-info)]"></span>Stunden</span>
                                    </div>
                                    <div class="flex items-end gap-1" style="height: 160px;" x-data="{ tip: null }">
                                        @foreach ($trend['snapshots'] as $idx => $snap)
                                            @php
                                                $maxItems = max($trend['max_items_total'], 1);
                                                $maxMin = max($trend['max_minutes'], 1);
                                                $totalH = round(($snap['items_total'] / $maxItems) * 130);
                                                $doneH = $snap['items_total'] > 0 ? max(1, round(($snap['items_done'] / $maxItems) * 130)) : 0;
                                                $timeH = $snap['time_total_minutes'] > 0 ? max(2, round(($snap['time_total_minutes'] / $maxMin) * 130)) : 0;
                                                $lastIdx = count($trend['snapshots']) - 1;
                                            @endphp
                                            <div class="relative flex h-full flex-1 flex-col items-center justify-end gap-px"
                                                 x-on:mouseenter="tip = {{ $idx }}" x-on:mouseleave="tip = null">
                                                <div x-show="tip === {{ $idx }}" x-cloak
                                                     class="pointer-events-none absolute bottom-full z-10 mb-2 whitespace-nowrap rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-2 py-1 text-[11px] text-[color:var(--nx-text)] shadow-sm">
                                                    {{ $snap['date'] }}: {{ $snap['items_done'] }}/{{ $snap['items_total'] }} ·
                                                    {{ intdiv($snap['time_total_minutes'], 60) }}:{{ str_pad($snap['time_total_minutes'] % 60, 2, '0', STR_PAD_LEFT) }} h
                                                </div>
                                                <div class="flex w-full flex-1 items-end justify-center gap-px">
                                                    <div class="flex flex-1 flex-col justify-end">
                                                        @if ($snap['items_total'] > 0)
                                                            <div class="flex w-full flex-col justify-end rounded-t-[3px] bg-[color:var(--nx-accent-soft)]" style="height: {{ $totalH }}px;">
                                                                <div class="w-full rounded-t-[3px] bg-[color:var(--nx-accent)]" style="height: {{ $doneH }}px;"></div>
                                                            </div>
                                                        @else
                                                            <div class="h-px w-full bg-[color:var(--nx-line)]"></div>
                                                        @endif
                                                    </div>
                                                    <div class="flex flex-1 flex-col justify-end">
                                                        @if ($snap['time_total_minutes'] > 0)
                                                            <div class="w-full rounded-t-[3px] bg-[color:var(--nx-info)] opacity-70" style="height: {{ $timeH }}px;"></div>
                                                        @else
                                                            <div class="h-px w-full bg-[color:var(--nx-line)]"></div>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="mt-0.5 h-[11px] text-[9px] leading-none text-[color:var(--nx-faint)]">
                                                    @if ($idx === 0 || $idx === $lastIdx || $idx % 3 === 0){{ $snap['date'] }}@endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    @if ($velocity['avg_per_week'] > 0)
                                        <p class="mb-0 mt-3 border-t border-[color:var(--nx-line)] pt-3 text-xs text-[color:var(--nx-muted)]">
                                            Erledigungsrate Ø {{ $velocity['avg_per_week'] }} Items/Woche
                                            @if ($velocity['trend'] === 'accelerating')
                                                <span class="text-[color:var(--nx-success)]">· beschleunigt</span>
                                            @elseif ($velocity['trend'] === 'decelerating')
                                                <span class="text-[color:var(--nx-warning)]">· verlangsamt</span>
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endif

                            @if ($hasMovement)
                                <div x-show="tab === 'movement'" x-cloak>
                                    <div class="mb-3 flex flex-wrap gap-1">
                                        <button type="button" wire:click="$set('dashboardStream', null)"
                                            class="rounded-[6px] px-2 py-0.5 text-xs {{ ! $dashboardStream ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">Alle</button>
                                        @foreach ($teamMovement['available_groups'] as $groupKey => $groupLabel)
                                            <button type="button" wire:click="$set('dashboardStream', '{{ $groupKey }}')"
                                                class="rounded-[6px] px-2 py-0.5 text-xs {{ $dashboardStream === $groupKey ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">{{ $groupLabel }}</button>
                                        @endforeach
                                    </div>
                                    @foreach ($teamMovement['metrics_by_group'] as $groupKey => $metrics)
                                        <div class="mb-3">
                                            <div class="mb-1.5 text-xs font-medium text-[color:var(--nx-faint)]">{{ ucfirst($groupKey) }}</div>
                                            <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
                                                @foreach ($metrics as $m)
                                                    @if ($m['current'] > 0 || $m['previous'] > 0)
                                                        <div class="rounded-[6px] border border-[color:var(--nx-line)] px-2.5 py-2">
                                                            <div class="text-sm font-semibold tabular-nums text-[color:var(--nx-text)]">
                                                                {{ $m['current'] }}
                                                                @if ($m['delta'] != 0)
                                                                    <span class="ml-1 text-[11px] font-medium {{ $m['sentiment'] === 'positive' ? 'text-[color:var(--nx-success)]' : ($m['sentiment'] === 'negative' ? 'text-[color:var(--nx-danger)]' : 'text-[color:var(--nx-muted)]') }}">{{ $m['delta_formatted'] }}</span>
                                                                @endif
                                                            </div>
                                                            <div class="text-[11px] text-[color:var(--nx-muted)]">{{ $m['label'] }}</div>
                                                            @if ($m['ratio'])
                                                                <div class="mt-1 h-1 overflow-hidden rounded-full bg-[color:var(--nx-accent-soft)]">
                                                                    <div class="h-full rounded-full bg-[color:var(--nx-accent)]" style="width: {{ min($m['ratio'], 100) }}%"></div>
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
                        </x-nx-panel>
                    @endif

                    @if ($envRadar['has_data'])
                        <x-nx-panel flush title="Umwelt-Radar" subtitle="Externe Signale">
                            <x-slot name="actions">
                                <a href="{{ route('organization.environment-snapshots.index') }}" wire:navigate class="text-xs font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle</a>
                            </x-slot>
                            @foreach ($envRadar['sources'] as $src)
                                <div class="border-b border-[color:var(--nx-line)] px-4 py-3 last:border-0">
                                    <div class="flex items-center gap-2">
                                        <x-nx-badge>{{ ucfirst($src['category']) }}</x-nx-badge>
                                        <span class="flex-1 truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $src['name'] }}</span>
                                        @if ($src['sentiment_score'] !== null)
                                            @php
                                                $sent = $src['sentiment_score'] >= 0.6 ? 'var(--nx-success)' : ($src['sentiment_score'] >= 0.3 ? 'var(--nx-warning)' : 'var(--nx-danger)');
                                            @endphp
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $sent }}" title="Stimmung {{ round($src['sentiment_score'] * 100) }} %"></span>
                                        @endif
                                        @if ($src['delta'] && $src['delta']['sentiment_change'] != 0)
                                            <span class="text-xs {{ $src['delta']['sentiment_change'] > 0 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]' }}">{!! $src['delta']['sentiment_change'] > 0 ? '&#9650;' : '&#9660;' !!}</span>
                                        @endif
                                    </div>
                                    @if ($src['relevance_score'] !== null)
                                        <div class="mt-1.5 flex items-center gap-2">
                                            <span class="w-14 shrink-0 text-[11px] text-[color:var(--nx-faint)]">Relevanz</span>
                                            <div class="h-1 flex-1 overflow-hidden rounded-full bg-[color:var(--nx-accent-soft)]">
                                                <div class="h-full rounded-full bg-[color:var(--nx-accent)]" style="width: {{ min(round($src['relevance_score'] * 100), 100) }}%"></div>
                                            </div>
                                            <span class="text-[11px] tabular-nums text-[color:var(--nx-muted)]">{{ round($src['relevance_score'] * 100) }} %</span>
                                        </div>
                                    @endif
                                    @if ($src['summary'])
                                        <p class="mb-0 mt-1.5 line-clamp-2 text-xs text-[color:var(--nx-muted)]">{{ $src['summary'] }}</p>
                                    @endif
                                    <div class="mt-1.5 flex items-center justify-between gap-2">
                                        <div class="flex min-w-0 flex-wrap gap-1">
                                            @foreach (array_slice($src['topics'] ?? [], 0, 4) as $topic)
                                                <span class="rounded-[4px] bg-[color:var(--nx-accent-soft)] px-1.5 py-0.5 text-[10px] text-[color:var(--nx-muted)]">{{ $topic }}</span>
                                            @endforeach
                                        </div>
                                        @if ($src['last_pulled_at'])
                                            <span class="shrink-0 whitespace-nowrap text-[10px] text-[color:var(--nx-faint)]">{{ $src['last_pulled_at'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </x-nx-panel>
                    @endif
                </div>

                {{-- Seitenspalte --}}
                <div class="min-w-0 space-y-5">
                    @if ($time['has_data'])
                        <x-nx-panel flush title="Zeiterfassung">
                            <div class="divide-y divide-[color:var(--nx-line)] text-sm">
                                <div class="flex justify-between px-4 py-2"><span class="text-[color:var(--nx-muted)]">Stunden (Monat)</span><span class="font-medium tabular-nums">{{ $time['hours_this_month'] }}</span></div>
                                @if ($summary['has_data'])
                                    <div class="flex justify-between px-4 py-2"><span class="text-[color:var(--nx-muted)]">Items erledigt</span><span class="font-medium tabular-nums">{{ $summary['items_done'] }}</span></div>
                                    <div class="flex justify-between px-4 py-2"><span class="text-[color:var(--nx-muted)]">Abrechnungsquote</span><span class="font-medium tabular-nums">{{ $time['billing_rate'] }} %</span></div>
                                @endif
                            </div>
                        </x-nx-panel>
                    @endif

                    @if (count($linkDist) > 0)
                        <x-nx-panel flush title="Verknüpfungen">
                            <div class="divide-y divide-[color:var(--nx-line)] text-sm">
                                @foreach ($linkDist as $dist)
                                    <div class="flex items-center gap-2 px-4 py-2">
                                        @svg('heroicon-o-' . (app('safe-svg')->resolve($dist['icon'] ?? null, 'heroicon-o-') ?? 'cube'), 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                        <span class="flex-1 truncate text-[color:var(--nx-muted)]">{{ $dist['label'] }}</span>
                                        <span class="font-medium tabular-nums">{{ $dist['count'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </x-nx-panel>
                    @endif

                    <x-nx-panel flush title="Letzte Aktivität">
                        <div class="max-h-[32rem] overflow-y-auto">
                            <livewire:organization.activity-feed lazy />
                        </div>
                    </x-nx-panel>
                </div>
            </div>
        </div>

        @if ($profile)
            <script type="application/json" id="org-dashboard-timings">{!! json_encode($this->timings()) !!}</script>
        @endif
    </x-ui-page-container>
</x-ui-page>
