<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Einheiten', 'href' => route('organization.entities.index')],
            ['label' => $entity->name ?? 'Details'],
        ]">
            @if($this->isDirty())
                <x-ui-button variant="secondary-ghost" size="sm" wire:click="loadForm">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </x-ui-button>
            @else
                <x-ui-button variant="ghost" size="sm" wire:click="edit">
                    @svg('heroicon-o-pencil', 'w-4 h-4')
                    <span>Bearbeiten</span>
                </x-ui-button>
                <x-ui-button variant="ghost" size="sm" href="{{ route('organization.entities.mindmap', $entity) }}">
                    @svg('heroicon-o-globe-alt', 'w-4 h-4')
                    <span>Mindmap</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="openCreateTeamModal">
                    @svg('heroicon-o-user-group', 'w-4 h-4')
                    <span>Team erstellen</span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-3">
                        @if($entity->is_active)
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        @if($entity->code)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Code</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->code }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Typ</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->type->name }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">{{ $entity->type->group->name }}</div>
                        </div>
                        @if($entity->vsmSystem)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">VSM System</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->vsmSystem->name }}</div>
                            </div>
                        @endif
                        @if($entity->costCenter)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Kostenstelle</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->costCenter->name }}</div>
                            </div>
                        @endif
                        @if($entity->parent)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Übergeordnet</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->parent->name }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">{{ $entity->parent->type->name }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Aktualisiert</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->updated_at->format('d.m.Y H:i') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <livewire:organization.activity-feed :entityId="$entity->id" />
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                {{ session('error') }}
            </div>
        @endif

        <div class="space-y-6">
            {{-- Hero Card --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $entity->name }}</h1>
                        <div class="flex items-center gap-3 mt-2">
                            <x-ui-badge variant="secondary" size="sm">{{ $entity->type->name }}</x-ui-badge>
                            @if($entity->is_active)
                                <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                            @else
                                <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                            @endif
                            @if($entity->code)
                                <span class="text-sm text-[var(--ui-muted)]">{{ $entity->code }}</span>
                            @endif
                        </div>
                        @if($entity->description)
                            <p class="mt-3 text-sm text-[var(--ui-muted)] max-w-2xl">{{ $entity->description }}</p>
                        @endif
                    </div>
                </div>

                {{-- Stats Grid --}}
                @php
                    $childCount = $entity->children->count();
                    $descendantCount = $this->totalDescendantCount;
                    $linkCount = $this->totalLinkCount;
                    $cascaded = $this->cascadedTimeSummary;
                    $totalHours = intdiv($cascaded['total_minutes'], 60);
                    $totalMins = $cascaded['total_minutes'] % 60;
                    $openMinutes = $cascaded['total_minutes'] - $cascaded['billed_minutes'];
                    $openHours = intdiv($openMinutes, 60);
                    $openMins = abs($openMinutes % 60);
                @endphp
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $childCount }}</div>
                        <div class="text-xs text-[var(--ui-muted)] mt-1">
                            Einheiten
                            @if($descendantCount > $childCount)
                                <span class="text-[var(--ui-muted)]">({{ $descendantCount }} gesamt)</span>
                            @endif
                        </div>
                    </div>
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $linkCount }}</div>
                        <div class="text-xs text-[var(--ui-muted)] mt-1">Verknüpfungen gesamt</div>
                    </div>
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $totalHours }}:{{ str_pad($totalMins, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="text-xs text-[var(--ui-muted)] mt-1">Gesamt-Stunden</div>
                    </div>
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                        <div class="text-2xl font-bold {{ $openMinutes > 0 ? 'text-amber-600' : 'text-green-600' }}">{{ $openHours }}:{{ str_pad($openMins, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="text-xs text-[var(--ui-muted)] mt-1">Offene Stunden</div>
                    </div>
                </div>

                {{-- Snapshot Analysis --}}
                @php $analysis = $this->snapshotAnalysis; @endphp
                @if(!empty($analysis))
                    {{-- Health Status Badge + Snapshot KPIs --}}
                    <div class="mt-6 pt-6 border-t border-[var(--ui-border)]/40">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-medium text-[var(--ui-muted)]">Snapshot-Analyse</span>
                            @php
                                $healthLabels = [
                                    'progressing' => ['Fortschreitend', 'success'],
                                    'completed' => ['Abgeschlossen', 'info'],
                                    'stalled' => ['Stagnierend', 'warning'],
                                    'at_risk' => ['Gefährdet', 'danger'],
                                ];
                                [$healthLabel, $healthVariant] = $healthLabels[$analysis['health_status']] ?? ['—', 'secondary'];
                            @endphp
                            <x-ui-badge :variant="$healthVariant" size="sm">{{ $healthLabel }}</x-ui-badge>
                        </div>

                        {{-- Snapshot KPI Grid --}}
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                            <div class="py-2.5 px-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                                <div class="text-lg font-bold text-[var(--ui-secondary)]">
                                    {{ $analysis['completion_rate'] }}%
                                    @if($analysis['trend_completion'] > 0)
                                        <span class="text-xs text-green-600 font-medium ml-1">+{{ $analysis['trend_completion'] }}%</span>
                                    @elseif($analysis['trend_completion'] < 0)
                                        <span class="text-xs text-red-600 font-medium ml-1">{{ $analysis['trend_completion'] }}%</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-[var(--ui-muted)] mt-0.5">Fortschritt</div>
                            </div>
                            <div class="py-2.5 px-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                                <div class="text-lg font-bold text-[var(--ui-secondary)]">{{ $analysis['items_completed_7d'] }}</div>
                                <div class="text-[10px] text-[var(--ui-muted)] mt-0.5">Items erledigt (7d)</div>
                            </div>
                            <div class="py-2.5 px-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                                <div class="text-lg font-bold text-[var(--ui-secondary)]">
                                    {{ $analysis['estimated_days_remaining'] !== null ? $analysis['estimated_days_remaining'] . ' Tage' : '—' }}
                                </div>
                                <div class="text-[10px] text-[var(--ui-muted)] mt-0.5">Geschätzte Restlaufzeit</div>
                            </div>
                            <div class="py-2.5 px-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                                <div class="text-lg font-bold text-[var(--ui-secondary)]">
                                    {{ $analysis['billing_rate'] }}%
                                    @if($analysis['trend_billing'] > 0)
                                        <span class="text-xs text-green-600 font-medium ml-1">+{{ $analysis['trend_billing'] }}%</span>
                                    @elseif($analysis['trend_billing'] < 0)
                                        <span class="text-xs text-red-600 font-medium ml-1">{{ $analysis['trend_billing'] }}%</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-[var(--ui-muted)] mt-0.5">Abrechnungsquote</div>
                            </div>
                        </div>

                        {{-- Insight Statements --}}
                        @if(!empty($analysis['insights']))
                            <div class="space-y-1 mb-4">
                                @foreach($analysis['insights'] as $insight)
                                    <p class="text-xs
                                        @if($insight['type'] === 'success') text-green-700
                                        @elseif($insight['type'] === 'warning') text-amber-700
                                        @else text-[var(--ui-muted)]
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
                                    </p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Bewegung (7 Tage) --}}
                @php $movement = $this->movement; @endphp
                @if(!empty($movement['metrics']))
                    <div class="mt-6 pt-6 border-t border-[var(--ui-border)]/40">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-medium text-[var(--ui-muted)]">Bewegung (7 Tage)</span>
                            <div class="flex gap-1">
                                <button wire:click="$set('movementStream', null)"
                                    class="px-2 py-1 text-[10px] rounded transition-colors {{ !$movementStream ? 'bg-[var(--ui-primary)] text-white' : 'text-[var(--ui-muted)] hover:text-[var(--ui-text)]' }}">
                                    Alle
                                </button>
                                @foreach($this->availableStreams as $stream)
                                    <button wire:click="$set('movementStream', '{{ $stream }}')"
                                        class="px-2 py-1 text-[10px] rounded transition-colors {{ $movementStream === $stream ? 'bg-[var(--ui-primary)] text-white' : 'text-[var(--ui-muted)] hover:text-[var(--ui-text)]' }}">
                                        {{ ucfirst($stream) }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @foreach($movement['metrics_by_group'] as $groupKey => $metrics)
                            <div class="mb-3">
                                <div class="text-[10px] font-medium text-[var(--ui-muted)] uppercase mb-1.5">
                                    {{ ucfirst($groupKey) }}
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
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

                {{-- Snapshot Trend (14 Tage) --}}
                @php $trend = $this->snapshotTrend; @endphp
                @if(!empty($trend) && count($trend['snapshots'] ?? []) >= 1)
                    <div class="mt-6 pt-6 border-t border-[var(--ui-border)]/40">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-medium text-[var(--ui-muted)]">Trend (14 Tage)</span>
                            <div class="flex items-center gap-3">
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
                                        {{ $snap['date'] }} {{ $snap['period'] }}: {{ $snap['items_done'] }}/{{ $snap['items_total'] }} Items,
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
                                    @if($idx === 0 || $idx === count($trend['snapshots']) - 1 || $idx % 4 === 0)
                                        <div class="text-[9px] text-[var(--ui-muted)] mt-0.5 leading-none">{{ $snap['date'] }}</div>
                                    @else
                                        <div class="h-[11px]"></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Monthly Time Chart --}}
                @php
                    $monthlyData = $this->monthlyTimeData;
                    $chartMonths = $monthlyData['months'] ?? [];
                    $maxMin = $monthlyData['max_minutes'] ?? 0;
                @endphp
                @if($maxMin > 0)
                    <div class="mt-6 pt-6 border-t border-[var(--ui-border)]/40"
                         x-data="{ tooltip: null }">
                        {{-- Header with legend --}}
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-medium text-[var(--ui-muted)]">Zeitverlauf (12 Monate)</span>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                    <span class="text-[10px] text-[var(--ui-muted)]">abgerechnet</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                    <span class="text-[10px] text-[var(--ui-muted)]">offen</span>
                                </div>
                            </div>
                        </div>

                        {{-- Bars --}}
                        <div class="flex items-end gap-1.5" style="height: 136px;">
                            @foreach($chartMonths as $idx => $m)
                                <div class="flex-1 flex flex-col items-center h-full relative"
                                     @mouseenter="tooltip = {{ $idx }}"
                                     @mouseleave="tooltip = null">
                                    {{-- Tooltip --}}
                                    <div x-show="tooltip === {{ $idx }}" x-cloak
                                         class="absolute bottom-full mb-2 px-2.5 py-1.5 rounded-lg bg-[var(--ui-secondary)] text-white text-[10px] whitespace-nowrap z-10 shadow-lg pointer-events-none"
                                         x-transition.opacity>
                                        {{ $m['label'] }} {{ $m['year'] }}:
                                        {{ intdiv($m['total_minutes'], 60) }}:{{ str_pad($m['total_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h
                                        ({{ intdiv($m['billed_minutes'], 60) }}:{{ str_pad($m['billed_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h abgerechnet,
                                        {{ intdiv($m['open_minutes'], 60) }}:{{ str_pad($m['open_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h offen)
                                    </div>

                                    {{-- Bar stack --}}
                                    <div class="w-full flex flex-col justify-end flex-1 rounded-t overflow-hidden">
                                        @if($m['total_minutes'] > 0)
                                            @php
                                                $barHeight = round(($m['total_minutes'] / $maxMin) * 120);
                                                $billedHeight = $m['billed_minutes'] > 0 ? max(2, round(($m['billed_minutes'] / $maxMin) * 120)) : 0;
                                                $openHeight = $m['open_minutes'] > 0 ? max(2, $barHeight - $billedHeight) : 0;
                                                if ($billedHeight + $openHeight > $barHeight && $barHeight > 4) {
                                                    // Adjust if min-heights pushed total over
                                                    $billedHeight = $barHeight - $openHeight;
                                                }
                                            @endphp
                                            <div class="w-full flex flex-col justify-end mt-auto">
                                                @if($m['open_minutes'] > 0)
                                                    <div class="w-full bg-amber-400 rounded-t" style="height: {{ $openHeight }}px;"></div>
                                                @endif
                                                @if($m['billed_minutes'] > 0)
                                                    <div class="w-full bg-green-500 {{ $m['open_minutes'] <= 0 ? 'rounded-t' : '' }}" style="height: {{ $billedHeight }}px;"></div>
                                                @endif
                                            </div>
                                        @else
                                            {{-- Empty month: thin track --}}
                                            <div class="w-full mt-auto">
                                                <div class="w-full bg-[var(--ui-border)]/20 rounded-t" style="height: 1px;"></div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Month label --}}
                                    <div class="text-[10px] text-[var(--ui-muted)] mt-1 leading-none">{{ $m['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Tab Navigation --}}
            <div x-data="{ tab: @entangle('activeTab') }">
                <div class="border-b border-[var(--ui-border)] mb-6">
                    <nav class="flex gap-1 -mb-px">
                        <button
                            @click="tab = 'hierarchy'"
                            :class="tab === 'hierarchy'
                                ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                            class="px-4 py-2.5 text-sm transition-colors"
                        >
                            @svg('heroicon-o-rectangle-group', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                            Hierarchie
                        </button>
                        <button
                            @click="tab = 'data'"
                            :class="tab === 'data'
                                ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                            class="px-4 py-2.5 text-sm transition-colors"
                        >
                            @svg('heroicon-o-document-text', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                            Daten
                        </button>
                        <button
                            @click="tab = 'relations'"
                            :class="tab === 'relations'
                                ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                            class="px-4 py-2.5 text-sm transition-colors"
                        >
                            @svg('heroicon-o-link', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                            Relations
                        </button>
                        @if($this->hasLinkedUser)
                            <button
                                @click="tab = 'person'"
                                :class="tab === 'person'
                                    ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                    : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                                class="px-4 py-2.5 text-sm transition-colors"
                            >
                                @svg('heroicon-o-user', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                                Person
                            </button>
                        @endif
                    </nav>
                </div>

                {{-- Tab: Hierarchie --}}
                <div x-show="tab === 'hierarchy'" x-cloak x-data="{
                    linkConfig: {{ Js::from(collect($this->linkTypeConfig)->map(fn($c) => ['label' => $c['label'], 'icon' => $c['icon']])) }},
                    linkIconSvgs: {{ Js::from($this->linkTypeIconSvgs) }},
                    displayRules: {{ Js::from($this->displayRules) }}
                }">
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                        @if(count($this->rootEntityLinks) > 0 || count($this->treeNodes) > 0)
                            {{-- Tree Controls --}}
                            <div class="flex justify-end gap-2 mb-4" x-data>
                                <button
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors"
                                    :class="$store.tree.showDone ? 'border-green-300 text-green-700 bg-green-50 hover:bg-green-100' : 'border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]'"
                                    @click="$store.tree.showDone = !$store.tree.showDone"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span x-text="$store.tree.showDone ? 'Erledigte ausblenden' : 'Erledigte anzeigen'"></span>
                                </button>
                                <button
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                                    @click="$store.tree.allExpanded ? $store.tree.collapseAll() : $store.tree.expandAll($wire)"
                                    :disabled="$store.tree.loading"
                                >
                                    <template x-if="$store.tree.loading">
                                        <svg class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </template>
                                    <template x-if="!$store.tree.loading">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                        </svg>
                                    </template>
                                    <span x-text="$store.tree.allExpanded ? 'Alle einklappen' : 'Alle aufklappen'"></span>
                                </button>
                            </div>
                            @php
                                $rootCascaded = $this->cascadedTimeSummary;
                                $rootTotalMin = $rootCascaded['total_minutes'];
                                $rootBilledMin = $rootCascaded['billed_minutes'];
                                $rootOpenMin = $rootTotalMin - $rootBilledMin;
                                $rootHours = intdiv($rootTotalMin, 60);
                                $rootMins = $rootTotalMin % 60;
                                $rootOpenH = intdiv(abs($rootOpenMin), 60);
                                $rootOpenM = abs($rootOpenMin) % 60;
                                $rootTypeIcon = null;
                                if ($entity->type->icon) {
                                    $icon = str_replace('heroicons.', '', $entity->type->icon);
                                    $iconMap = ['user-check' => 'user', 'folder-kanban' => 'folder', 'briefcase-globe' => 'briefcase', 'server-cog' => 'server', 'package-check' => 'archive-box', 'badge-check' => 'check-badge'];
                                    $rootTypeIcon = $iconMap[$icon] ?? $icon;
                                }
                            @endphp

                            <div x-data="{ rootExpanded: true }" class="space-y-1">
                                {{-- Root Entity Node --}}
                                <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">
                                    <div class="flex items-center gap-2 cursor-pointer" @click="rootExpanded = !rootExpanded">
                                        {{-- Chevron --}}
                                        <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                                                :class="{ 'rotate-90': rootExpanded }">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                            </svg>
                                        </div>

                                        {{-- Type Icon --}}
                                        @if($rootTypeIcon)
                                            @svg('heroicon-o-' . $rootTypeIcon, 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                                        @endif

                                        {{-- Name --}}
                                        <span class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $entity->name }}</span>

                                        {{-- Code --}}
                                        @if($entity->code)
                                            <span class="text-xs text-[var(--ui-muted)] font-mono flex-shrink-0">{{ $entity->code }}</span>
                                        @endif

                                        {{-- Type Badge --}}
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0">
                                            {{ $entity->type->name }}
                                        </span>

                                        {{-- Time Summary --}}
                                        @if($rootTotalMin > 0)
                                            <div class="flex items-center gap-2 ml-auto flex-shrink-0">
                                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--ui-secondary)]">
                                                    @if($rootOpenMin > 0)
                                                        <span class="w-2 h-2 rounded-full flex-shrink-0 bg-amber-400"></span>
                                                    @else
                                                        <span class="w-2 h-2 rounded-full flex-shrink-0 bg-green-500"></span>
                                                    @endif
                                                    {{ $rootHours }}:{{ str_pad($rootMins, 2, '0', STR_PAD_LEFT) }}h
                                                </span>
                                                @if($rootOpenMin > 0)
                                                    <span class="text-[10px] text-amber-600 font-medium">
                                                        {{ $rootOpenH }}:{{ str_pad($rootOpenM, 2, '0', STR_PAD_LEFT) }}h offen
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Root Expanded Content --}}
                                <div x-show="rootExpanded" x-collapse>
                                    {{-- Root Typ-Gruppen (grouped links) --}}
                                    @foreach($this->rootEntityLinks as $group)
                                        <div x-data="{ groupOpen: false, init() { this.$watch('$store.tree.allExpanded', v => this.groupOpen = v); } }" class="ml-6 border-l-2 border-[var(--ui-border)]/20">
                                            {{-- Group Header --}}
                                            <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3 cursor-pointer"
                                                @click="groupOpen = !groupOpen">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                            class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                                                            :class="{ 'rotate-90': groupOpen }">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                        </svg>
                                                    </div>
                                                    @svg('heroicon-o-' . $group['icon'], 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $group['label'] }}</span>
                                                    <span class="text-xs text-[var(--ui-muted)]">({{ count($group['items']) }})</span>
                                                    @if(($group['group_logged_minutes'] ?? 0) > 0)
                                                        <span class="text-xs text-[var(--ui-muted)] ml-auto flex-shrink-0">
                                                            {{ intdiv($group['group_logged_minutes'], 60) }}:{{ str_pad($group['group_logged_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Group Items (Link Leaves) --}}
                                            <div x-show="groupOpen" x-collapse x-cloak>
                                                @foreach($group['items'] as $link)
                                                    @include('organization::livewire.entity.partials.link-item', ['link' => $link, 'group' => $group])
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach

                                    {{-- Child Entities (expandable tree nodes) --}}
                                    @foreach($this->treeNodes as $node)
                                        @include('organization::livewire.entity.partials.tree-node', ['node' => $node, 'depth' => 1])
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                                    @svg('heroicon-o-rectangle-group', 'w-8 h-8 text-[var(--ui-muted)]')
                                </div>
                                <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine Verknüpfungen oder Untereinheiten</p>
                                <p class="text-xs text-[var(--ui-muted)]">Diese Einheit hat weder Verknüpfungen noch Kinder-Entities</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Tab: Daten --}}
                <div x-show="tab === 'data'" x-cloak>
                    <div class="space-y-6">
                        {{-- Grunddaten --}}
                        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                            <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                            <div class="space-y-4">
                                <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />
                                <x-ui-input-text name="code" label="Code" wire:model.live="form.code" placeholder="Optional: Code oder Nummer" />
                                <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" />
                                <x-ui-input-select
                                    name="entity_type_id"
                                    label="Typ"
                                    :options="$this->entityTypes->flatten()"
                                    optionValue="id"
                                    optionLabel="name"
                                    :nullable="false"
                                    wire:model.live="form.entity_type_id"
                                    required
                                />
                                <x-ui-input-select
                                    name="vsm_system_id"
                                    label="VSM System (optional)"
                                    :options="$this->vsmSystems"
                                    optionValue="id"
                                    optionLabel="name"
                                    :nullable="true"
                                    nullLabel="Kein VSM System"
                                    wire:model.live="form.vsm_system_id"
                                />
                                <x-ui-input-select
                                    name="cost_center_id"
                                    label="Kostenstelle (optional)"
                                    :options="$this->costCenters"
                                    optionValue="id"
                                    optionLabel="name"
                                    :nullable="true"
                                    nullLabel="Keine Kostenstelle"
                                    wire:model.live="form.cost_center_id"
                                />
                                <x-ui-input-select
                                    name="parent_entity_id"
                                    label="Übergeordnete Einheit (optional)"
                                    :options="$this->parentEntities"
                                    optionValue="id"
                                    optionLabel="name"
                                    :nullable="true"
                                    nullLabel="Keine übergeordnete Einheit"
                                    wire:model.live="form.parent_entity_id"
                                />
                                <x-ui-input-select
                                    name="linked_user_id"
                                    label="Verknüpfter User (optional)"
                                    :options="$this->teamUsers"
                                    optionValue="id"
                                    optionLabel="name"
                                    :nullable="true"
                                    nullLabel="Kein User verknüpft"
                                    wire:model.live="form.linked_user_id"
                                />
                                <div class="flex items-center">
                                    <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                                    <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                                </div>
                            </div>
                        </div>

                        {{-- Dimensionen --}}
                        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                            <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Dimensionen</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Kostenstellen</h3>
                                    <livewire:organization.dimension-linker
                                        dimension="cost-centers"
                                        :contextType="$entity::class"
                                        :contextId="$entity->id"
                                        :key="'dim-cost-centers-'.$entity->id"
                                    />
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">VSM Funktionen</h3>
                                    <div class="space-y-2">
                                        @if($this->availableVsmFunctions->count() > 0)
                                            @foreach($this->availableVsmFunctions as $vsmFunction)
                                                <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded">
                                                    <div>
                                                        <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $vsmFunction->name }}</div>
                                                        <div class="text-xs text-[var(--ui-muted)]">{{ $vsmFunction->code }}</div>
                                                    </div>
                                                    @if($vsmFunction->isGlobal())
                                                        <x-ui-badge variant="secondary" size="sm">Global</x-ui-badge>
                                                    @else
                                                        <x-ui-badge variant="info" size="sm">Entitätsspezifisch</x-ui-badge>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @else
                                            <div class="text-sm text-[var(--ui-muted)] py-2">Keine VSM Funktionen verfügbar</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tab: Relations --}}
                <div x-show="tab === 'relations'" x-cloak>
                    <div class="space-y-6">
                        {{-- Intro --}}
                        <div class="bg-[var(--ui-info-5)] border border-[var(--ui-info-20)] rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-[var(--ui-secondary)] mb-1">Beziehungen & Schnittstellen</h4>
                            <p class="text-sm text-[var(--ui-muted)]">
                                <strong>Beziehungen</strong> beschreiben, wie Organisationseinheiten zusammenhängen (z.B. "liefert an", "beauftragt").
                                An jede Beziehung können <strong>Schnittstellen</strong> gehängt werden — die konkreten Berührungspunkte: Verträge, Ticketsysteme, Datenflüsse, APIs.
                                Pro Beziehung sind <strong>mehrere Schnittstellen</strong> möglich.
                            </p>
                        </div>

                        {{-- Ausgehende Beziehungen --}}
                        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                            <div class="flex items-center justify-between mb-1">
                                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] flex items-center gap-2">
                                    @svg('heroicon-o-arrow-right', 'w-4 h-4 text-[var(--ui-primary)]')
                                    Ausgehende Beziehungen
                                    <span class="text-xs font-normal text-[var(--ui-muted)]">({{ $this->relationsFrom->count() }})</span>
                                </h3>
                                <x-ui-button variant="primary" size="sm" wire:click="$toggle('relationFormShow')">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    <span>Neue Beziehung</span>
                                </x-ui-button>
                            </div>
                            <p class="text-xs text-[var(--ui-muted)] mb-4">Von <strong>{{ $entity->name }}</strong> ausgehende Beziehungen zu anderen Einheiten.</p>

                            {{-- Neue Beziehung erstellen (inline) --}}
                            @if($relationFormShow)
                                <div class="border border-[var(--ui-border)]/60 rounded-lg p-4 mb-4 bg-[var(--ui-muted-5)]">
                                    <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Neue Beziehung</h4>
                                    <div class="grid grid-cols-2 gap-3 mb-3">
                                        <div>
                                            <x-ui-input-select
                                                name="relation_to_entity_id"
                                                label="Ziel-Einheit"
                                                :options="$this->availableRelationEntities->map(fn($e) => ['value' => (string) $e->id, 'label' => $e->name . ' (' . ($e->type->name ?? '') . ')'])->toArray()"
                                                nullable
                                                nullLabel="– Einheit auswählen –"
                                                wire:model.live="relationForm.to_entity_id"
                                            />
                                            <p class="text-xs text-[var(--ui-muted)] mt-1">Mit welcher Einheit besteht die Beziehung?</p>
                                            @error('relationForm.to_entity_id') <p class="text-xs text-[var(--ui-danger)] mt-1">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <x-ui-input-select
                                                name="relation_type_id"
                                                label="Art der Beziehung"
                                                :options="$this->availableRelationTypes->map(fn($t) => ['value' => (string) $t->id, 'label' => $t->name])->toArray()"
                                                nullable
                                                nullLabel="– Beziehungstyp auswählen –"
                                                wire:model.live="relationForm.relation_type_id"
                                            />
                                            <p class="text-xs text-[var(--ui-muted)] mt-1">z.B. "liefert an", "beauftragt", "ist Dienstleister für"</p>
                                            @error('relationForm.relation_type_id') <p class="text-xs text-[var(--ui-danger)] mt-1">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 mb-3">
                                        <x-ui-input-text name="relation_valid_from" label="Gültig von (optional)" type="date" wire:model.live="relationForm.valid_from" />
                                        <x-ui-input-text name="relation_valid_to" label="Gültig bis (optional)" type="date" wire:model.live="relationForm.valid_to" />
                                    </div>
                                    <div class="flex gap-2">
                                        <x-ui-button variant="primary" size="sm" wire:click="createRelation">
                                            @svg('heroicon-o-check', 'w-4 h-4')
                                            <span>Erstellen</span>
                                        </x-ui-button>
                                        <x-ui-button variant="secondary-ghost" size="sm" wire:click="$set('relationFormShow', false)">
                                            Abbrechen
                                        </x-ui-button>
                                    </div>
                                </div>
                            @endif

                            {{-- Liste --}}
                            <div class="space-y-2">
                                @forelse($this->relationsFrom as $relation)
                                    @include('organization::livewire.entity.partials.relation-card-inline', [
                                        'relation' => $relation,
                                        'direction' => 'from',
                                        'thisEntity' => $entity,
                                        'otherEntity' => $relation->toEntity,
                                    ])
                                @empty
                                    <div class="p-6 text-center rounded-lg border border-dashed border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                        @svg('heroicon-o-arrow-right', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-0.5">Keine ausgehenden Beziehungen</p>
                                        <p class="text-xs text-[var(--ui-muted)]">Klicke "Neue Beziehung" um eine Beziehung zu einer anderen Einheit zu erstellen.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        {{-- Eingehende Beziehungen --}}
                        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                            <h3 class="text-sm font-semibold text-[var(--ui-secondary)] flex items-center gap-2 mb-1">
                                @svg('heroicon-o-arrow-left', 'w-4 h-4 text-[var(--ui-info)]')
                                Eingehende Beziehungen
                                <span class="text-xs font-normal text-[var(--ui-muted)]">({{ $this->relationsTo->count() }})</span>
                            </h3>
                            <p class="text-xs text-[var(--ui-muted)] mb-4">Beziehungen, die von anderen Einheiten auf <strong>{{ $entity->name }}</strong> zeigen.</p>

                            <div class="space-y-2">
                                @forelse($this->relationsTo as $relation)
                                    @include('organization::livewire.entity.partials.relation-card-inline', [
                                        'relation' => $relation,
                                        'direction' => 'to',
                                        'thisEntity' => $entity,
                                        'otherEntity' => $relation->fromEntity,
                                    ])
                                @empty
                                    <div class="p-6 text-center rounded-lg border border-dashed border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                        @svg('heroicon-o-arrow-left', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                        <p class="text-sm font-medium text-[var(--ui-secondary)] mb-0.5">Keine eingehenden Beziehungen</p>
                                        <p class="text-xs text-[var(--ui-muted)]">Andere Einheiten können Beziehungen zu dieser Entity anlegen.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tab: Person --}}
                @if($this->hasLinkedUser)
                    <div x-show="tab === 'person'" x-cloak>
                        <livewire:organization.entity.person-activity :entity="$entity" :key="'person-activity-'.$entity->id" />
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>

    {{-- Alpine treeNode component for recursive tree rendering --}}
    <script>
    document.addEventListener('alpine:init', () => {
        // Store linkConfig and linkIconSvgs globally for tree nodes
        Alpine.store('treeConfig', {
            linkConfig: @js(collect($this->linkTypeConfig)->map(fn($c) => ['label' => $c['label'], 'icon' => $c['icon']])),
            linkIconSvgs: @js($this->linkTypeIconSvgs),
            displayRules: @js($this->displayRules),
        });
        const escHtml = (s) => s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';

        const chevronSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[var(--ui-muted)]"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>`;
        const spinnerSvg = `<svg class="w-4 h-4 animate-spin text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;
        const externalSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-[var(--ui-muted)]"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>`;

        function formatTime(totalMin) {
            const h = Math.floor(totalMin / 60);
            const m = String(totalMin % 60).padStart(2, '0');
            return h + ':' + m + 'h';
        }

        function formatOpenTime(totalMin, billedMin) {
            const open = totalMin - billedMin;
            if (open <= 0) return '';
            return Math.floor(open / 60) + ':' + String(open % 60).padStart(2, '0') + 'h offen';
        }

        // Generic rule-based metadata renderer
        function renderLinkMeta(link, type) {
            const rules = Alpine.store('treeConfig').displayRules[type];
            if (!rules) return '';
            let parts = [];
            for (const rule of rules) {
                if (rule.format === 'expandable_children') continue;
                const val = link[rule.field];
                if (val === null || val === undefined) continue;
                switch (rule.format) {
                    case 'text':
                        if (!val) break;
                        let text = escHtml(String(val));
                        if (rule.suffix) text += ' ' + escHtml(rule.suffix);
                        if (rule.css_class) text = `<span class="${escHtml(rule.css_class)}">${text}</span>`;
                        parts.push(text);
                        break;
                    case 'prefixed_text':
                        if (!val) break;
                        parts.push((rule.prefix ? escHtml(rule.prefix) + ' ' : '') + escHtml(String(val)));
                        break;
                    case 'time':
                        if (val > 0) parts.push(formatTime(val));
                        break;
                    case 'count':
                        if (val > 0) {
                            let suffix = rule.suffix || '';
                            if (rule.suffix_plural && val > 1) suffix = rule.suffix_plural;
                            parts.push(val + (suffix ? ' ' + suffix : ''));
                        }
                        break;
                    case 'count_ratio':
                        if (val > 0) {
                            const done = link[rule.done_field] || 0;
                            parts.push(`${done}/${val}` + (rule.suffix ? ' ' + rule.suffix : ''));
                        }
                        break;
                    case 'percentage':
                        if (val > 0) parts.push(val + '%' + (rule.suffix ? ' ' + rule.suffix : ''));
                        break;
                    case 'boolean_done':
                        if (val) parts.push('<span class="text-green-600">erledigt</span>');
                        break;
                    case 'boolean_active':
                        if (val === false) parts.push('<span class="text-amber-600">inaktiv</span>');
                        break;
                    case 'boolean_published':
                        if (val) parts.push('<span class="text-green-600">veröffentlicht</span>');
                        break;
                    case 'boolean_pinned':
                        if (val) parts.push('angepinnt');
                        break;
                    case 'boolean_frog':
                        if (val) parts.push('<span class="text-green-700">🐸</span>');
                        break;
                    case 'badge':
                        if (val) parts.push(escHtml(String(val)));
                        break;
                }
            }
            if (parts.length === 0) return '';
            return `<span class="inline-flex items-center gap-1.5 text-[10px] text-[var(--ui-muted)] flex-shrink-0">${parts.join(' · ')}</span>`;
        }

        // Shared render function for a node card (used at all Alpine levels)
        window._treeRenderNode = function(node, linkConfig, linkIconSvgs) {
            const isExpandable = node.has_children || (node.own_links_grouped && node.own_links_grouped.length > 0);
            const totalMin = node.cascaded_time ? node.cascaded_time.total_minutes : 0;
            const billedMin = node.cascaded_time ? node.cascaded_time.billed_minutes : 0;
            const openMin = totalMin - billedMin;

            let html = `<div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3 ${!node.is_active ? 'opacity-50' : ''}">`;
            html += `<div class="flex items-center gap-2 ${isExpandable ? 'cursor-pointer' : ''}" @click="nodeToggle()">`;

            // Chevron/spinner
            html += `<div class="w-5 h-5 flex items-center justify-center flex-shrink-0">`;
            if (isExpandable) {
                html += `<template x-if="nodeLoading">${spinnerSvg}</template>`;
                html += `<template x-if="!nodeLoading"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200" :class="{ 'rotate-90': nodeExpanded }"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg></template>`;
            }
            html += `</div>`;

            // Type icon (pre-rendered SVG)
            if (node.type_icon_svg) {
                html += node.type_icon_svg;
            }

            // Name link
            html += `<a href="/organization/entities/${node.id}" class="text-sm font-semibold text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate" @click.stop>${escHtml(node.name)}</a>`;

            // Code
            if (node.code) {
                html += `<span class="text-xs text-[var(--ui-muted)] font-mono flex-shrink-0">${escHtml(node.code)}</span>`;
            }

            // Type badge
            html += `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0">${escHtml(node.type_name)}</span>`;

            // Time
            if (totalMin > 0) {
                const dotClass = openMin > 0 ? 'bg-amber-400' : 'bg-green-500';
                html += `<div class="flex items-center gap-2 ml-auto flex-shrink-0">`;
                html += `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--ui-secondary)]">`;
                html += `<span class="w-2 h-2 rounded-full flex-shrink-0 ${dotClass}"></span>`;
                html += formatTime(totalMin);
                html += `</span>`;
                if (openMin > 0) {
                    html += `<span class="text-[10px] text-amber-600 font-medium">${formatOpenTime(totalMin, billedMin)}</span>`;
                }
                html += `</div>`;
            }

            html += `</div>`; // end row 1

            // Row 2: Link pills
            if (node.total_links > 0 || node.descendant_count > 0) {
                html += `<div class="flex items-center gap-1.5 mt-1.5 ml-7 flex-wrap">`;
                if (node.cascaded_link_counts) {
                    for (const [type, count] of Object.entries(node.cascaded_link_counts)) {
                        if (count > 0 && linkConfig[type]) {
                            const iconSvg = linkIconSvgs[type] ? linkIconSvgs[type].replace('w-4 h-4', 'w-3 h-3') : '';
                            html += `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/20">`;
                            html += iconSvg;
                            html += `${escHtml(linkConfig[type].label)} <span class="font-semibold text-[var(--ui-secondary)]">${count}</span>`;
                            html += `</span>`;
                        }
                    }
                }
                if (node.descendant_count > 0) {
                    html += `<span class="text-[10px] text-[var(--ui-muted)] ml-1">inkl. ${node.descendant_count} ${node.descendant_count === 1 ? 'Untereinheit' : 'Untereinheiten'}</span>`;
                }
                html += `</div>`;
            }

            html += `</div>`; // end card
            return html;
        };

        // Find expandable_children rule for a type
        function getExpandableRule(type) {
            const rules = Alpine.store('treeConfig').displayRules[type];
            if (!rules) return null;
            return rules.find(r => r.format === 'expandable_children') || null;
        }

        // Shared render function for link groups (used at all Alpine levels)
        window._treeRenderLinkGroups = function(node, linkConfig, linkIconSvgs) {
            if (!node.own_links_grouped || node.own_links_grouped.length === 0) return '';

            let html = '';
            for (const group of node.own_links_grouped) {
                const iconSvg = linkIconSvgs[group.type] || '';
                const expandRule = getExpandableRule(group.type);
                html += `<div x-data="{ gOpen: $store.tree.allExpanded, init() { this.$watch('$store.tree.allExpanded', v => this.gOpen = v); } }" class="ml-6 border-l-2 border-[var(--ui-border)]/20">`;
                // Group header
                html += `<div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3 cursor-pointer" @click.stop="gOpen = !gOpen">`;
                html += `<div class="flex items-center gap-2">`;
                html += `<div class="w-5 h-5 flex items-center justify-center flex-shrink-0"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200" :class="{ 'rotate-90': gOpen }"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg></div>`;
                html += iconSvg;
                html += `<span class="text-sm font-medium text-[var(--ui-secondary)]">${escHtml(group.label)}</span>`;
                html += `<span class="text-xs text-[var(--ui-muted)]">(${group.items.length})</span>`;
                if (group.group_logged_minutes > 0) {
                    html += `<span class="text-xs text-[var(--ui-muted)] ml-auto flex-shrink-0">${formatTime(group.group_logged_minutes)}</span>`;
                }
                html += `</div></div>`;

                // Group items
                html += `<div x-show="gOpen" x-collapse x-cloak>`;
                for (const link of group.items) {
                    // Generic expandable_children detection
                    const childrenField = expandRule ? expandRule.field : null;
                    const hasChildren = childrenField && link[childrenField] && link[childrenField].length > 0;
                    const linkId = 'link_' + link.id;
                    const doneField = expandRule ? expandRule.done_field : 'is_done';
                    const linkIsDone = link.done || link.is_done;
                    const doneShowAttr = linkIsDone ? ' x-show="$store.tree.showDone" x-transition' : '';
                    const doneNameClass = linkIsDone ? ' line-through opacity-60' : '';

                    html += `<div class="ml-6 border-l-2 border-[var(--ui-border)]/20"${hasChildren ? ` x-data="{ ${linkId}: $store.tree.allExpanded, init() { this.$watch('$store.tree.allExpanded', v => this.${linkId} = v); } }"` : ''}${doneShowAttr}>`;
                    html += `<div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">`;
                    html += `<div class="flex items-center gap-2${hasChildren ? ' cursor-pointer' : ''}"${hasChildren ? ` @click="${linkId} = !${linkId}"` : ''}>`;

                    // Chevron for expandable items
                    html += `<div class="w-5 h-5 flex items-center justify-center flex-shrink-0">`;
                    if (hasChildren) {
                        html += `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200" :class="{ 'rotate-90': ${linkId} }"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>`;
                    }
                    html += `</div>`;

                    html += iconSvg;
                    if (link.url) {
                        html += `<a href="${escHtml(link.url)}" class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate${doneNameClass}" @click.stop>${escHtml(link.name)}</a>`;
                    } else {
                        html += `<span class="text-sm font-medium text-[var(--ui-secondary)] truncate${doneNameClass}">${escHtml(link.name)}</span>`;
                    }
                    html += renderLinkMeta(link, group.type);
                    if (link.status) {
                        html += `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0">${escHtml(link.status)}</span>`;
                    }
                    if (link.url) {
                        html += `<div class="ml-auto flex-shrink-0">${externalSvg}</div>`;
                    }
                    html += `</div></div>`;

                    // Generic expandable children
                    if (hasChildren) {
                        const childType = expandRule.child_type;
                        const childIconSvg = linkIconSvgs[childType] || '';
                        const childRules = Alpine.store('treeConfig').displayRules[childType];
                        html += `<div x-show="${linkId}" x-collapse x-cloak>`;
                        for (const child of link[childrenField]) {
                            const childDoneField = expandRule.done_field || 'is_done';
                            const childIsDone = child[childDoneField];
                            const childDoneClass = childIsDone ? 'line-through opacity-60' : '';
                            const childDoneShow = childIsDone ? ' x-show="$store.tree.showDone" x-transition' : '';
                            const childName = child[expandRule.name_field || 'name'] || '—';
                            html += `<div class="ml-6 border-l-2 border-[var(--ui-border)]/20"${childDoneShow}>`;
                            html += `<div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">`;
                            html += `<div class="flex items-center gap-2">`;
                            html += `<div class="w-5 h-5 flex-shrink-0"></div>`;
                            html += childIconSvg;
                            html += `<span class="text-sm font-medium text-[var(--ui-secondary)] truncate ${childDoneClass}">${escHtml(childName)}</span>`;
                            // Render child metadata using its own display rules
                            html += renderLinkMeta(child, childType);
                            html += `</div></div></div>`;
                        }
                        html += `</div>`;
                    }

                    html += `</div>`;
                }
                html += `</div></div>`;
            }
            return html;
        };

        Alpine.store('tree', {
            allExpanded: false,
            showDone: false,
            preloadedNodes: {},
            loading: false,
            async expandAll(wire) {
                this.loading = true;
                try {
                    this.preloadedNodes = await wire.loadEntireTree();
                    this.allExpanded = true;
                } finally {
                    this.loading = false;
                }
            },
            collapseAll() {
                this.allExpanded = false;
                this.preloadedNodes = {};
            },
        });

        Alpine.data('treeNode', (node) => ({
            nodeExpanded: false,
            nodeLoading: false,
            nodeChildren: [],
            get nodeIsExpandable() { return node.has_children || (node.own_links_grouped && node.own_links_grouped.length > 0); },
            init() {
                this.$watch('$store.tree.allExpanded', (val) => {
                    if (val && this.nodeIsExpandable) {
                        this.nodeExpand();
                    } else if (!val) {
                        this.nodeExpanded = false;
                        this.nodeChildren = [];
                    }
                });
            },
            async nodeExpand() {
                if (node.has_children && this.nodeChildren.length === 0) {
                    const store = Alpine.store('tree');
                    const preloaded = store?.preloadedNodes?.[node.id];
                    if (preloaded) {
                        this.nodeChildren = preloaded;
                    }
                }
                this.nodeExpanded = true;
            },
            async nodeToggle() {
                if (!this.nodeIsExpandable) return;
                if (this.nodeExpanded) { this.nodeExpanded = false; return; }
                if (node.has_children && this.nodeChildren.length === 0) {
                    this.nodeLoading = true;
                    try {
                        const store = Alpine.store('tree');
                        const preloaded = store?.preloadedNodes?.[node.id];
                        this.nodeChildren = preloaded || await this.$wire.loadChildNodes(node.id);
                    } finally {
                        this.nodeLoading = false;
                    }
                }
                this.nodeExpanded = true;
            },
            renderNode(n) {
                const cfg = Alpine.store('treeConfig');
                return window._treeRenderNode(n, cfg.linkConfig, cfg.linkIconSvgs);
            },
            renderLinkGroups(n) {
                const cfg = Alpine.store('treeConfig');
                return window._treeRenderLinkGroups(n, cfg.linkConfig, cfg.linkIconSvgs);
            },
        }));
    });
    </script>

    <!-- Create Team Modal -->
    <x-ui-modal
        wire:model="showCreateTeamModal"
        size="md"
    >
        <x-slot name="header">
            Team aus Entität erstellen
        </x-slot>

        <div class="space-y-4">
            <div class="space-y-4">
                <x-ui-input-text
                    name="team_name"
                    label="Team-Name"
                    wire:model.live="newTeam.name"
                    required
                    placeholder="Name des Teams"
                />

                <x-ui-input-select
                    name="parent_team_id"
                    label="Eltern-Team (optional)"
                    :options="$this->availableTeams"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    nullLabel="Kein Eltern-Team"
                    wire:model.live="newTeam.parent_team_id"
                />
            </div>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button
                    type="button"
                    variant="secondary-outline"
                    wire:click="closeCreateTeamModal"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="createTeam">
                    @svg('heroicon-o-user-group', 'w-4 h-4 mr-2')
                    Team erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
