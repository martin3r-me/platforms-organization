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
                <x-ui-button variant="ghost" size="sm" href="{{ route('organization.entities.board', $entity) }}">
                    @svg('heroicon-o-squares-2x2', 'w-4 h-4')
                    <span>VSM Board</span>
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
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        @if($entity->parent)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Übergeordnet</span>
                                <a href="{{ route('organization.entities.show', $entity->parent) }}" class="block text-sm font-medium text-[var(--ui-primary)] hover:underline">{{ $entity->parent->name }}</a>
                                <div class="text-xs text-[var(--ui-muted)]">{{ $entity->parent->type->name }}</div>
                            </div>
                        @endif
                        @if($entity->linkedUser)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Verantwortlich</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->linkedUser->name }}</div>
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
            {{-- ═══ Block A: Hero Card — Compact ═══ --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                {{-- Row 1: Name + Badges --}}
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $entity->name }}</h1>
                    <x-ui-badge variant="secondary" size="sm">{{ $entity->type->name }}</x-ui-badge>
                    @php $vsmClass = $entity->type->vsm_class; @endphp
                    @if($vsmClass === 'carrier')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20" title="Carrier — lebensfähiges System (VSM). Kann eigene Perspektive sein.">
                            @svg('heroicon-o-cube-transparent', 'w-3 h-3 mr-1')
                            Carrier · Perspektive möglich
                        </span>
                    @elseif($vsmClass === 'actor')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 ring-1 ring-inset ring-slate-600/20" title="Actor — füllt VSM-Funktionen aus, empfängt Signale. Keine eigene Perspektive.">Actor</span>
                    @elseif($vsmClass === 'observed')
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20" title="Observed — Umwelt-Entity. Wird von S4 beobachtet, keine eigene Perspektive.">Observed</span>
                    @endif
                    @if($entity->is_active)
                        <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                    @else
                        <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                    @endif
                    @if($entity->code)
                        <span class="text-sm text-[var(--ui-muted)] font-mono">{{ $entity->code }}</span>
                    @endif
                </div>

                {{-- Row 2: Description --}}
                @if($entity->description)
                    <p class="mt-2 text-sm text-[var(--ui-muted)] max-w-2xl line-clamp-2">{{ $entity->description }}</p>
                @endif

                {{-- Row 3: Inline Stats --}}
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
                <div class="mt-4 flex items-center gap-6 flex-wrap">
                    <div class="flex items-center gap-1.5">
                        @svg('heroicon-o-rectangle-group', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $childCount }}</span>
                        <span class="text-xs text-[var(--ui-muted)]">Einheiten{{ $descendantCount > $childCount ? ' (' . $descendantCount . ' gesamt)' : '' }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @svg('heroicon-o-link', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $linkCount }}</span>
                        <span class="text-xs text-[var(--ui-muted)]">Verknüpfungen</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-muted)]')
                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $totalHours }}:{{ str_pad($totalMins, 2, '0', STR_PAD_LEFT) }}h</span>
                        <span class="text-xs text-[var(--ui-muted)]">gesamt</span>
                    </div>
                    @if($openMinutes > 0)
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                            <span class="text-sm font-semibold text-amber-600">{{ $openHours }}:{{ str_pad($openMins, 2, '0', STR_PAD_LEFT) }}h</span>
                            <span class="text-xs text-[var(--ui-muted)]">offen</span>
                        </div>
                    @endif
                </div>

                {{-- Row 4: Health Progress Bar --}}
                @php $analysis = $this->snapshotAnalysis; @endphp
                @if(!empty($analysis))
                    @php
                        $healthColors = [
                            'progressing' => ['bar' => 'bg-green-500', 'text' => 'text-green-700', 'icon' => 'heroicon-o-arrow-trending-up', 'label' => 'Fortschreitend'],
                            'stalled' => ['bar' => 'bg-amber-500', 'text' => 'text-amber-700', 'icon' => 'heroicon-o-pause-circle', 'label' => 'Stagnierend'],
                            'at_risk' => ['bar' => 'bg-red-500', 'text' => 'text-red-700', 'icon' => 'heroicon-o-exclamation-triangle', 'label' => 'Gefährdet'],
                            'completed' => ['bar' => 'bg-blue-500', 'text' => 'text-blue-700', 'icon' => 'heroicon-o-check-circle', 'label' => 'Abgeschlossen'],
                        ];
                        $hc = $healthColors[$analysis['health_status']] ?? $healthColors['progressing'];
                        $pct = (int) $analysis['completion_rate'];
                    @endphp
                    <div class="mt-4 flex items-center gap-4">
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            @svg($hc['icon'], 'w-4 h-4 ' . $hc['text'])
                            <span class="text-xs font-medium {{ $hc['text'] }}">{{ $hc['label'] }}</span>
                        </div>
                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full {{ $hc['bar'] }} rounded-full transition-all" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="text-xs font-semibold {{ $hc['text'] }} flex-shrink-0">{{ $pct }}%</span>
                        <div class="flex items-center gap-3 text-xs text-[var(--ui-muted)] flex-shrink-0">
                            <span>{{ $analysis['items_done'] }}/{{ $analysis['items_total'] }}</span>
                            @if($analysis['velocity_daily_avg'] > 0)
                                <span>{{ $analysis['velocity_daily_avg'] }}/d</span>
                            @endif
                            @if($analysis['estimated_days_remaining'] !== null)
                                <span>~{{ $analysis['estimated_days_remaining'] }}d</span>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Row 5: Context Summary Strip --}}
                @php $contextSummary = $this->contextSummary; @endphp
                @if(!empty($contextSummary))
                    <div class="mt-4 flex items-center gap-2 flex-wrap">
                        @foreach($contextSummary as $ctx)
                            <button
                                type="button"
                                x-data
                                @click="$wire.set('activeTab', 'relations')"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/30 hover:bg-[var(--ui-muted-10)] hover:text-[var(--ui-secondary)] transition-colors cursor-pointer"
                            >
                                {{ $ctx['label'] }}
                                <span class="font-bold text-[var(--ui-secondary)]">{{ $ctx['count'] }}</span>
                            </button>
                        @endforeach
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
                            @click="tab = 'analyse'; $wire.loadAnalyseData()"
                            :class="tab === 'analyse'
                                ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                            class="px-4 py-2.5 text-sm transition-colors"
                        >
                            @svg('heroicon-o-chart-bar-square', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                            Analyse
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
                        @if($this->isCarrierEntity)
                            @php
                                $vsmMatrix = $this->vsmMatrix;
                                $vacancyCount = collect($vsmMatrix)->where('is_vacant', true)->count();
                            @endphp
                            <button
                                @click="tab = 'vsm'"
                                :class="tab === 'vsm'
                                    ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                    : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                                class="px-4 py-2.5 text-sm transition-colors flex items-center gap-1.5"
                                title="VSM-Zellen-Besetzung aus Sicht dieser Carrier-Entity"
                            >
                                @svg('heroicon-o-squares-2x2', 'w-4 h-4 inline-block -mt-0.5')
                                VSM
                                @if($vacancyCount > 0)
                                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold text-amber-700 bg-amber-100 ring-1 ring-inset ring-amber-600/20 rounded-full" title="{{ $vacancyCount }} unbesetzte Zellen">{{ $vacancyCount }}</span>
                                @endif
                            </button>
                        @endif
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
                        @if($this->isPersonEntity)
                            <button
                                @click="tab = 'skills'"
                                :class="tab === 'skills'
                                    ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                    : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                                class="px-4 py-2.5 text-sm transition-colors"
                            >
                                @svg('heroicon-o-academic-cap', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                                Skills
                            </button>
                        @endif
                        <button
                            @click="tab = 'signals'"
                            :class="tab === 'signals'
                                ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                            class="px-4 py-2.5 text-sm transition-colors flex items-center gap-1.5"
                        >
                            @svg('heroicon-o-bell-alert', 'w-4 h-4 inline-block -mt-0.5')
                            Signale
                            @php
                                $openSignalCount = \Platform\Organization\Models\OrganizationSignal::where('entity_id', $this->entity->id)->where('status', 'open')->count();
                            @endphp
                            @if($openSignalCount > 0)
                                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold text-white bg-red-500 rounded-full">{{ $openSignalCount }}</span>
                            @endif
                        </button>
                    </nav>
                </div>

                {{-- Tab: Analyse --}}
                <div x-show="tab === 'analyse'" x-cloak>
                    @if($analyseLoaded)
                        <div class="space-y-6">
                            {{-- Dimensionen-Radar --}}
                            @php $radar = $this->dimensionRadar; @endphp
                            @if(!empty($radar))
                                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                                    <h2 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4">7&frac12; Dimensionen</h2>
                                    @include('organization::livewire.entity.partials.dimension-radar', ['radar' => $radar])
                                </div>
                            @endif

                            {{-- Bewegung (7 Tage) --}}
                            @php $movement = $this->movement; @endphp
                            @if(!empty($movement['metrics']))
                                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6" x-data="{ movementGrouping: 'dimension' }">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center gap-3">
                                            <h2 class="text-sm font-semibold text-[var(--ui-secondary)]">Bewegung (7 Tage)</h2>
                                            <div class="flex bg-[var(--ui-muted-5)] rounded p-0.5 border border-[var(--ui-border)]/30">
                                                <button @click="movementGrouping = 'dimension'"
                                                    class="px-2 py-0.5 text-[10px] rounded transition-colors"
                                                    :class="movementGrouping === 'dimension' ? 'bg-white text-[var(--ui-secondary)] shadow-sm' : 'text-[var(--ui-muted)]'">
                                                    Dimension
                                                </button>
                                                <button @click="movementGrouping = 'module'"
                                                    class="px-2 py-0.5 text-[10px] rounded transition-colors"
                                                    :class="movementGrouping === 'module' ? 'bg-white text-[var(--ui-secondary)] shadow-sm' : 'text-[var(--ui-muted)]'">
                                                    Modul
                                                </button>
                                            </div>
                                        </div>
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

                                    {{-- Compact Delta Badges --}}
                                    @php
                                        $nonZero = collect($movement['metrics'])->filter(fn($m) => $m['delta'] != 0)->take(5);
                                    @endphp
                                    @if($nonZero->isNotEmpty())
                                        <div class="flex items-center gap-2 flex-wrap mb-4">
                                            @foreach($nonZero as $m)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium
                                                    {{ $m['sentiment'] === 'positive' ? 'bg-green-50 text-green-700 border border-green-200' : '' }}
                                                    {{ $m['sentiment'] === 'negative' ? 'bg-red-50 text-red-700 border border-red-200' : '' }}
                                                    {{ $m['sentiment'] === 'neutral' ? 'bg-gray-50 text-gray-600 border border-gray-200' : '' }}
                                                ">
                                                    {{ $m['delta_formatted'] }} {{ $m['label'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Full Metric Grid --}}
                                    @php
                                        $totalMetrics = collect($movement['metrics'])->filter(fn($m) => $m['current'] > 0 || $m['previous'] > 0)->count();
                                        $dimensionLabels = \Platform\Organization\Services\EntityLinkRegistry::allDimensions();
                                    @endphp
                                    @if($movementStream || $totalMetrics > 5)
                                        <div x-show="movementGrouping === 'dimension'">
                                            @foreach($movement['metrics_by_dimension'] as $dimKey => $metrics)
                                                <div class="mb-3">
                                                    <div class="text-[10px] font-medium text-[var(--ui-muted)] uppercase mb-1.5">
                                                        {{ $dimensionLabels[$dimKey]['label'] ?? ucfirst($dimKey) }}
                                                    </div>
                                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                                        @foreach($metrics as $m)
                                                            @if($m['current'] != 0 || $m['previous'] != 0)
                                                                <div class="py-2 px-2.5 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/20">
                                                                    <div class="text-sm font-bold text-[var(--ui-text)]">
                                                                        {{ $m['current_formatted'] }}
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

                                        <div x-show="movementGrouping === 'module'" x-cloak>
                                            @foreach($movement['metrics_by_group'] as $groupKey => $metrics)
                                                <div class="mb-3">
                                                    <div class="text-[10px] font-medium text-[var(--ui-muted)] uppercase mb-1.5">
                                                        {{ ucfirst($groupKey) }}
                                                    </div>
                                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                                        @foreach($metrics as $m)
                                                            @if($m['current'] != 0 || $m['previous'] != 0)
                                                                <div class="py-2 px-2.5 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/20">
                                                                    <div class="text-sm font-bold text-[var(--ui-text)]">
                                                                        {{ $m['current_formatted'] }}
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
                                </div>
                            @endif

                            {{-- Trends (14d + 12 Monate) --}}
                            @php
                                $trend = $this->snapshotTrend;
                                $monthlyData = $this->monthlyTimeData;
                                $chartMonths = $monthlyData['months'] ?? [];
                                $monthlyMax = $monthlyData['max_minutes'] ?? 0;
                                $hasTrend = !empty($trend) && count($trend['snapshots'] ?? []) >= 1;
                                $hasMonthly = $monthlyMax > 0;
                            @endphp
                            @if($hasTrend || $hasMonthly)
                                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6"
                                     x-data="{ trendTab: '{{ $hasTrend ? '14d' : '12m' }}' }">
                                    <div class="flex items-center gap-4 mb-4 border-b border-[var(--ui-border)]/40 -mx-6 px-6 pb-3">
                                        @if($hasTrend)
                                            <button @click="trendTab = '14d'"
                                                class="text-sm font-medium transition-colors pb-1"
                                                :class="trendTab === '14d' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'">
                                                14 Tage
                                            </button>
                                        @endif
                                        @if($hasMonthly)
                                            <button @click="trendTab = '12m'"
                                                class="text-sm font-medium transition-colors pb-1"
                                                :class="trendTab === '12m' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'">
                                                12 Monate
                                            </button>
                                        @endif
                                    </div>

                                    @if($hasTrend)
                                        <div x-show="trendTab === '14d'" x-cloak>
                                            <div class="flex items-center justify-end gap-3 mb-3">
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
                                                            {{ $snap['date'] }} {{ $snap['period'] }}: {{ $snap['items_done'] }}/{{ $snap['items_total'] }} Items,
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

                                    @if($hasMonthly)
                                        <div x-show="trendTab === '12m'" x-cloak x-data="{ tooltip: null }">
                                            <div class="flex items-center justify-end gap-3 mb-3">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                                    <span class="text-[10px] text-[var(--ui-muted)]">abgerechnet</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                                    <span class="text-[10px] text-[var(--ui-muted)]">offen</span>
                                                </div>
                                            </div>
                                            <div class="flex items-end gap-1.5" style="height: 136px;">
                                                @foreach($chartMonths as $idx => $m)
                                                    <div class="flex-1 flex flex-col items-center h-full relative"
                                                         @mouseenter="tooltip = {{ $idx }}"
                                                         @mouseleave="tooltip = null">
                                                        <div x-show="tooltip === {{ $idx }}" x-cloak
                                                             class="absolute bottom-full mb-2 px-2.5 py-1.5 rounded-lg bg-[var(--ui-secondary)] text-white text-[10px] whitespace-nowrap z-10 shadow-lg pointer-events-none"
                                                             x-transition.opacity>
                                                            {{ $m['label'] }} {{ $m['year'] }}:
                                                            {{ intdiv($m['total_minutes'], 60) }}:{{ str_pad($m['total_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h
                                                            ({{ intdiv($m['billed_minutes'], 60) }}:{{ str_pad($m['billed_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h abgerechnet,
                                                            {{ intdiv($m['open_minutes'], 60) }}:{{ str_pad($m['open_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h offen)
                                                        </div>
                                                        <div class="w-full flex flex-col justify-end flex-1 rounded-t overflow-hidden">
                                                            @if($m['total_minutes'] > 0)
                                                                @php
                                                                    $barHeight = round(($m['total_minutes'] / $monthlyMax) * 120);
                                                                    $billedHeight = $m['billed_minutes'] > 0 ? max(2, round(($m['billed_minutes'] / $monthlyMax) * 120)) : 0;
                                                                    $openHeight = $m['open_minutes'] > 0 ? max(2, $barHeight - $billedHeight) : 0;
                                                                    if ($billedHeight + $openHeight > $barHeight && $barHeight > 4) {
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
                                                                <div class="w-full mt-auto">
                                                                    <div class="w-full bg-[var(--ui-border)]/20 rounded-t" style="height: 1px;"></div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                        <div class="text-[10px] text-[var(--ui-muted)] mt-1 leading-none">{{ $m['label'] }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- KPI Insights --}}
                            @if(!empty($analysis['insights'] ?? []))
                                <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                                    <h2 class="text-sm font-semibold text-[var(--ui-secondary)] mb-3">Erkenntnisse</h2>
                                    <div class="space-y-2">
                                        @foreach($analysis['insights'] as $insight)
                                            <div class="flex items-start gap-2 text-sm">
                                                @if($insight['type'] === 'success')
                                                    @svg('heroicon-o-arrow-trending-up', 'w-4 h-4 text-green-600 flex-shrink-0 mt-0.5')
                                                @elseif($insight['type'] === 'warning')
                                                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5')
                                                @else
                                                    @svg('heroicon-o-information-circle', 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0 mt-0.5')
                                                @endif
                                                <span class="@if($insight['type'] === 'success') text-green-700 @elseif($insight['type'] === 'warning') text-amber-700 @else text-[var(--ui-muted)] @endif">
                                                    {{ $insight['text'] }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-8 text-center">
                            <div class="inline-flex items-center gap-2 text-sm text-[var(--ui-muted)]">
                                <svg class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Analyse-Daten werden geladen...
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Tab: Hierarchie --}}
                <div x-show="tab === 'hierarchy'" x-cloak x-data="{
                    linkConfig: {{ Js::from(collect($this->linkTypeConfig)->map(fn($c) => ['label' => $c['label'], 'icon' => $c['icon']])) }},
                    linkIconSvgs: {{ Js::from($this->linkTypeIconSvgs) }},
                    displayRules: {{ Js::from($this->displayRules) }}
                }">
                    <div class="space-y-6">
                        {{-- ═══ Section 1: Organisationsstruktur (nur Kind-Entities) ═══ --}}
                        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] flex items-center gap-2">
                                    @svg('heroicon-o-rectangle-group', 'w-4 h-4 text-[var(--ui-primary)]')
                                    Organisationsstruktur
                                    @if(count($this->treeNodes) > 0)
                                        <span class="text-xs font-normal text-[var(--ui-muted)]">({{ $this->totalDescendantCount }} Einheiten)</span>
                                    @endif
                                </h3>
                                @if(count($this->treeNodes) > 0)
                                    <div class="flex gap-2" x-data>
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
                                            <span x-text="$store.tree.allExpanded ? 'Einklappen' : 'Alle aufklappen'"></span>
                                        </button>
                                    </div>
                                @endif
                            </div>

                            @if(count($this->treeNodes) > 0)
                                <div class="space-y-6">
                                    @foreach($this->treeNodes as $section)
                                        <div>
                                            <div class="flex items-center gap-2 mb-2 px-1">
                                                <h4 class="text-xs font-bold text-[var(--ui-muted)] uppercase tracking-wider">{{ $section['group_name'] }}</h4>
                                                <span class="text-[10px] text-[var(--ui-muted)]">({{ count($section['nodes']) }})</span>
                                            </div>
                                            <div class="space-y-1">
                                                @foreach($section['nodes'] as $node)
                                                    @include('organization::livewire.entity.partials.tree-node', ['node' => $node, 'depth' => 0])
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="py-6 text-center rounded-lg border border-dashed border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                    @svg('heroicon-o-rectangle-group', 'w-6 h-6 text-[var(--ui-muted)] mx-auto mb-2')
                                    <p class="text-sm text-[var(--ui-muted)]">Keine Untereinheiten vorhanden</p>
                                </div>
                            @endif
                        </div>

                        {{-- ═══ Section 2: Verknüpfungen (eigene Links dieser Entity) ═══ --}}
                        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] flex items-center gap-2">
                                    @svg('heroicon-o-link', 'w-4 h-4 text-[var(--ui-primary)]')
                                    Verknüpfungen
                                    @if(count($this->rootEntityLinks) > 0)
                                        @php $totalLinkItems = collect($this->rootEntityLinks)->sum(fn($g) => count($g['items'])); @endphp
                                        <span class="text-xs font-normal text-[var(--ui-muted)]">({{ $totalLinkItems }})</span>
                                    @endif
                                </h3>
                                @if(count($this->rootEntityLinks) > 0)
                                    <div class="flex gap-2" x-data>
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
                                    </div>
                                @endif
                            </div>

                            @if(count($this->rootEntityLinks) > 0)
                                @php
                                    $rootCascaded = $this->cascadedTimeSummary;
                                    $rootTotalMin = $rootCascaded['total_minutes'];
                                    $rootBilledMin = $rootCascaded['billed_minutes'];
                                    $rootOpenMin = $rootTotalMin - $rootBilledMin;
                                @endphp

                                {{-- Time summary bar --}}
                                @if($rootTotalMin > 0)
                                    <div class="flex items-center gap-4 mb-4 pb-3 border-b border-[var(--ui-border)]/30">
                                        @svg('heroicon-o-clock', 'w-4 h-4 text-[var(--ui-muted)]')
                                        <span class="text-xs text-[var(--ui-muted)]">Gesamt:</span>
                                        <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ intdiv($rootTotalMin, 60) }}:{{ str_pad($rootTotalMin % 60, 2, '0', STR_PAD_LEFT) }}h</span>
                                        @if($rootOpenMin > 0)
                                            <span class="text-xs text-amber-600 font-medium">{{ intdiv($rootOpenMin, 60) }}:{{ str_pad($rootOpenMin % 60, 2, '0', STR_PAD_LEFT) }}h offen</span>
                                        @endif
                                    </div>
                                @endif

                                {{-- Link groups --}}
                                <div class="space-y-1">
                                    @foreach($this->rootEntityLinks as $group)
                                        <div x-data="{ groupOpen: false, init() { this.$watch('$store.tree.allExpanded', v => this.groupOpen = v); } }">
                                            {{-- Group Header --}}
                                            <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2.5 px-3 cursor-pointer"
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
                                                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 text-xs font-bold text-[var(--ui-secondary)] bg-[var(--ui-muted-5)] rounded-full">{{ count($group['items']) }}</span>
                                                    @if(($group['group_logged_minutes'] ?? 0) > 0)
                                                        <span class="text-xs text-[var(--ui-muted)] ml-auto flex-shrink-0">
                                                            {{ intdiv($group['group_logged_minutes'], 60) }}:{{ str_pad($group['group_logged_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Group Items --}}
                                            <div x-show="groupOpen" x-collapse x-cloak class="ml-7 border-l-2 border-[var(--ui-border)]/20">
                                                @foreach($group['items'] as $link)
                                                    @include('organization::livewire.entity.partials.link-item', ['link' => $link, 'group' => $group])
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="py-6 text-center rounded-lg border border-dashed border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                    @svg('heroicon-o-link', 'w-6 h-6 text-[var(--ui-muted)] mx-auto mb-2')
                                    <p class="text-sm text-[var(--ui-muted)]">Keine Verknüpfungen vorhanden</p>
                                </div>
                            @endif
                        </div>
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

                {{-- Tab: Skills --}}
                @if($this->isPersonEntity)
                    <div x-show="tab === 'skills'" x-cloak>
                        <div class="space-y-6">
                            {{-- Skills --}}
                            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4 flex items-center gap-2">
                                    @svg('heroicon-o-academic-cap', 'w-4 h-4 text-[var(--ui-primary)]')
                                    Skills
                                    <span class="text-xs font-normal text-[var(--ui-muted)]">({{ $this->entitySkills->count() }})</span>
                                </h3>

                                {{-- Zugeordnete Skills --}}
                                @if($this->entitySkills->isNotEmpty())
                                    <table class="w-full text-sm mb-4">
                                        <thead>
                                            <tr class="text-xs text-[var(--ui-muted)] uppercase">
                                                <th class="text-left py-1 px-2">Name</th>
                                                <th class="text-left py-1 px-2">Kategorie</th>
                                                <th class="text-left py-1 px-2">Level</th>
                                                <th class="text-left py-1 px-2">Zertifiziert am</th>
                                                <th class="text-left py-1 px-2">Notizen</th>
                                                <th class="py-1 px-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($this->entitySkills as $skill)
                                                <tr class="border-t border-[var(--ui-border)]" wire:key="entity-skill-{{ $skill->id }}">
                                                    <td class="py-2 px-2 font-medium text-[var(--ui-secondary)]">{{ $skill->name }}</td>
                                                    <td class="py-2 px-2">
                                                        <x-ui-badge variant="secondary" size="sm">{{ ucfirst($skill->category) }}</x-ui-badge>
                                                    </td>
                                                    <td class="py-2 px-2">
                                                        <select
                                                            wire:change="updatePersonSkillLevel({{ $skill->id }}, $event.target.value)"
                                                            class="rounded-md border-gray-300 shadow-sm text-xs py-1 px-2"
                                                        >
                                                            <option value="basic" {{ $skill->pivot->level === 'basic' ? 'selected' : '' }}>Basic</option>
                                                            <option value="advanced" {{ $skill->pivot->level === 'advanced' ? 'selected' : '' }}>Advanced</option>
                                                            <option value="expert" {{ $skill->pivot->level === 'expert' ? 'selected' : '' }}>Expert</option>
                                                        </select>
                                                    </td>
                                                    <td class="py-2 px-2 text-xs text-[var(--ui-muted)]">
                                                        {{ $skill->pivot->certified_at ? \Carbon\Carbon::parse($skill->pivot->certified_at)->format('d.m.Y') : '—' }}
                                                    </td>
                                                    <td class="py-2 px-2 text-xs text-[var(--ui-muted)]">
                                                        {{ $skill->pivot->notes ?? '—' }}
                                                    </td>
                                                    <td class="py-2 px-2 text-right">
                                                        <button wire:click="removePersonSkill({{ $skill->id }})" wire:confirm="Skill-Zuordnung entfernen?" class="text-red-500 hover:text-red-700 p-1">
                                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif

                                {{-- Autocomplete --}}
                                <div class="relative">
                                    <input type="text" wire:model.live.debounce.300ms="personSkillSearch"
                                        placeholder="Skill aus Katalog hinzufügen..."
                                        class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                    @if($personSkillSearch && $this->availablePersonSkills->isNotEmpty())
                                        <div class="absolute z-10 w-full mt-1 bg-white rounded-lg border border-[var(--ui-border)] shadow-lg max-h-48 overflow-y-auto">
                                            @foreach($this->availablePersonSkills as $s)
                                                <button type="button" wire:click="assignPersonSkill({{ $s->id }}, 'basic')"
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-[var(--ui-muted-5)] transition-colors flex items-center gap-2">
                                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $s->name }}</span>
                                                    <x-ui-badge variant="secondary" size="sm">{{ ucfirst($s->category) }}</x-ui-badge>
                                                </button>
                                            @endforeach
                                        </div>
                                    @elseif($personSkillSearch && $this->availablePersonSkills->isEmpty())
                                        <div class="absolute z-10 w-full mt-1 bg-white rounded-lg border border-[var(--ui-border)] shadow-lg px-4 py-3">
                                            <span class="text-sm text-[var(--ui-muted)]">Keine Skills gefunden.</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Soft Skills --}}
                            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                                <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-4 flex items-center gap-2">
                                    @svg('heroicon-o-heart', 'w-4 h-4 text-[var(--ui-primary)]')
                                    Soft Skills
                                    <span class="text-xs font-normal text-[var(--ui-muted)]">({{ $this->entitySoftSkills->count() }})</span>
                                </h3>

                                {{-- Zugeordnete Soft Skills --}}
                                @if($this->entitySoftSkills->isNotEmpty())
                                    <table class="w-full text-sm mb-4">
                                        <thead>
                                            <tr class="text-xs text-[var(--ui-muted)] uppercase">
                                                <th class="text-left py-1 px-2">Name</th>
                                                <th class="text-left py-1 px-2">Level</th>
                                                <th class="text-left py-1 px-2">Notizen</th>
                                                <th class="py-1 px-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($this->entitySoftSkills as $softSkill)
                                                <tr class="border-t border-[var(--ui-border)]" wire:key="entity-soft-skill-{{ $softSkill->id }}">
                                                    <td class="py-2 px-2 font-medium text-[var(--ui-secondary)]">{{ $softSkill->name }}</td>
                                                    <td class="py-2 px-2">
                                                        <select
                                                            wire:change="updatePersonSoftSkillLevel({{ $softSkill->id }}, $event.target.value)"
                                                            class="rounded-md border-gray-300 shadow-sm text-xs py-1 px-2"
                                                        >
                                                            <option value="basic" {{ $softSkill->pivot->level === 'basic' ? 'selected' : '' }}>Basic</option>
                                                            <option value="advanced" {{ $softSkill->pivot->level === 'advanced' ? 'selected' : '' }}>Advanced</option>
                                                            <option value="expert" {{ $softSkill->pivot->level === 'expert' ? 'selected' : '' }}>Expert</option>
                                                        </select>
                                                    </td>
                                                    <td class="py-2 px-2 text-xs text-[var(--ui-muted)]">
                                                        {{ $softSkill->pivot->notes ?? '—' }}
                                                    </td>
                                                    <td class="py-2 px-2 text-right">
                                                        <button wire:click="removePersonSoftSkill({{ $softSkill->id }})" wire:confirm="Soft-Skill-Zuordnung entfernen?" class="text-red-500 hover:text-red-700 p-1">
                                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif

                                {{-- Autocomplete --}}
                                <div class="relative">
                                    <input type="text" wire:model.live.debounce.300ms="personSoftSkillSearch"
                                        placeholder="Soft Skill aus Katalog hinzufügen..."
                                        class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
                                    @if($personSoftSkillSearch && $this->availablePersonSoftSkills->isNotEmpty())
                                        <div class="absolute z-10 w-full mt-1 bg-white rounded-lg border border-[var(--ui-border)] shadow-lg max-h-48 overflow-y-auto">
                                            @foreach($this->availablePersonSoftSkills as $ss)
                                                <button type="button" wire:click="assignPersonSoftSkill({{ $ss->id }}, 'basic')"
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-[var(--ui-muted-5)] transition-colors flex items-center gap-2">
                                                    <span class="font-medium text-[var(--ui-secondary)]">{{ $ss->name }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @elseif($personSoftSkillSearch && $this->availablePersonSoftSkills->isEmpty())
                                        <div class="absolute z-10 w-full mt-1 bg-white rounded-lg border border-[var(--ui-border)] shadow-lg px-4 py-3">
                                            <span class="text-sm text-[var(--ui-muted)]">Keine Soft Skills gefunden.</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Tab: Signale --}}
                <div x-show="tab === 'signals'" x-cloak>
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                        {{-- Header with filter --}}
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-[var(--ui-secondary)] flex items-center gap-2">
                                @svg('heroicon-o-bell-alert', 'w-4 h-4 text-[var(--ui-primary)]')
                                Algedonic Alerts
                            </h3>
                            <select
                                wire:model.live="signalStatusFilter"
                                class="rounded-md border-gray-300 shadow-sm text-xs py-1.5 px-3"
                            >
                                <option value="">Alle</option>
                                <option value="open">Offen</option>
                                <option value="acknowledged">Bestätigt</option>
                                <option value="resolved">Gelöst</option>
                                <option value="dismissed">Verworfen</option>
                            </select>
                        </div>

                        @if($this->entitySignals->isEmpty())
                            <div class="text-center py-8">
                                @svg('heroicon-o-bell-slash', 'w-8 h-8 text-[var(--ui-muted)] mx-auto mb-2')
                                <p class="text-sm text-[var(--ui-muted)]">Keine Signale{{ $signalStatusFilter ? ' mit Status "' . $signalStatusFilter . '"' : '' }}.</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach($this->entitySignals as $signal)
                                    <div class="border border-[var(--ui-border)] rounded-lg p-4" wire:key="signal-{{ $signal->id }}">
                                        <div class="flex items-start justify-between gap-4">
                                            {{-- Left: Severity + Definition + Pattern --}}
                                            <div class="flex items-start gap-3 min-w-0 flex-1">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium flex-shrink-0
                                                    @if($signal->severity === 'critical') bg-red-100 text-red-800
                                                    @elseif($signal->severity === 'warning') bg-amber-100 text-amber-800
                                                    @else bg-blue-100 text-blue-800
                                                    @endif
                                                ">
                                                    {{ ucfirst($signal->severity) }}
                                                </span>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <a href="{{ route('organization.signals.show', $signal) }}" class="text-sm font-medium text-[var(--ui-primary)] hover:underline">
                                                            {{ $signal->definition?->name ?? 'Unbekannt' }}
                                                        </a>
                                                        @if($signal->definition?->pattern_type)
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-600">
                                                                @switch($signal->definition->pattern_type)
                                                                    @case('threshold') Schwellenwert @break
                                                                    @case('trend') Trend @break
                                                                    @case('cross_dimension') Kreuz-Dimension @break
                                                                    @case('ratio') Verhältnis @break
                                                                    @default {{ $signal->definition->pattern_type }}
                                                                @endswitch
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <p class="text-sm text-[var(--ui-muted)] mt-1">{{ $signal->message }}</p>

                                                    {{-- Trigger Metrics (expandable) --}}
                                                    @if($signal->trigger_metrics)
                                                        <div x-data="{ showMetrics: false }" class="mt-1.5">
                                                            <button @click="showMetrics = !showMetrics" class="text-xs text-[var(--ui-primary)] hover:underline flex items-center gap-1">
                                                                @svg('heroicon-o-chart-bar', 'w-3 h-3')
                                                                <span x-text="showMetrics ? 'Metriken ausblenden' : 'Metriken anzeigen'"></span>
                                                            </button>
                                                            <div x-show="showMetrics" x-cloak class="mt-1.5 text-xs text-[var(--ui-muted)] bg-gray-50 rounded p-2 font-mono">
                                                                @foreach($signal->trigger_metrics as $key => $value)
                                                                    <div>{{ $key }}: {{ is_array($value) ? json_encode($value) : $value }}</div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Right: Status + Time + Actions --}}
                                            <div class="flex flex-col items-end gap-2 flex-shrink-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
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
                                                <span class="text-xs text-[var(--ui-muted)]">{{ $signal->created_at->format('d.m.Y H:i') }}</span>

                                                @if($signal->status === 'resolved' && $signal->resolvedByUser)
                                                    <span class="text-xs text-[var(--ui-muted)]">von {{ $signal->resolvedByUser->name }}</span>
                                                @endif

                                                {{-- Action Buttons --}}
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    @if($signal->status === 'open')
                                                        <button wire:click="acknowledgeSignal({{ $signal->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded border border-blue-300 text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors">
                                                            @svg('heroicon-o-check', 'w-3 h-3')
                                                            Bestätigen
                                                        </button>
                                                        <button wire:click="dismissSignal({{ $signal->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded border border-gray-300 text-gray-600 hover:bg-gray-100 transition-colors">
                                                            @svg('heroicon-o-x-mark', 'w-3 h-3')
                                                            Verwerfen
                                                        </button>
                                                    @elseif($signal->status === 'acknowledged')
                                                        <button wire:click="resolveSignal({{ $signal->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded border border-green-300 text-green-700 bg-green-50 hover:bg-green-100 transition-colors">
                                                            @svg('heroicon-o-check-circle', 'w-3 h-3')
                                                            Lösen
                                                        </button>
                                                        <button wire:click="dismissSignal({{ $signal->id }})" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded border border-gray-300 text-gray-600 hover:bg-gray-100 transition-colors">
                                                            @svg('heroicon-o-x-mark', 'w-3 h-3')
                                                            Verwerfen
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Tab: VSM-Matrix --}}
                @if($this->isCarrierEntity)
                    <div x-show="tab === 'vsm'" x-cloak>
                        <div class="space-y-4">
                            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-5">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h2 class="text-base font-semibold text-[var(--ui-secondary)]">VSM-Zellen-Besetzung</h2>
                                        <p class="text-xs text-[var(--ui-muted)] mt-0.5">
                                            Aus Sicht <span class="font-medium text-[var(--ui-secondary)]">{{ $entity->name }}</span> — wer fuellt welche System-Funktion aus?
                                            Mehrfachbesetzung pro Zelle erlaubt.
                                        </p>
                                    </div>
                                    @php
                                        $matrix = $this->vsmMatrix;
                                        $occupied = collect($matrix)->where('is_vacant', false)->count();
                                        $total = count($matrix);
                                    @endphp
                                    <div class="text-right shrink-0">
                                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $occupied }}/{{ $total }}</div>
                                        <div class="text-[10px] text-[var(--ui-muted)] uppercase tracking-wider">besetzt</div>
                                    </div>
                                </div>
                            </div>

                            @foreach($matrix as $code => $cell)
                                <div class="bg-white rounded-lg border {{ $cell['is_vacant'] ? 'border-amber-200' : 'border-[var(--ui-border)]' }} p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg bg-indigo-50 text-indigo-700 ring-1 ring-inset ring-indigo-600/10 shrink-0">
                                                @svg('heroicon-o-' . $cell['icon'], 'w-5 h-5')
                                            </span>
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $cell['label'] }}</div>
                                                <div class="text-xs text-[var(--ui-muted)] truncate">{{ $cell['description'] }}</div>
                                            </div>
                                        </div>
                                        <x-ui-button variant="ghost" size="sm" wire:click="openVsmAssignmentModal('{{ $code }}')">
                                            @svg('heroicon-o-plus', 'w-4 h-4')
                                            <span>Zuweisen</span>
                                        </x-ui-button>
                                    </div>

                                    @if($cell['is_vacant'])
                                        <div class="px-3 py-2 bg-amber-50 text-amber-800 text-xs rounded-md ring-1 ring-inset ring-amber-600/15">
                                            Vakant — diese Zelle ist unbesetzt.
                                        </div>
                                    @else
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($cell['assignments'] as $a)
                                                <div class="inline-flex items-center gap-2 pl-2.5 pr-1 py-1 rounded-full bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 {{ $a['is_active_today'] ? '' : 'opacity-50' }}">
                                                    @svg('heroicon-o-user-circle', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                                                    <a href="{{ route('organization.entities.show', $a['assigned_entity_id']) }}" class="text-xs font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline">{{ $a['assigned_name'] }}</a>
                                                    @if($a['scope'])
                                                        <span class="text-[10px] text-[var(--ui-muted)]" title="Scope">· {{ $a['scope'] }}</span>
                                                    @endif
                                                    @if(!$a['is_active_today'])
                                                        <span class="text-[10px] text-amber-700" title="Auserhalb des Gueltigkeits-Zeitraums">inaktiv</span>
                                                    @endif
                                                    <button
                                                        type="button"
                                                        wire:click="removeVsmAssignment({{ $a['id'] }})"
                                                        wire:confirm="Zuordnung {{ $a['assigned_name'] }} entfernen?"
                                                        class="ml-0.5 w-5 h-5 inline-flex items-center justify-center rounded-full text-[var(--ui-muted)] hover:text-red-600 hover:bg-red-50 transition-colors"
                                                        title="Entfernen"
                                                    >
                                                        @svg('heroicon-o-x-mark', 'w-3 h-3')
                                                    </button>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
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

    {{-- VSM-Assignment Modal --}}
    <x-ui-modal wire:model="vsmAssignmentModalShow" size="md">
        <x-slot name="header">
            @php
                $sysCode = $vsmAssignmentForm['vsm_system'] ?? '';
                $sysLabel = \Platform\Organization\Models\OrganizationEntityVsmAssignment::VSM_DEFINITIONS[$sysCode]['label'] ?? $sysCode;
            @endphp
            Zuordnung für {{ $sysLabel }}
        </x-slot>

        <form wire:submit.prevent="addVsmAssignment" class="space-y-4">
            <p class="text-xs text-[var(--ui-muted)]">
                Aus Sicht <span class="font-medium text-[var(--ui-secondary)]">{{ $entity->name }}</span> — wer füllt {{ $sysLabel }} aus?
            </p>

            <x-ui-input-select
                name="assigned_entity_id"
                label="Actor-Entity"
                :options="$this->vsmActorEntities"
                optionValue="id"
                optionLabel="name"
                :nullable="true"
                nullLabel="– Actor auswählen –"
                wire:model.live="vsmAssignmentForm.assigned_entity_id"
                required
            />

            <x-ui-input-text
                name="scope"
                label="Scope (optional)"
                wire:model.live="vsmAssignmentForm.scope"
                placeholder='z.B. "Cashflow", "Backend"'
            />

            <x-ui-input-textarea
                name="notes"
                label="Notiz (optional)"
                wire:model.live="vsmAssignmentForm.notes"
                placeholder="Warum diese Zuordnung?"
                rows="3"
            />
        </form>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button type="button" variant="secondary-outline" wire:click="closeVsmAssignmentModal">
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="addVsmAssignment">
                    @svg('heroicon-o-check', 'w-4 h-4 mr-2')
                    Zuweisen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>

    </x-ui-page-container>

    {{-- Alpine treeNode component for recursive tree rendering --}}
    @script
    <script>
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
    </script>
    @endscript
</x-ui-page>
