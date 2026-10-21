@php
    $fmtMin = fn ($min) => intdiv((int) $min, 60) . ':' . str_pad(abs((int) $min % 60), 2, '0', STR_PAD_LEFT) . ' h';

    $vsmClass = $entity->type->vsm_class;
    $vsmChip = match ($vsmClass) {
        'carrier' => ['Carrier', 'Lebensfähiges System (VSM) — kann eigene Perspektive sein'],
        'actor' => ['Actor', 'Füllt VSM-Funktionen aus, empfängt Signale'],
        'observed' => ['Observed', 'Umwelt — wird von S4 beobachtet'],
        default => null,
    };

    $childCount = $entity->children->count();
    $descendantCount = $this->totalDescendantCount;
    $linkCount = $this->totalLinkCount;
    $cascaded = $this->cascadedTimeSummary;
    $openMinutes = $cascaded['total_minutes'] - $cascaded['billed_minutes'];
    $analysis = $this->snapshotAnalysis;
    $contextSummary = $this->contextSummary;

    $healthMeta = [
        'progressing' => ['Fortschreitend', 'var(--nx-success)'],
        'stalled' => ['Stagnierend', 'var(--nx-warning)'],
        'at_risk' => ['Gefährdet', 'var(--nx-danger)'],
        'completed' => ['Abgeschlossen', 'var(--nx-info)'],
    ];

    // Tabs: Inhalt zuerst, Konfiguration („Einstellungen") zuletzt.
    $tabs = [];
    $tabs[] = ['key' => 'hierarchy', 'label' => 'Struktur', 'icon' => 'heroicon-o-rectangle-group', 'badge' => $descendantCount ?: null];
    $tabs[] = ['key' => 'links', 'label' => 'Verknüpft', 'icon' => 'heroicon-o-link', 'badge' => $linkCount ?: null];
    $tabs[] = ['key' => 'analyse', 'label' => 'Analyse', 'icon' => 'heroicon-o-chart-bar-square', 'click' => '$wire.loadAnalyseData()'];
    $tabs[] = ['key' => 'relations', 'label' => 'Beziehungen', 'icon' => 'heroicon-o-arrows-right-left'];
    $tabs[] = ['key' => 'signals', 'label' => 'Signale', 'icon' => 'heroicon-o-bell-alert', 'badge' => $this->openSignalCount ?: null, 'badgeVariant' => 'danger'];
    if ($this->hasLinkedUser) {
        $tabs[] = ['key' => 'person', 'label' => 'Person', 'icon' => 'heroicon-o-user'];
    }
    if ($this->isCarrierEntity) {
        $vacancyCount = collect($this->vsmMatrix)->where('is_vacant', true)->count();
        $tabs[] = ['key' => 'strategy', 'label' => 'Strategie', 'icon' => 'heroicon-o-flag'];
        $tabs[] = ['key' => 'vsm', 'label' => 'VSM', 'icon' => 'heroicon-o-squares-2x2', 'badge' => $vacancyCount ?: null, 'badgeVariant' => 'warning', 'title' => $vacancyCount ? $vacancyCount . ' unbesetzte Zellen' : null];
    }
    if ($this->isAgentEntity) {
        $tabs[] = ['key' => 'agent', 'label' => 'Agent', 'icon' => 'heroicon-o-sparkles'];
        $tabs[] = ['key' => 'braingraph', 'label' => 'Gehirn', 'icon' => 'heroicon-o-cpu-chip'];
    } elseif ($this->isSystemAgent) {
        $unhealthyCount = $this->agentPrompts->filter(fn ($p) => in_array($p->health_status, ['stale', 'error']))->count();
        $tabs[] = ['key' => 'agent', 'label' => 'Agent', 'icon' => 'heroicon-o-cpu-chip', 'badge' => $unhealthyCount ?: null, 'badgeVariant' => 'danger'];
    }
    $tabs[] = ['key' => 'reports', 'label' => 'Berichte', 'icon' => 'heroicon-o-document-text'];
    $tabs[] = ['key' => 'activity', 'label' => 'Aktivität', 'icon' => 'heroicon-o-clock'];
    $tabs[] = ['key' => 'data', 'label' => 'Einstellungen', 'icon' => 'heroicon-o-cog-6-tooth'];

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

    $crumbs = [['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
               ['label' => 'Einheiten', 'href' => route('organization.entities.index')]];
    if ($entity->parent) {
        $crumbs[] = ['label' => $entity->parent->name, 'href' => route('organization.entities.show', $entity->parent)];
    }
    $crumbs[] = ['label' => $entity->name];

    $field = 'block w-full rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-2.5 py-1.5 text-sm text-[color:var(--nx-text)] focus:border-[color:var(--nx-accent)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]';
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$entity->name" icon="heroicon-o-building-office" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="$crumbs">
            <div class="flex items-center gap-2">
                @if ($this->isDirty())
                    <x-nx-button variant="primary" size="sm" wire:click="save">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Speichern</span>
                    </x-nx-button>
                @endif
                <x-nx-dropdown>
                    @if ($this->isDirty())
                        <x-nx-dropdown-item wire:click="loadForm">
                            @svg('heroicon-o-arrow-uturn-left', 'w-4 h-4')
                            Änderungen verwerfen
                        </x-nx-dropdown-item>
                        <x-nx-dropdown-divider />
                    @endif
                    <x-nx-dropdown-item :href="route('organization.entities.mindmap', $entity)">
                        @svg('heroicon-o-globe-alt', 'w-4 h-4')
                        Mindmap
                    </x-nx-dropdown-item>
                    <x-nx-dropdown-item :href="route('organization.entities.board', $entity)">
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4')
                        VSM-Board
                    </x-nx-dropdown-item>
                    <x-nx-dropdown-divider />
                    <x-nx-dropdown-item wire:click="openCreateTeamModal">
                        @svg('heroicon-o-user-group', 'w-4 h-4')
                        Plattform-Team daraus erstellen
                    </x-nx-dropdown-item>
                </x-nx-dropdown>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            @if (session()->has('message'))
                <x-nx-callout variant="success">{{ session('message') }}</x-nx-callout>
            @endif
            @if (session()->has('error'))
                <x-nx-callout variant="danger">{{ session('error') }}</x-nx-callout>
            @endif

            {{-- Kopf: Identität, Einordnung, Kennzahlen --}}
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $entity->name }}</h1>
                    <x-nx-badge>{{ $entity->type->name }}</x-nx-badge>
                    @if ($vsmChip)
                        <span title="{{ $vsmChip[1] }}"><x-nx-badge :variant="$vsmClass === 'carrier' ? 'success' : 'neutral'">{{ $vsmChip[0] }}</x-nx-badge></span>
                    @endif
                    @unless ($entity->is_active)
                        <x-nx-badge variant="danger" dot>Inaktiv</x-nx-badge>
                    @endunless
                    @if ($entity->code)
                        <span class="font-mono text-xs text-[color:var(--nx-faint)]">{{ $entity->code }}</span>
                    @endif
                </div>

                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[color:var(--nx-muted)]">
                    @if ($entity->parent)
                        <span>in <a href="{{ route('organization.entities.show', $entity->parent) }}" wire:navigate class="font-medium text-[color:var(--nx-text)] hover:underline">{{ $entity->parent->name }}</a></span>
                    @endif
                    @if ($entity->linkedUser)
                        <span>verantwortlich: <span class="font-medium text-[color:var(--nx-text)]">{{ $entity->linkedUser->name }}</span></span>
                    @endif
                    <span title="angelegt {{ $entity->created_at->format('d.m.Y H:i') }}">aktualisiert {{ $entity->updated_at->diffForHumans() }}</span>
                </div>

                @if ($entity->description)
                    <p class="mb-0 mt-2 max-w-3xl text-sm text-[color:var(--nx-muted)] line-clamp-3">{{ $entity->description }}</p>
                @endif

                <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    <button type="button" x-on:click="$wire.set('activeTab', 'hierarchy')" class="inline-flex items-baseline gap-1.5 hover:underline">
                        <span class="font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $childCount }}</span>
                        <span class="text-xs text-[color:var(--nx-muted)]">Untereinheiten{{ $descendantCount > $childCount ? ' (' . $descendantCount . ' gesamt)' : '' }}</span>
                    </button>
                    <button type="button" x-on:click="$wire.set('activeTab', 'links')" class="inline-flex items-baseline gap-1.5 hover:underline">
                        <span class="font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $linkCount }}</span>
                        <span class="text-xs text-[color:var(--nx-muted)]">Verknüpfungen</span>
                    </button>
                    <span class="inline-flex items-baseline gap-1.5">
                        <span class="font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $fmtMin($cascaded['total_minutes']) }}</span>
                        <span class="text-xs text-[color:var(--nx-muted)]">erfasst</span>
                    </span>
                    @if ($openMinutes > 0)
                        <span class="inline-flex items-baseline gap-1.5">
                            <span class="font-semibold tabular-nums text-[color:var(--nx-warning)]">{{ $fmtMin($openMinutes) }}</span>
                            <span class="text-xs text-[color:var(--nx-muted)]">nicht abgerechnet</span>
                        </span>
                    @endif
                </div>

                @if (! empty($analysis))
                    @php
                        [$hLabel, $hColor] = $healthMeta[$analysis['health_status']] ?? $healthMeta['progressing'];
                        $pct = (int) $analysis['completion_rate'];
                    @endphp
                    <div class="mt-3 flex max-w-3xl items-center gap-3 text-xs">
                        <span class="w-24 shrink-0 font-medium" style="color: {{ $hColor }}">{{ $hLabel }}</span>
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-[color:var(--nx-accent-soft)]">
                            <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $hColor }}"></div>
                        </div>
                        <span class="shrink-0 tabular-nums text-[color:var(--nx-muted)]">
                            {{ $pct }} % · {{ $analysis['items_done'] }}/{{ $analysis['items_total'] }}
                            @if ($analysis['velocity_daily_avg'] > 0) · {{ $analysis['velocity_daily_avg'] }}/Tag @endif
                            @if ($analysis['estimated_days_remaining'] !== null) · noch ~{{ $analysis['estimated_days_remaining'] }} Tage @endif
                        </span>
                    </div>
                @endif

                @if (! empty($contextSummary))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($contextSummary as $ctx)
                            <button type="button" x-on:click="$wire.set('activeTab', 'links')"
                                class="inline-flex items-center gap-1 rounded-full bg-[color:var(--nx-accent-soft)] px-2 py-0.5 text-xs text-[color:var(--nx-muted)] transition-colors hover:text-[color:var(--nx-text)]">
                                {{ $ctx['label'] }} <span class="font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $ctx['count'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Tabs — nur der aktive Tab wird serverseitig gerendert (@if je Tab), Wechsel per entangle.live --}}
            <div x-data="{ tab: @entangle('activeTab').live }">
                <div class="mb-5 border-b border-[color:var(--nx-line)]">
                    <nav class="-mb-px flex gap-1 overflow-x-auto">
                        @foreach ($tabs as $t)
                            <button type="button"
                                x-on:click="tab = '{{ $t['key'] }}'{{ isset($t['click']) ? '; ' . $t['click'] : '' }}"
                                :class="tab === '{{ $t['key'] }}' ? 'border-[color:var(--nx-text)] text-[color:var(--nx-text)] font-medium' : 'border-transparent text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]'"
                                class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-sm transition-colors"
                                @if (! empty($t['title'])) title="{{ $t['title'] }}" @endif>
                                @svg($t['icon'], 'w-4 h-4')
                                {{ $t['label'] }}
                                @if (! empty($t['badge']))
                                    <x-nx-badge :variant="$t['badgeVariant'] ?? 'neutral'">{{ $t['badge'] }}</x-nx-badge>
                                @endif
                            </button>
                        @endforeach
                    </nav>
                    <div wire:loading.delay wire:target="activeTab" class="py-2 text-xs text-[color:var(--nx-faint)]">Lädt…</div>
                </div>

                {{-- ═══ Struktur: Untereinheiten ═══ --}}
                @if ($activeTab === 'hierarchy')
                    <div x-data="{
                        linkConfig: {{ Js::from(collect($this->linkTypeConfig)->map(fn($c) => ['label' => $c['label'], 'icon' => $c['icon']])) }},
                        linkIconSvgs: {{ Js::from($this->linkTypeIconSvgs) }},
                        displayRules: {{ Js::from($this->displayRules) }}
                    }">
                        <x-nx-panel flush title="Untereinheiten" :subtitle="count($this->treeNodes) > 0 ? $descendantCount . ' im Teilbaum, inkl. Zeiten und Verknüpfungen' : null">
                            @if (count($this->treeNodes) > 0)
                                <x-slot name="actions">
                                    <button type="button" x-data
                                        class="inline-flex items-center gap-1.5 rounded-[6px] px-2 py-1 text-xs font-medium text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]"
                                        x-on:click="$store.tree.allExpanded ? $store.tree.collapseAll() : $store.tree.expandAll($wire)"
                                        :disabled="$store.tree.loading">
                                        <span x-show="$store.tree.loading">Lädt…</span>
                                        <span x-show="!$store.tree.loading" x-text="$store.tree.allExpanded ? 'Einklappen' : 'Alle aufklappen'"></span>
                                    </button>
                                </x-slot>
                                <div class="space-y-4 p-3">
                                    @foreach ($this->treeNodes as $section)
                                        <div>
                                            <div class="mb-1 flex items-center gap-2 px-2 text-xs font-medium text-[color:var(--nx-faint)]">
                                                {{ $section['group_name'] }} <span>{{ count($section['nodes']) }}</span>
                                            </div>
                                            <div class="space-y-0.5">
                                                @foreach ($section['nodes'] as $node)
                                                    @include('organization::livewire.entity.partials.tree-node', ['node' => $node, 'depth' => 0])
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <x-nx-empty icon="heroicon-o-rectangle-group">Keine Untereinheiten.</x-nx-empty>
                            @endif
                        </x-nx-panel>
                    </div>
                @endif

                {{-- ═══ Verknüpft: Objekte, die an dieser Einheit hängen ═══ --}}
                @if ($activeTab === 'links')
                    <div x-data="{
                        linkConfig: {{ Js::from(collect($this->linkTypeConfig)->map(fn($c) => ['label' => $c['label'], 'icon' => $c['icon']])) }},
                        linkIconSvgs: {{ Js::from($this->linkTypeIconSvgs) }},
                        displayRules: {{ Js::from($this->displayRules) }}
                    }">
                        @php
                            $rootLinks = $this->rootEntityLinks;
                            $totalLinkItems = collect($rootLinks)->sum(fn ($g) => count($g['items']));
                        @endphp
                        <x-nx-panel flush title="Verknüpft" :subtitle="count($rootLinks) > 0 ? $totalLinkItems . ' Objekte direkt an ' . $entity->name . ($cascaded['total_minutes'] > 0 ? ' · ' . $fmtMin($cascaded['total_minutes']) . ' im Teilbaum erfasst' : '') : null">
                            @if (count($rootLinks) > 0)
                                <x-slot name="actions">
                                    <button type="button" x-data x-on:click="$store.tree.showDone = !$store.tree.showDone"
                                        class="rounded-[6px] px-2 py-1 text-xs font-medium hover:bg-[color:var(--nx-hover)]"
                                        :class="$store.tree.showDone ? 'text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)]'"
                                        x-text="$store.tree.showDone ? 'Erledigte ausblenden' : 'Erledigte anzeigen'"></button>
                                </x-slot>
                                <div class="p-2">
                                    @foreach ($rootLinks as $group)
                                        <div x-data="{ groupOpen: false, init() { this.$watch('$store.tree.allExpanded', v => this.groupOpen = v); } }">
                                            <div class="flex cursor-pointer items-center gap-2 rounded-[6px] px-2 py-2 hover:bg-[color:var(--nx-hover)]" x-on:click="groupOpen = !groupOpen">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                    class="h-4 w-4 shrink-0 text-[color:var(--nx-faint)] transition-transform duration-200" :class="{ 'rotate-90': groupOpen }">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                </svg>
                                                @svg('heroicon-o-' . (app('safe-svg')->resolve($group['icon'] ?? null, 'heroicon-o-') ?? 'cube'), 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $group['label'] }}</span>
                                                <x-nx-badge>{{ count($group['items']) }}</x-nx-badge>
                                                @if (($group['group_logged_minutes'] ?? 0) > 0)
                                                    <span class="ml-auto shrink-0 text-xs tabular-nums text-[color:var(--nx-muted)]">{{ $fmtMin($group['group_logged_minutes']) }}</span>
                                                @endif
                                            </div>
                                            <div x-show="groupOpen" x-collapse x-cloak class="ml-5 border-l border-[color:var(--nx-line)]">
                                                @foreach ($group['items'] as $link)
                                                    @include('organization::livewire.entity.partials.link-item', ['link' => $link, 'group' => $group])
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <x-nx-empty icon="heroicon-o-link">Noch nichts verknüpft. Projekte, Tickets usw. hängen Module oder Agenten per <code class="text-xs">organization.entity.link.POST</code> an.</x-nx-empty>
                            @endif
                        </x-nx-panel>
                    </div>
                @endif

                {{-- ═══ Analyse ═══ --}}
                @if ($activeTab === 'analyse')
                    @if ($analyseLoaded)
                        <div class="space-y-5">
                            @php $radar = $this->dimensionRadar; @endphp
                            @if (! empty($radar))
                                <x-nx-panel title="7½ Dimensionen">
                                    @include('organization::livewire.entity.partials.dimension-radar', ['radar' => $radar])
                                </x-nx-panel>
                            @endif

                            @php $movement = $this->movement; @endphp
                            @if (! empty($movement['metrics']))
                                @php
                                    $nonZero = collect($movement['metrics'])->filter(fn ($m) => $m['delta'] != 0)->take(5);
                                    $totalMetrics = collect($movement['metrics'])->filter(fn ($m) => $m['current'] > 0 || $m['previous'] > 0)->count();
                                    $dimensionLabels = \Platform\Organization\Services\EntityLinkRegistry::allDimensions();
                                    $pill = fn ($on) => 'rounded-[6px] px-2 py-0.5 text-xs ' . ($on ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]');
                                    $sentColor = fn ($s) => $s === 'positive' ? 'text-[color:var(--nx-success)]' : ($s === 'negative' ? 'text-[color:var(--nx-danger)]' : 'text-[color:var(--nx-muted)]');
                                @endphp
                                <x-nx-panel title="Bewegung" subtitle="letzte 7 Tage" x-data="{ movementGrouping: 'dimension' }">
                                    <x-slot name="actions">
                                        <div class="flex gap-1">
                                            <button type="button" x-on:click="movementGrouping = 'dimension'" class="rounded-[6px] px-2 py-0.5 text-xs" :class="movementGrouping === 'dimension' ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]'">nach Dimension</button>
                                            <button type="button" x-on:click="movementGrouping = 'module'" class="rounded-[6px] px-2 py-0.5 text-xs" :class="movementGrouping === 'module' ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]'">nach Modul</button>
                                        </div>
                                    </x-slot>

                                    <div class="mb-3 flex flex-wrap gap-1">
                                        <button type="button" wire:click="$set('movementStream', null)" class="{{ $pill(! $movementStream) }}">Alle</button>
                                        @foreach ($this->availableStreams as $stream)
                                            <button type="button" wire:click="$set('movementStream', '{{ $stream }}')" class="{{ $pill($movementStream === $stream) }}">{{ ucfirst($stream) }}</button>
                                        @endforeach
                                    </div>

                                    @if ($nonZero->isNotEmpty())
                                        <div class="mb-4 flex flex-wrap gap-1.5">
                                            @foreach ($nonZero as $m)
                                                <x-nx-badge :variant="$m['sentiment'] === 'positive' ? 'success' : ($m['sentiment'] === 'negative' ? 'danger' : 'neutral')">{{ $m['delta_formatted'] }} {{ $m['label'] }}</x-nx-badge>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($movementStream || $totalMetrics > 5)
                                        @foreach (['dimension' => $movement['metrics_by_dimension'], 'module' => $movement['metrics_by_group']] as $grouping => $groups)
                                            <div x-show="movementGrouping === '{{ $grouping }}'" @if ($grouping === 'module') x-cloak @endif>
                                                @foreach ($groups as $gKey => $metrics)
                                                    <div class="mb-3">
                                                        <div class="mb-1.5 text-xs font-medium text-[color:var(--nx-faint)]">{{ $grouping === 'dimension' ? ($dimensionLabels[$gKey]['label'] ?? ucfirst($gKey)) : ucfirst($gKey) }}</div>
                                                        <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
                                                            @foreach ($metrics as $m)
                                                                @if ($m['current'] != 0 || $m['previous'] != 0)
                                                                    <div class="rounded-[6px] border border-[color:var(--nx-line)] px-2.5 py-2">
                                                                        <div class="text-sm font-semibold tabular-nums text-[color:var(--nx-text)]">
                                                                            {{ $m['current_formatted'] }}
                                                                            @if ($m['delta'] != 0)<span class="ml-1 text-[11px] font-medium {{ $sentColor($m['sentiment']) }}">{{ $m['delta_formatted'] }}</span>@endif
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
                                        @endforeach
                                    @endif
                                </x-nx-panel>
                            @endif

                            @php
                                $trend = $this->snapshotTrend;
                                $monthlyData = $this->monthlyTimeData;
                                $chartMonths = $monthlyData['months'] ?? [];
                                $monthlyMax = $monthlyData['max_minutes'] ?? 0;
                                $hasTrend = ! empty($trend) && count($trend['snapshots'] ?? []) >= 1;
                                $hasMonthly = $monthlyMax > 0;
                            @endphp
                            @if ($hasTrend || $hasMonthly)
                                <x-nx-panel title="Verlauf" x-data="{ trendTab: '{{ $hasTrend ? '14d' : '12m' }}' }">
                                    <x-slot name="actions">
                                        <div class="flex gap-1">
                                            @if ($hasTrend)
                                                <button type="button" x-on:click="trendTab = '14d'" class="rounded-[6px] px-2 py-0.5 text-xs" :class="trendTab === '14d' ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]'">14 Tage</button>
                                            @endif
                                            @if ($hasMonthly)
                                                <button type="button" x-on:click="trendTab = '12m'" class="rounded-[6px] px-2 py-0.5 text-xs" :class="trendTab === '12m' ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]'">12 Monate</button>
                                            @endif
                                        </div>
                                    </x-slot>

                                    @if ($hasTrend)
                                        <div x-show="trendTab === '14d'" x-cloak>
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
                                                    <div class="relative flex h-full flex-1 flex-col items-center justify-end gap-px" x-on:mouseenter="tip = {{ $idx }}" x-on:mouseleave="tip = null">
                                                        <div x-show="tip === {{ $idx }}" x-cloak class="pointer-events-none absolute bottom-full z-10 mb-2 whitespace-nowrap rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-2 py-1 text-[11px] text-[color:var(--nx-text)] shadow-sm">
                                                            {{ $snap['date'] }} {{ $snap['period'] }}: {{ $snap['items_done'] }}/{{ $snap['items_total'] }} · {{ $fmtMin($snap['time_total_minutes']) }}
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
                                                            @if ($idx === 0 || $idx === $lastIdx || $idx % 4 === 0){{ $snap['date'] }}@endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if ($hasMonthly)
                                        <div x-show="trendTab === '12m'" x-cloak x-data="{ tip: null }">
                                            <div class="mb-3 flex items-center justify-end gap-3 text-[11px] text-[color:var(--nx-muted)]">
                                                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[color:var(--nx-success)]"></span>abgerechnet</span>
                                                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-[color:var(--nx-warning)]"></span>offen</span>
                                            </div>
                                            <div class="flex items-end gap-1.5" style="height: 136px;">
                                                @foreach ($chartMonths as $idx => $m)
                                                    @php
                                                        $barHeight = $m['total_minutes'] > 0 ? round(($m['total_minutes'] / $monthlyMax) * 120) : 0;
                                                        $billedHeight = $m['billed_minutes'] > 0 ? max(2, round(($m['billed_minutes'] / $monthlyMax) * 120)) : 0;
                                                        $openHeight = $m['open_minutes'] > 0 ? max(2, $barHeight - $billedHeight) : 0;
                                                        if ($billedHeight + $openHeight > $barHeight && $barHeight > 4) {
                                                            $billedHeight = $barHeight - $openHeight;
                                                        }
                                                    @endphp
                                                    <div class="relative flex h-full flex-1 flex-col items-center" x-on:mouseenter="tip = {{ $idx }}" x-on:mouseleave="tip = null">
                                                        <div x-show="tip === {{ $idx }}" x-cloak class="pointer-events-none absolute bottom-full z-10 mb-2 whitespace-nowrap rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-2 py-1 text-[11px] text-[color:var(--nx-text)] shadow-sm">
                                                            {{ $m['label'] }} {{ $m['year'] }}: {{ $fmtMin($m['total_minutes']) }} ({{ $fmtMin($m['billed_minutes']) }} abgerechnet, {{ $fmtMin($m['open_minutes']) }} offen)
                                                        </div>
                                                        <div class="flex w-full flex-1 flex-col justify-end overflow-hidden rounded-t-[3px]">
                                                            @if ($m['total_minutes'] > 0)
                                                                @if ($m['open_minutes'] > 0)<div class="w-full bg-[color:var(--nx-warning)] opacity-80" style="height: {{ $openHeight }}px;"></div>@endif
                                                                @if ($m['billed_minutes'] > 0)<div class="w-full bg-[color:var(--nx-success)] opacity-80" style="height: {{ $billedHeight }}px;"></div>@endif
                                                            @else
                                                                <div class="h-px w-full bg-[color:var(--nx-line)]"></div>
                                                            @endif
                                                        </div>
                                                        <div class="mt-1 text-[10px] leading-none text-[color:var(--nx-faint)]">{{ $m['label'] }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </x-nx-panel>
                            @endif

                            @if (! empty($analysis['insights'] ?? []))
                                <x-nx-panel title="Erkenntnisse">
                                    <ul class="m-0 list-none space-y-1.5 p-0 text-sm">
                                        @foreach ($analysis['insights'] as $insight)
                                            <li @class([
                                                'text-[color:var(--nx-success)]' => $insight['type'] === 'success',
                                                'text-[color:var(--nx-warning)]' => $insight['type'] === 'warning',
                                                'text-[color:var(--nx-text)]' => ! in_array($insight['type'], ['success', 'warning']),
                                            ])>{{ $insight['text'] }}</li>
                                        @endforeach
                                    </ul>
                                </x-nx-panel>
                            @endif

                            @if (empty($radar) && empty($movement['metrics']) && ! $hasTrend && ! $hasMonthly)
                                <x-nx-card flush><x-nx-empty icon="heroicon-o-chart-bar-square">Noch keine Kennzahlen — die Snapshots laufen nachts.</x-nx-empty></x-nx-card>
                            @endif
                        </div>
                    @else
                        <div class="py-8 text-center text-sm text-[color:var(--nx-muted)]">Analyse wird geladen…</div>
                    @endif
                @endif

                {{-- ═══ Beziehungen ═══ --}}
                @if ($activeTab === 'relations')
                    <div class="space-y-5">
                        <x-nx-panel flush title="Ausgehend" :subtitle="$this->relationsFrom->count() . ' · von ' . $entity->name . ' zu anderen Einheiten'">
                            <x-slot name="actions">
                                <x-nx-button size="sm" wire:click="$toggle('relationFormShow')">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    <span>Beziehung</span>
                                </x-nx-button>
                            </x-slot>

                            @if ($relationFormShow)
                                <div class="space-y-3 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-4">
                                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <x-nx-input-select name="relation_to_entity_id" label="Ziel-Einheit" size="sm"
                                            :options="$this->availableRelationEntities->map(fn($e) => ['value' => (string) $e->id, 'label' => $e->name . ' (' . ($e->type->name ?? '') . ')'])->toArray()"
                                            nullable nullLabel="– auswählen –" wire:model.live="relationForm.to_entity_id" errorKey="relationForm.to_entity_id" />
                                        <x-nx-input-select name="relation_type_id" label="Art" size="sm" hint="z. B. liefert an, beauftragt"
                                            :options="$this->availableRelationTypes->map(fn($t) => ['value' => (string) $t->id, 'label' => $t->name])->toArray()"
                                            nullable nullLabel="– auswählen –" wire:model.live="relationForm.relation_type_id" errorKey="relationForm.relation_type_id" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <x-nx-input-date name="relation_valid_from" label="Gültig von" size="sm" wire:model.live="relationForm.valid_from" />
                                        <x-nx-input-date name="relation_valid_to" label="Gültig bis" size="sm" wire:model.live="relationForm.valid_to" />
                                    </div>
                                    <div class="flex gap-2">
                                        <x-nx-button variant="primary" size="sm" wire:click="createRelation">Anlegen</x-nx-button>
                                        <x-nx-button variant="ghost" size="sm" wire:click="$set('relationFormShow', false)">Abbrechen</x-nx-button>
                                    </div>
                                </div>
                            @endif

                            <div class="space-y-2 p-3">
                                @forelse ($this->relationsFrom as $relation)
                                    @include('organization::livewire.entity.partials.relation-card-inline', [
                                        'relation' => $relation,
                                        'direction' => 'from',
                                        'thisEntity' => $entity,
                                        'otherEntity' => $relation->toEntity,
                                    ])
                                @empty
                                    <p class="m-0 px-1 py-2 text-sm text-[color:var(--nx-faint)]">Keine ausgehenden Beziehungen.</p>
                                @endforelse
                            </div>
                        </x-nx-panel>

                        <x-nx-panel flush title="Eingehend" :subtitle="$this->relationsTo->count() . ' · von anderen Einheiten zu ' . $entity->name">
                            <div class="space-y-2 p-3">
                                @forelse ($this->relationsTo as $relation)
                                    @include('organization::livewire.entity.partials.relation-card-inline', [
                                        'relation' => $relation,
                                        'direction' => 'to',
                                        'thisEntity' => $entity,
                                        'otherEntity' => $relation->fromEntity,
                                    ])
                                @empty
                                    <p class="m-0 px-1 py-2 text-sm text-[color:var(--nx-faint)]">Keine eingehenden Beziehungen.</p>
                                @endforelse
                            </div>
                        </x-nx-panel>

                        <p class="m-0 text-xs text-[color:var(--nx-faint)]">
                            Beziehungen beschreiben, wie Einheiten zusammenhängen. An jede Beziehung lassen sich Schnittstellen hängen — Verträge, Ticketsysteme, Datenflüsse.
                        </p>
                    </div>
                @endif

                {{-- ═══ Signale ═══ --}}
                @if ($activeTab === 'signals')
                    <x-nx-panel flush title="Signale">
                        <x-slot name="actions">
                            <div class="w-40">
                                <x-nx-input-select name="signalStatusFilter" size="sm" wire:model.live="signalStatusFilter" nullable nullLabel="Alle"
                                    :options="[['value' => 'open', 'label' => 'Offen'], ['value' => 'acknowledged', 'label' => 'Bestätigt'], ['value' => 'resolved', 'label' => 'Gelöst'], ['value' => 'dismissed', 'label' => 'Verworfen']]" />
                            </div>
                        </x-slot>

                        @if ($this->entitySignals->isEmpty())
                            <x-nx-empty icon="heroicon-o-bell-slash">Keine Signale{{ $signalStatusFilter ? ' mit diesem Status' : '' }}.</x-nx-empty>
                        @else
                            @foreach ($this->entitySignals as $signal)
                                @php
                                    [$stV, $stL] = $statusBadge($signal->status);
                                    $isOverdue = $signal->deadline_at && $signal->status === 'open' && $signal->deadline_at->isPast();
                                    $pattern = match ($signal->definition?->pattern_type) {
                                        'threshold' => 'Schwellenwert',
                                        'trend' => 'Trend',
                                        'cross_dimension' => 'Kreuz-Dimension',
                                        'ratio' => 'Verhältnis',
                                        default => $signal->definition?->pattern_type,
                                    };
                                @endphp
                                <div wire:key="signal-{{ $signal->id }}" class="flex items-start gap-4 border-b border-[color:var(--nx-line)] px-4 py-3 last:border-0">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <x-nx-badge :variant="$sevVariant($signal->severity)">{{ $sevLabel($signal->severity) }}</x-nx-badge>
                                            <a href="{{ route('organization.signals.show', $signal) }}" wire:navigate class="text-sm font-medium text-[color:var(--nx-text)] hover:underline">{{ $signal->definition?->name ?? 'Signal' }}</a>
                                            @if ($pattern)<span class="text-xs text-[color:var(--nx-faint)]">{{ $pattern }}</span>@endif
                                        </div>
                                        <p class="mb-0 mt-1 text-sm text-[color:var(--nx-muted)]">{{ $signal->message }}</p>

                                        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[color:var(--nx-faint)]">
                                            @if ($signal->vsm_level)<span title="VSM-Ebene">{{ strtoupper(str_replace('_', '', $signal->vsm_level)) }}</span>@endif
                                            @if ($signal->current_owner_entity_id)
                                                <span title="Owner">{{ $signal->currentOwner?->name ?? '#' . $signal->current_owner_entity_id }}</span>
                                            @elseif ($signal->status === 'open')
                                                <span class="text-[color:var(--nx-warning)]">ohne Owner</span>
                                            @endif
                                            @if ($signal->deadline_at)
                                                <span class="{{ $isOverdue ? 'text-[color:var(--nx-danger)]' : '' }}" title="{{ $signal->deadline_at->format('d.m.Y H:i') }}">{{ $isOverdue ? 'überfällig' : 'fällig' }} {{ $signal->deadline_at->diffForHumans() }}</span>
                                            @endif
                                            @if ($signal->escalated_at)<span class="text-[color:var(--nx-warning)]">eskaliert</span>@endif
                                            @if ($signal->source_type === 'aggregation')<span>aggregiert</span>@endif
                                            @if ($signal->createdByAgent)<span>von {{ $signal->createdByAgent->name }}</span>@endif
                                            @if ($signal->trigger_metrics)
                                                <span x-data="{ open: false }">
                                                    <button type="button" x-on:click="open = !open" class="hover:text-[color:var(--nx-text)] hover:underline" x-text="open ? 'Werte ausblenden' : 'Auslösende Werte'"></button>
                                                    <div x-show="open" x-cloak class="mt-1.5 rounded-[6px] bg-[color:var(--nx-bg)] p-2 font-mono text-[11px] text-[color:var(--nx-muted)]">
                                                        @foreach ($signal->trigger_metrics as $key => $value)
                                                            <div>{{ $key }}: {{ is_array($value) ? json_encode($value) : $value }}</div>
                                                        @endforeach
                                                    </div>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                                        <x-nx-badge :variant="$stV" dot>{{ $stL }}</x-nx-badge>
                                        <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $signal->created_at->format('d.m.Y') }}</span>
                                        @if ($signal->status === 'resolved' && $signal->resolvedByUser)
                                            <span class="text-xs text-[color:var(--nx-faint)]">von {{ $signal->resolvedByUser->name }}</span>
                                        @endif
                                        @if (in_array($signal->status, ['open', 'acknowledged']))
                                            <div class="mt-0.5 flex gap-1">
                                                @if ($signal->status === 'open')
                                                    <x-nx-button size="sm" wire:click="acknowledgeSignal({{ $signal->id }})">Bestätigen</x-nx-button>
                                                @else
                                                    <x-nx-button size="sm" wire:click="resolveSignal({{ $signal->id }})">Lösen</x-nx-button>
                                                @endif
                                                <x-nx-button size="sm" variant="ghost" wire:click="dismissSignal({{ $signal->id }})">Verwerfen</x-nx-button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </x-nx-panel>
                @endif

                {{-- ═══ Person ═══ --}}
                @if ($this->hasLinkedUser && $activeTab === 'person')
                    <livewire:organization.entity.person-activity :entity="$entity" :key="'person-activity-'.$entity->id" />
                @endif

                {{-- ═══ Agent (KI-Worker) ═══ --}}
                @if ($this->isAgentEntity)
                    @if ($activeTab === 'agent')
                        @livewire(\Platform\Organization\Livewire\Agent\ProfilePanel::class, ['entity' => $entity], key('agent-panel-'.$entity->id))
                    @endif
                    @if ($activeTab === 'braingraph')
                        @livewire(\Platform\Organization\Livewire\Agent\BrainGraph::class, ['entity' => $entity], key('agent-braingraph-'.$entity->id))
                    @endif
                @endif

                {{-- ═══ System-Agent ═══ --}}
                @if ($this->isSystemAgent && ! $this->isAgentEntity && $activeTab === 'agent')
                    <div class="space-y-5">
                        <x-nx-callout :variant="$entity->is_active ? 'success' : 'danger'" :title="$entity->is_active ? 'Agent aktiv' : 'Agent inaktiv'">
                            {{ $entity->is_active ? 'Die Inference-Prompts laufen nach ihrem Zeitplan.' : 'Der Worker überspringt die Prompts, auch wenn sie selbst aktiv sind.' }}
                            <x-slot name="action">
                                <x-nx-button size="sm" :variant="$entity->is_active ? 'danger' : 'primary'" wire:click="toggleEntityActive"
                                    wire:confirm="{{ $entity->is_active ? 'Agent wirklich deaktivieren? Seine Prompts laufen dann nicht mehr.' : 'Agent wieder aktivieren?' }}">
                                    {{ $entity->is_active ? 'Deaktivieren' : 'Aktivieren' }}
                                </x-nx-button>
                            </x-slot>
                        </x-nx-callout>

                        <x-nx-panel flush title="Inference-Prompts" :subtitle="$this->agentPrompts->count() . ' zugewiesen'">
                            @forelse ($this->agentPrompts as $prompt)
                                @php
                                    $hv = match ($prompt->health_status) { 'healthy' => 'success', 'stale' => 'warning', 'error' => 'danger', default => 'neutral' };
                                @endphp
                                <div class="border-b border-[color:var(--nx-line)] px-4 py-3 last:border-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-nx-badge>{{ strtoupper(str_replace('_star', '*', $prompt->vsm_system)) }}</x-nx-badge>
                                        <a href="{{ route('organization.settings.inference-prompts.show', $prompt) }}" wire:navigate class="truncate text-sm font-medium text-[color:var(--nx-text)] hover:underline">{{ $prompt->name }}</a>
                                        <x-nx-badge :variant="$hv" dot>{{ $prompt->health_status }}</x-nx-badge>
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-x-3 text-xs text-[color:var(--nx-faint)]">
                                        <span>alle {{ $prompt->schedule_interval_hours ?? 72 }} h</span>
                                        @if ($prompt->last_evaluated_at)
                                            <span title="{{ $prompt->last_evaluated_at->format('d.m.Y H:i:s') }}">zuletzt {{ $prompt->last_evaluated_at->diffForHumans() }}</span>
                                        @else
                                            <span class="text-[color:var(--nx-warning)]">noch nie gelaufen</span>
                                        @endif
                                        <span>{{ $prompt->run_count ?? 0 }} Läufe</span>
                                        <span>Schwere {{ $prompt->default_severity }}</span>
                                    </div>
                                    @if ($prompt->description)
                                        <p class="mb-0 mt-1.5 line-clamp-2 text-xs text-[color:var(--nx-muted)]">{{ $prompt->description }}</p>
                                    @endif
                                    @if ($prompt->last_error)
                                        <pre class="mb-0 mt-2 whitespace-pre-wrap break-words rounded-[6px] bg-[rgba(224,49,49,.08)] p-2 font-mono text-[11px] text-[color:var(--nx-danger)]">{{ $prompt->last_error }}</pre>
                                    @endif
                                </div>
                            @empty
                                <x-nx-empty icon="heroicon-o-cpu-chip">Noch kein Prompt zugewiesen — per <code class="text-xs">organization.signal_inference_prompts.PUT</code> mit <code class="text-xs">agent_entity_id={{ $entity->id }}</code>.</x-nx-empty>
                            @endforelse
                        </x-nx-panel>

                        <x-nx-panel flush title="Letzte Läufe">
                            <x-slot name="actions">
                                <a href="{{ route('organization.inference-runs.index') }}" wire:navigate class="text-xs font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle</a>
                            </x-slot>
                            @forelse ($this->agentRecentRuns as $run)
                                @php
                                    $rv = match ($run->status) { 'completed' => 'success', 'failed' => 'danger', 'running' => 'warning', default => 'neutral' };
                                @endphp
                                <a href="{{ route('organization.inference-runs.show', $run) }}" wire:navigate class="flex items-center gap-3 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm last:border-0 hover:bg-[color:var(--nx-hover)]">
                                    <span class="w-12 font-mono text-xs tabular-nums text-[color:var(--nx-faint)]">#{{ $run->id }}</span>
                                    <x-nx-badge :variant="$rv" dot>{{ $run->status }}</x-nx-badge>
                                    <span class="text-xs text-[color:var(--nx-muted)]" title="{{ $run->created_at->format('d.m.Y H:i:s') }}">{{ $run->created_at->diffForHumans() }}</span>
                                    <span class="ml-auto flex gap-3 text-xs tabular-nums text-[color:var(--nx-faint)]">
                                        <span>{{ $run->signals_created ?? 0 }} Signale</span>
                                        <span>{{ $run->entities_analyzed ?? 0 }} Einheiten</span>
                                        @if ($run->duration_ms > 0)<span>{{ $run->duration_ms < 1000 ? $run->duration_ms . ' ms' : number_format($run->duration_ms / 1000, 1, ',', '') . ' s' }}</span>@endif
                                    </span>
                                </a>
                            @empty
                                <x-nx-empty icon="heroicon-o-play">Noch keine Läufe.</x-nx-empty>
                            @endforelse
                        </x-nx-panel>
                    </div>
                @endif

                {{-- ═══ VSM-Besetzung ═══ --}}
                @if ($this->isCarrierEntity && $activeTab === 'vsm')
                    @php
                        $matrix = $this->vsmMatrix;
                        $occupied = collect($matrix)->where('is_vacant', false)->count();
                    @endphp
                    <x-nx-panel flush title="VSM-Besetzung" :subtitle="'Aus Sicht ' . $entity->name . ' — wer füllt welche Systemfunktion aus? ' . $occupied . ' von ' . count($matrix) . ' besetzt'">
                        @foreach ($matrix as $code => $cell)
                            <div class="flex flex-col gap-2 border-b border-[color:var(--nx-line)] px-4 py-3 last:border-0 md:flex-row md:items-start">
                                <div class="flex min-w-0 items-start gap-3 md:w-72 md:shrink-0">
                                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-[6px] bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)]">
                                        @svg('heroicon-o-' . (app('safe-svg')->resolve($cell['icon'] ?? null, 'heroicon-o-') ?? 'cube'), 'w-4 h-4')
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-[color:var(--nx-text)]">{{ $cell['label'] }}</div>
                                        <div class="text-xs text-[color:var(--nx-faint)]">{{ $cell['description'] }}</div>
                                    </div>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
                                    @if ($cell['is_vacant'])
                                        <x-nx-badge variant="warning">vakant</x-nx-badge>
                                    @else
                                        @foreach ($cell['assignments'] as $a)
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-[color:var(--nx-line)] py-0.5 pl-2.5 pr-1 text-xs {{ $a['is_active_today'] ? '' : 'opacity-50' }}">
                                                <a href="{{ route('organization.entities.show', $a['assigned_entity_id']) }}" wire:navigate class="font-medium text-[color:var(--nx-text)] hover:underline">{{ $a['assigned_name'] }}</a>
                                                @if ($a['scope'])<span class="text-[color:var(--nx-faint)]">· {{ $a['scope'] }}</span>@endif
                                                @if (! $a['is_active_today'])<span class="text-[color:var(--nx-warning)]" title="außerhalb des Gültigkeitszeitraums">inaktiv</span>@endif
                                                <button type="button" wire:click="removeVsmAssignment({{ $a['id'] }})" wire:confirm="Zuordnung {{ $a['assigned_name'] }} entfernen?"
                                                    class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[color:var(--nx-faint)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-danger)]" title="Entfernen">
                                                    @svg('heroicon-o-x-mark', 'w-3 h-3')
                                                </button>
                                            </span>
                                        @endforeach
                                    @endif
                                    <button type="button" wire:click="openVsmAssignmentModal('{{ $code }}')"
                                        class="inline-flex items-center gap-1 rounded-[6px] px-1.5 py-0.5 text-xs text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]">
                                        @svg('heroicon-o-plus', 'w-3.5 h-3.5') zuweisen
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </x-nx-panel>
                @endif

                {{-- ═══ Strategie: Mission, Vision, Fokusräume, Transformation Map, Regnosen ═══ --}}
                @if ($this->isCarrierEntity && $activeTab === 'strategy')
                    <div>
                        @php $strategy = $this->strategy; @endphp
                        @if($strategy === null)
                            <x-nx-card>
                                <div class="py-10 text-center">
                                    <div class="mx-auto w-12 h-12 rounded-full border border-[color:var(--nx-line)] flex items-center justify-center mb-4">
                                        @svg('heroicon-o-map', 'w-6 h-6 text-[color:var(--nx-faint)]')
                                    </div>
                                    <h3 class="text-sm font-semibold text-[color:var(--nx-text)]">Noch kein strategisches Zukunftsbild</h3>
                                    <p class="text-xs text-[color:var(--nx-muted)] mt-1 max-w-md mx-auto">
                                        Diese Carrier-Entity hat noch keine Mission, Vision oder Regnose. Lege sie an, um Zielbilder, Hindernisse und Meilensteine zu strukturieren.
                                    </p>
                                    <div class="mt-4 flex items-center justify-center gap-1.5 flex-wrap text-[10px] text-[color:var(--nx-faint)]">
                                        <span>MCP-Tools:</span>
                                        <code class="bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] px-1.5 py-0.5 rounded">organization.strategic_documents.POST</code>
                                        <code class="bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] px-1.5 py-0.5 rounded">organization.forecasts.POST</code>
                                        <code class="bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] px-1.5 py-0.5 rounded">organization.focus_areas.POST</code>
                                        <code class="bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] px-1.5 py-0.5 rounded">organization.milestones.POST</code>
                                    </div>
                                </div>
                            </x-nx-card>
                        @else
                            @php
                                $totalFA = count($strategy['focus_areas']); $totalVI = 0; $totalOb = 0; $totalMS = 0;
                                foreach ($strategy['focus_areas'] as $__fa) {
                                    $totalVI += count($__fa['vision_images']);
                                    $totalOb += count($__fa['obstacles']);
                                    $totalMS += count($__fa['milestones']);
                                }
                            @endphp

                            {{-- Kennzahlen: nur was nicht ohnehin unten als Abschnitt steht --}}
                            <div class="mb-8">
                                <x-nx-stat-grid :cols="4">
                                    <x-nx-stat label="Fokusräume" :value="$totalFA" icon="heroicon-o-squares-2x2" />
                                    <x-nx-stat label="Zielbilder" :value="$totalVI" icon="heroicon-o-photo" />
                                    <x-nx-stat label="Hindernisse" :value="$totalOb" icon="heroicon-o-exclamation-triangle" />
                                    <x-nx-stat label="Meilensteine" :value="$totalMS" icon="heroicon-o-flag" />
                                </x-nx-stat-grid>
                            </div>

                            {{-- Vollständigkeit gegen das Strategie-Blueprint --}}
                            @php $sc = $this->strategyCompleteness; @endphp
                            @if($sc)
                                <div class="mb-8">
                                    <x-nx-card>
                                        <div class="flex items-center justify-between gap-3 mb-3">
                                            <div class="flex items-center gap-2">
                                                <div class="text-sm font-medium text-[color:var(--nx-text)]">Vollständigkeit</div>
                                                @php $sm = $strategy['strategy_meta'] ?? null; @endphp
                                                @if($sm)
                                                    <x-nx-badge :variant="$sm['status'] === 'active' ? 'success' : ($sm['status'] === 'archived' ? 'neutral' : 'warning')">
                                                        {{ ['draft' => 'Entwurf', 'active' => 'Aktiv', 'archived' => 'Archiviert'][$sm['status']] ?? $sm['status'] }} · v{{ $sm['version'] }}
                                                    </x-nx-badge>
                                                @endif
                                            </div>
                                            <div class="text-sm font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $sc['percent'] }}%</div>
                                        </div>
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-[color:var(--nx-line)] mb-4">
                                            <div class="h-full rounded-full transition-all"
                                                 style="width: {{ $sc['percent'] }}%; background: {{ $sc['complete'] ? 'var(--nx-success)' : 'var(--nx-accent)' }}"></div>
                                        </div>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($sc['chapters'] as $ch)
                                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs {{ $ch['ok'] ? 'text-[color:var(--nx-success)] bg-[rgba(47,158,68,.10)]' : ($ch['required'] ? 'text-[color:var(--nx-warning)] bg-[rgba(232,89,12,.10)]' : 'text-[color:var(--nx-faint)] bg-[color:var(--nx-accent-soft)]') }}"
                                                      @if($ch['reason']) title="{{ $ch['reason'] }}" @endif>
                                                    {{ $ch['order'] }}. {{ $ch['label'] }}
                                                    @if($ch['ok'])<span aria-hidden="true">✓</span>@elseif($ch['required'])<span aria-hidden="true">!</span>@endif
                                                </span>
                                            @endforeach
                                        </div>
                                        @if(!empty($sc['issues']))
                                            <div class="mt-4 space-y-2">
                                                @foreach($sc['issues'] as $issue)
                                                    <x-nx-callout :variant="$issue['severity'] === 'error' ? 'warning' : 'info'">{{ $issue['message'] }}</x-nx-callout>
                                                @endforeach
                                            </div>
                                        @endif
                                    </x-nx-card>
                                </div>
                            @endif

                            {{-- Öffentlich teilen --}}
                            @php $publicUrl = $this->publicStrategyUrl; @endphp
                            <div class="mb-8">
                                <x-nx-card>
                                    @if($publicUrl)
                                        <div class="flex items-center justify-between gap-3 flex-wrap">
                                            <div class="min-w-0 flex items-center gap-2">
                                                <x-nx-badge variant="success" dot>öffentlich</x-nx-badge>
                                                <div x-data="{ copied: false }" class="flex items-center gap-1.5 min-w-0">
                                                    <input type="text" readonly value="{{ $publicUrl }}"
                                                           x-ref="publicLink"
                                                           class="text-xs text-[color:var(--nx-text)] bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] rounded-md px-2 py-1 w-[280px] max-w-full font-mono truncate">
                                                    <x-nx-button variant="ghost" size="sm"
                                                            x-on:click="navigator.clipboard.writeText($refs.publicLink.value); copied = true; setTimeout(() => copied = false, 1500)">
                                                        <span x-show="!copied">Kopieren</span>
                                                        <span x-show="copied" class="text-[color:var(--nx-success)]">✓ Kopiert</span>
                                                    </x-nx-button>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <x-nx-button variant="secondary" size="sm" :href="$publicUrl.'/pdf'" target="_blank">
                                                    @svg('heroicon-o-arrow-down-tray', 'w-3.5 h-3.5') PDF
                                                </x-nx-button>
                                                <x-nx-button variant="danger" size="sm" wire:click="revokePublicLink"
                                                        wire:confirm="Öffentlichen Link wirklich widerrufen? Bestehende Links werden ungültig.">
                                                    Widerrufen
                                                </x-nx-button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-between gap-3 flex-wrap">
                                            <div class="flex items-center gap-2 text-xs text-[color:var(--nx-muted)]">
                                                @svg('heroicon-o-share', 'w-4 h-4 text-[color:var(--nx-faint)]')
                                                <span>Strategie extern teilen — als Link ohne Login oder als PDF.</span>
                                            </div>
                                            <x-nx-button variant="primary" size="sm" wire:click="generatePublicLink">
                                                @svg('heroicon-o-globe-alt', 'w-3.5 h-3.5') Öffentlichen Link erstellen
                                            </x-nx-button>
                                        </div>
                                    @endif
                                </x-nx-card>
                            </div>

                            {{-- 1. Mission — volle Breite --}}
                            @if(!empty($strategy['mission']))
                                <div class="mb-8">
                                    <x-nx-section icon="heroicon-o-flag" title="Mission"
                                        hint="v{{ $strategy['mission']['version'] }} · gültig ab {{ $strategy['mission']['valid_from'] }}">
                                        <x-nx-card>
                                            <div class="text-sm font-semibold text-[color:var(--nx-text)] mb-2">{{ $strategy['mission']['title'] }}</div>
                                            @if(!empty($strategy['mission']['content']))
                                                <div class="nx-prose">
                                                    {!! \Illuminate\Support\Str::markdown($strategy['mission']['content']) !!}
                                                </div>
                                            @else
                                                <p class="text-xs text-[color:var(--nx-muted)] italic">Kein Inhalt gepflegt.</p>
                                            @endif
                                        </x-nx-card>
                                    </x-nx-section>
                                </div>
                            @endif

                            {{-- 2. Vision — volle Breite, klar von Mission getrennt --}}
                            @if(!empty($strategy['vision']))
                                <div class="mb-8">
                                    <x-nx-section icon="heroicon-o-sparkles" title="Vision"
                                        hint="v{{ $strategy['vision']['version'] }} · gültig ab {{ $strategy['vision']['valid_from'] }}">
                                        <x-nx-card>
                                            <div class="text-sm font-semibold text-[color:var(--nx-text)] mb-2">{{ $strategy['vision']['title'] }}</div>
                                            @if(!empty($strategy['vision']['content']))
                                                <div class="nx-prose">
                                                    {!! \Illuminate\Support\Str::markdown($strategy['vision']['content']) !!}
                                                </div>
                                            @else
                                                <p class="text-xs text-[color:var(--nx-muted)] italic">Kein Inhalt gepflegt.</p>
                                            @endif
                                        </x-nx-card>
                                    </x-nx-section>
                                </div>
                            @endif

                            {{-- 3. Fokusräume — entity-nativ (Modell-Shift), unabhängig von einer Regnose --}}
                            @if(!empty($strategy['focus_areas']))
                                <div class="mb-8">
                                    <x-nx-section icon="heroicon-o-squares-2x2" title="Fokusräume" hint="Strategische Handlungsfelder der Entity">
                                        <div class="space-y-3">
                                            @foreach($strategy['focus_areas'] as $fa)
                                                @php
                                                    $viCount = count($fa['vision_images']);
                                                    $obCount = count($fa['obstacles']);
                                                    $msCount = count($fa['milestones']);
                                                    $emptyFA = ($viCount + $obCount + $msCount) === 0;
                                                @endphp
                                                <x-nx-card flush>
                                                    <div class="px-4 py-3 border-b border-[color:var(--nx-line)]">
                                                        <div class="flex items-start gap-3">
                                                            <div class="w-7 h-7 bg-[color:var(--nx-bg)] text-[color:var(--nx-muted)] rounded-md flex items-center justify-center flex-shrink-0 border border-[color:var(--nx-line)] mt-0.5">
                                                                <span class="text-xs font-semibold">{{ $loop->iteration }}</span>
                                                            </div>
                                                            <div class="flex-1 min-w-0">
                                                                <h3 class="text-sm font-semibold text-[color:var(--nx-text)]">{{ $fa['title'] }}</h3>
                                                                @if(!empty($fa['description']))
                                                                    <p class="text-xs text-[color:var(--nx-muted)] mt-1 leading-relaxed">{{ $fa['description'] }}</p>
                                                                @endif
                                                            </div>
                                                            <div class="flex items-center gap-1 flex-shrink-0">
                                                                @if($viCount > 0)<x-nx-badge variant="neutral">@svg('heroicon-o-photo', 'w-3 h-3') {{ $viCount }}</x-nx-badge>@endif
                                                                @if($obCount > 0)<x-nx-badge variant="warning">@svg('heroicon-o-exclamation-triangle', 'w-3 h-3') {{ $obCount }}</x-nx-badge>@endif
                                                                @if($msCount > 0)<x-nx-badge variant="neutral">@svg('heroicon-o-flag', 'w-3 h-3') {{ $msCount }}</x-nx-badge>@endif
                                                            </div>
                                                        </div>
                                                    </div>

                                                    @if($emptyFA)
                                                        <div class="px-4 py-4 text-center">
                                                            <p class="text-xs text-[color:var(--nx-muted)] italic">Noch keine Zielbilder, Hindernisse oder Meilensteine gepflegt.</p>
                                                        </div>
                                                    @else
                                                        <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-[color:var(--nx-line)]">
                                                            {{-- Zielbilder --}}
                                                            <div class="p-4">
                                                                <div class="flex items-center gap-1.5 mb-2">
                                                                    @svg('heroicon-o-photo', 'w-3.5 h-3.5 text-[color:var(--nx-faint)]')
                                                                    <span class="text-[10px] uppercase tracking-wider text-[color:var(--nx-muted)] font-semibold">Zielbilder</span>
                                                                    @if($viCount > 0)<span class="text-[10px] text-[color:var(--nx-faint)]">{{ $viCount }}</span>@endif
                                                                </div>
                                                                @if(!empty($fa['central_question_vision_images']))
                                                                    <p class="text-[11px] text-[color:var(--nx-faint)] italic mb-2 leading-snug">{{ $fa['central_question_vision_images'] }}</p>
                                                                @endif
                                                                @if($viCount === 0)
                                                                    <p class="text-[11px] text-[color:var(--nx-faint)] italic">noch offen</p>
                                                                @else
                                                                    <ul class="space-y-2">
                                                                        @foreach($fa['vision_images'] as $vi)
                                                                            <li class="flex items-start gap-1.5 text-xs text-[color:var(--nx-text)]">
                                                                                <span class="w-1 h-1 rounded-full bg-[color:var(--nx-tone-sky)] mt-1.5 flex-shrink-0"></span>
                                                                                <div class="min-w-0 flex-1">
                                                                                    <span class="leading-snug">{{ $vi['title'] }}</span>
                                                                                    @if(!empty($vi['description']))
                                                                                        <p class="text-[11px] text-[color:var(--nx-muted)] mt-0.5 leading-snug">{{ $vi['description'] }}</p>
                                                                                    @endif
                                                                                    @if(!empty($vi['central_question']))
                                                                                        <p class="text-[11px] text-[color:var(--nx-faint)] italic mt-0.5 leading-snug">{{ $vi['central_question'] }}</p>
                                                                                    @endif
                                                                                </div>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @endif
                                                            </div>
                                                            {{-- Hindernisse --}}
                                                            <div class="p-4">
                                                                <div class="flex items-center gap-1.5 mb-2">
                                                                    @svg('heroicon-o-exclamation-triangle', 'w-3.5 h-3.5 text-[color:var(--nx-warning)]')
                                                                    <span class="text-[10px] uppercase tracking-wider text-[color:var(--nx-muted)] font-semibold">Hindernisse</span>
                                                                    @if($obCount > 0)<span class="text-[10px] text-[color:var(--nx-faint)]">{{ $obCount }}</span>@endif
                                                                </div>
                                                                @if(!empty($fa['central_question_obstacles']))
                                                                    <p class="text-[11px] text-[color:var(--nx-faint)] italic mb-2 leading-snug">{{ $fa['central_question_obstacles'] }}</p>
                                                                @endif
                                                                @if($obCount === 0)
                                                                    <p class="text-[11px] text-[color:var(--nx-faint)] italic">noch offen</p>
                                                                @else
                                                                    <ul class="space-y-2">
                                                                        @foreach($fa['obstacles'] as $ob)
                                                                            <li class="flex items-start gap-1.5 text-xs text-[color:var(--nx-text)]">
                                                                                <span class="w-1 h-1 rounded-full bg-[color:var(--nx-warning)] mt-1.5 flex-shrink-0"></span>
                                                                                <div class="min-w-0 flex-1">
                                                                                    <span class="leading-snug">{{ $ob['title'] }}</span>
                                                                                    @if(!empty($ob['description']))
                                                                                        <p class="text-[11px] text-[color:var(--nx-muted)] mt-0.5 leading-snug">{{ $ob['description'] }}</p>
                                                                                    @endif
                                                                                    @if(!empty($ob['central_question']))
                                                                                        <p class="text-[11px] text-[color:var(--nx-faint)] italic mt-0.5 leading-snug">{{ $ob['central_question'] }}</p>
                                                                                    @endif
                                                                                </div>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @endif
                                                            </div>
                                                            {{-- Meilensteine --}}
                                                            <div class="p-4">
                                                                <div class="flex items-center gap-1.5 mb-2">
                                                                    @svg('heroicon-o-flag', 'w-3.5 h-3.5 text-[color:var(--nx-faint)]')
                                                                    <span class="text-[10px] uppercase tracking-wider text-[color:var(--nx-muted)] font-semibold">Meilensteine</span>
                                                                    @if($msCount > 0)<span class="text-[10px] text-[color:var(--nx-faint)]">{{ $msCount }}</span>@endif
                                                                </div>
                                                                @if(!empty($fa['central_question_milestones']))
                                                                    <p class="text-[11px] text-[color:var(--nx-faint)] italic mb-2 leading-snug">{{ $fa['central_question_milestones'] }}</p>
                                                                @endif
                                                                @if($msCount === 0)
                                                                    <p class="text-[11px] text-[color:var(--nx-faint)] italic">noch offen</p>
                                                                @else
                                                                    <ul class="space-y-2">
                                                                        @foreach($fa['milestones'] as $m)
                                                                            <li class="flex items-start gap-1.5 text-xs text-[color:var(--nx-text)]">
                                                                                <span class="w-1 h-1 rounded-full bg-[color:var(--nx-tone-emerald)] mt-1.5 flex-shrink-0"></span>
                                                                                <div class="min-w-0 flex-1">
                                                                                    <div class="flex items-start gap-1.5">
                                                                                        <span class="leading-snug flex-1">{{ $m['title'] }}</span>
                                                                                        @if($m['target_year'] || $m['target_quarter'])
                                                                                            <span class="text-[9px] font-semibold text-[color:var(--nx-muted)] bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] rounded px-1 flex-shrink-0 whitespace-nowrap leading-4">
                                                                                                @if($m['target_year'] && $m['target_quarter']){{ $m['target_year'] }}·Q{{ $m['target_quarter'] }}
                                                                                                @elseif($m['target_year']){{ $m['target_year'] }}
                                                                                                @else Q{{ $m['target_quarter'] }}
                                                                                                @endif
                                                                                            </span>
                                                                                        @endif
                                                                                    </div>
                                                                                    @if(!empty($m['description']))
                                                                                        <p class="text-[11px] text-[color:var(--nx-muted)] mt-0.5 leading-snug">{{ $m['description'] }}</p>
                                                                                    @endif
                                                                                    @if(!empty($m['central_question']))
                                                                                        <p class="text-[11px] text-[color:var(--nx-faint)] italic mt-0.5 leading-snug">{{ $m['central_question'] }}</p>
                                                                                    @endif
                                                                                </div>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endif
                                                </x-nx-card>
                                            @endforeach
                                        </div>
                                    </x-nx-section>
                                </div>
                            @endif

                            {{-- 4. Transformation Map — Meilensteine über alle Fokusräume × Jahr --}}
                            @php $tmap = $strategy['transformation_map']; @endphp
                            @if(!empty($strategy['focus_areas']) && (count($tmap['years']) > 0 || !empty($tmap['no_year'])))
                                <div class="mb-8">
                                    <x-nx-section icon="heroicon-o-arrows-right-left" title="Transformation Map" hint="Meilensteine · Fokusraum × Jahr">
                                        <div class="overflow-x-auto rounded-lg border border-[color:var(--nx-line)]">
                                            <table class="w-full border-collapse">
                                                <thead>
                                                    <tr class="bg-[color:var(--nx-bg)]">
                                                        <th class="p-2.5 text-left text-[10px] uppercase tracking-wider font-semibold text-[color:var(--nx-muted)] sticky left-0 bg-[color:var(--nx-bg)] z-10 min-w-[160px] border-b border-[color:var(--nx-line)]">Fokusraum</th>
                                                        @foreach($tmap['years'] as $year)
                                                            <th class="p-2.5 text-center text-[10px] uppercase tracking-wider font-semibold text-[color:var(--nx-text)] min-w-[150px] border-b border-[color:var(--nx-line)]">{{ $year }}</th>
                                                        @endforeach
                                                        @if(!empty($tmap['no_year']))
                                                            <th class="p-2.5 text-center text-[10px] uppercase tracking-wider font-semibold text-[color:var(--nx-faint)] min-w-[150px] border-b border-[color:var(--nx-line)]" title="Meilensteine ohne Jahresangabe">ohne Jahr</th>
                                                        @endif
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-[color:var(--nx-line)]">
                                                    @foreach($strategy['focus_areas'] as $fa)
                                                        <tr class="hover:bg-[color:var(--nx-hover)]">
                                                            <td class="p-2.5 bg-[color:var(--nx-surface)] sticky left-0 z-10 border-r border-[color:var(--nx-line)]">
                                                                <span class="text-xs font-medium text-[color:var(--nx-text)]">{{ $fa['title'] }}</span>
                                                            </td>
                                                            @foreach($tmap['years'] as $year)
                                                                <td class="p-2 bg-[color:var(--nx-surface)] align-top border-r border-[color:var(--nx-line)] last:border-r-0">
                                                                    @php $cell = $tmap['grid'][$fa['id']][$year] ?? []; @endphp
                                                                    @if(count($cell) > 0)
                                                                        <div class="flex flex-col gap-1">
                                                                            @foreach($cell as $m)
                                                                                <div class="flex items-start gap-1 bg-[color:var(--nx-bg)] rounded px-1.5 py-1 border border-[color:var(--nx-line)]" title="Meilenstein #{{ $m['id'] }}">
                                                                                    <span class="text-[11px] text-[color:var(--nx-text)] leading-tight flex-1">{{ $m['title'] }}</span>
                                                                                    @if($m['target_quarter'])
                                                                                        <sup class="text-[9px] font-semibold text-[color:var(--nx-muted)] flex-shrink-0">Q{{ $m['target_quarter'] }}</sup>
                                                                                    @endif
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    @else
                                                                        <div class="text-[10px] text-[color:var(--nx-faint)] text-center">·</div>
                                                                    @endif
                                                                </td>
                                                            @endforeach
                                                            @if(!empty($tmap['no_year']))
                                                                <td class="p-2 bg-[color:var(--nx-surface)] align-top">
                                                                    @php $cell = $tmap['no_year'][$fa['id']] ?? []; @endphp
                                                                    @if(count($cell) > 0)
                                                                        <div class="flex flex-col gap-1">
                                                                            @foreach($cell as $m)
                                                                                <div class="bg-[color:var(--nx-bg)] rounded px-1.5 py-1 border border-[color:var(--nx-line)]" title="Meilenstein #{{ $m['id'] }}">
                                                                                    <span class="text-[11px] text-[color:var(--nx-text)] leading-tight">{{ $m['title'] }}</span>
                                                                                </div>
                                                                            @endforeach
                                                                        </div>
                                                                    @else
                                                                        <div class="text-[10px] text-[color:var(--nx-faint)] text-center">·</div>
                                                                    @endif
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </x-nx-section>
                                </div>
                            @endif

                            {{-- 5. Regnosen (Rückblicke aus der Zukunft) — optionale Erzähl-Blöcke --}}
                            @foreach($strategy['forecasts'] as $forecast)
                                @php
                                    $daysToTarget = null;
                                    $targetPassed = false;
                                    if ($forecast['target_date']) {
                                        try {
                                            $targetDt = \Carbon\Carbon::parse($forecast['target_date'])->startOfDay();
                                            $today = now()->startOfDay();
                                            $daysToTarget = $today->diffInDays($targetDt, false);
                                            $targetPassed = $daysToTarget < 0;
                                        } catch (\Throwable $e) {}
                                    }
                                @endphp
                                <div class="mb-8">
                                    <x-nx-section icon="heroicon-o-map" title="Regnose — {{ $forecast['title'] }}" hint="Rückblick aus der Zukunft">
                                        <x-slot name="action">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                @if($forecast['target_date'])
                                                    <x-nx-badge variant="neutral">
                                                        @svg('heroicon-o-calendar', 'w-3.5 h-3.5') {{ $forecast['target_date'] }}
                                                    </x-nx-badge>
                                                    @if($daysToTarget !== null)
                                                        <x-nx-badge :variant="$targetPassed ? 'neutral' : 'success'">
                                                            @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                                                            @if($targetPassed) Ziel überschritten
                                                            @else noch {{ (int) $daysToTarget }} {{ (int) $daysToTarget === 1 ? 'Tag' : 'Tage' }}
                                                            @endif
                                                        </x-nx-badge>
                                                    @endif
                                                @endif
                                                @if($forecast['current_version'])
                                                    <x-nx-badge variant="neutral">v{{ $forecast['current_version'] }}</x-nx-badge>
                                                @endif
                                            </div>
                                        </x-slot>

                                        @if(!empty($forecast['content']))
                                            <div class="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-[color:var(--nx-faint)] font-semibold mb-2 pl-1">
                                                @svg('heroicon-o-book-open', 'w-3.5 h-3.5')
                                                <span>Regnose-Rückblick — Erzählung aus der Zieljahres-Perspektive</span>
                                            </div>
                                            <div class="nx-prose">
                                                {!! \Illuminate\Support\Str::markdown($forecast['content']) !!}
                                            </div>
                                        @else
                                            <p class="text-xs text-[color:var(--nx-muted)] italic">Noch keine Regnose-Erzählung hinterlegt.</p>
                                        @endif
                                    </x-nx-section>
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endif

                {{-- ═══ Berichte ═══ --}}
                @if ($activeTab === 'reports')
                    <div class="space-y-5">
                        @php $pulse = $this->entityPulseSnapshot; @endphp
                        @if ($pulse)
                            @php
                                $pulseColor = match ($pulse['signal']) { 'red' => 'var(--nx-danger)', 'yellow' => 'var(--nx-warning)', default => 'var(--nx-success)' };
                            @endphp
                            <x-nx-panel flush title="Puls" :subtitle="$pulse['signal_reason']">
                                <x-slot name="actions">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $pulseColor }}"></span>
                                </x-slot>
                                @foreach (['derivations' => 'Aufmerksamkeit', 'movements' => 'Bewegung (' . $pulse['window_days'] . ' Tage)', 'states' => 'Zustand'] as $pKey => $pLabel)
                                    @if (! empty($pulse[$pKey]))
                                        <div class="border-b border-[color:var(--nx-line)] px-4 py-3">
                                            <div class="mb-1.5 text-xs font-medium text-[color:var(--nx-faint)]">{{ $pLabel }}</div>
                                            <ul class="m-0 grid list-none grid-cols-1 gap-x-6 gap-y-1 p-0 text-sm text-[color:var(--nx-text)] {{ $pKey === 'derivations' ? '' : 'md:grid-cols-2' }}">
                                                @foreach ($pulse[$pKey] as $row)
                                                    <li>{{ $row['text'] }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endforeach
                                <div class="px-4 py-2 text-[11px] text-[color:var(--nx-faint)]">berechnet {{ $pulse['computed_at'] }} · {{ $pulse['total_facts'] }} Fakten</div>
                            </x-nx-panel>
                        @endif

                        @php $feeds = $this->verbalizationFeeds; @endphp
                        <x-nx-panel flush title="Berichte" :subtitle="$includeDescendantsInReports ? 'für ' . $entity->name . ' und den ganzen Teilbaum' : 'nur direkt an ' . $entity->name">
                            <x-slot name="actions">
                                <x-nx-button size="sm" variant="ghost" wire:click="toggleReportsScope">{{ $includeDescendantsInReports ? 'Nur diese Einheit' : 'Mit Teilbaum' }}</x-nx-button>
                            </x-slot>

                            @forelse ($feeds as $feed)
                                <div class="border-b border-[color:var(--nx-line)] px-4 py-3 last:border-0" x-data="{ setup: false }">
                                    <div class="flex items-start gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $feed['title'] }}</span>
                                                @unless ($feed['is_active'])<x-nx-badge>inaktiv</x-nx-badge>@endunless
                                                <span class="text-xs text-[color:var(--nx-faint)]">{{ $feed['outputs_count'] }} Ausgaben{{ $feed['last_refreshed_at'] ? ' · zuletzt ' . $feed['last_refreshed_at'] : '' }}</span>
                                            </div>
                                            @if ($feed['description'])
                                                <p class="mb-0 mt-0.5 text-xs text-[color:var(--nx-muted)]">{{ $feed['description'] }}</p>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 items-center gap-1">
                                            <button type="button" x-on:click="setup = !setup" class="rounded-[6px] px-2 py-1 text-xs text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" x-text="setup ? 'Konfiguration ausblenden' : 'Konfiguration'"></button>
                                            <a href="{{ $feed['feed_url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $feed['feed_url'] }}"
                                               class="inline-flex items-center gap-1 rounded-[6px] px-2 py-1 text-xs text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]">
                                                @svg('heroicon-o-rss', 'w-3.5 h-3.5') Feed
                                            </a>
                                        </div>
                                    </div>

                                    @if ($feed['latest'])
                                        <div class="mt-2 rounded-[6px] bg-[color:var(--nx-bg)] px-3 py-2">
                                            <div class="mb-1 text-[11px] text-[color:var(--nx-faint)]">Letzter Bericht · {{ $feed['latest']['created_at'] }}@if ($feed['latest']['model']) · {{ $feed['latest']['model'] }}@endif</div>
                                            <p class="m-0 text-sm leading-relaxed text-[color:var(--nx-text)]">{{ $feed['latest']['preview'] }}</p>
                                        </div>
                                    @else
                                        <p class="mb-0 mt-2 text-xs text-[color:var(--nx-faint)]">Noch kein Bericht erzeugt.</p>
                                    @endif

                                    {{-- Technische Einrichtung: für Betreuer, standardmäßig zu --}}
                                    <div x-show="setup" x-cloak class="mt-3 space-y-2 border-l-2 border-[color:var(--nx-line)] pl-3 text-xs text-[color:var(--nx-muted)]">
                                        <div class="flex flex-wrap gap-1.5">
                                            <x-nx-badge>{{ $feed['subject_type'] }}</x-nx-badge>
                                            <x-nx-badge>{{ $feed['refresh_strategy'] }}</x-nx-badge>
                                            <x-nx-badge>Zugriff {{ $feed['access'] }}</x-nx-badge>
                                            <x-nx-badge>Verlauf {{ $feed['item_strategy'] }} ({{ $feed['retention_items'] }})</x-nx-badge>
                                            @if ($feed['subject_selector_descend'] !== false && $feed['subject_selector_descend'] !== null)
                                                <x-nx-badge>Teilbaum{{ $feed['subject_selector_descend'] === true ? '' : ' Tiefe ' . $feed['subject_selector_descend'] }}</x-nx-badge>
                                            @endif
                                        </div>
                                        @foreach ($feed['recipe_details'] as $rd)
                                            @php
                                                $activeN = $rd['include_natures'];
                                                $isFilter = is_array($activeN) && count($activeN) > 0;
                                                $natures = collect(['state' => 'State', 'movement' => 'Movement', 'derivation' => 'Derivation'])
                                                    ->filter(fn ($l, $n) => ! $isFilter || in_array($n, $activeN))->implode(', ');
                                                $sources = collect($rd['source_flags'])->except('__descend')->filter(fn ($sf) => $sf['on'])
                                                    ->map(fn ($sf) => $sf['label'] . ($sf['detail'] ? ' (' . $sf['detail'] . ')' : ''))->implode(', ');
                                            @endphp
                                            <div>
                                                <span class="font-medium text-[color:var(--nx-text)]">Recipe {{ $rd['key'] }}</span>
                                                @if ($rd['llm_model']) · {{ $rd['llm_provider'] }}/{{ $rd['llm_model'] }}@endif
                                                @if (! empty($rd['style']['tone'])) · Ton {{ $rd['style']['tone'] }}@endif
                                                @if (! empty($rd['style']['address'])) · {{ $rd['style']['address'] }}@endif
                                                @if ($rd['freshness_requirement']) · Frische {{ $rd['freshness_requirement'] }}@endif
                                                <div>Naturen: {{ $natures }}@if ($rd['descend'] !== false && $rd['descend'] !== null) · Teilbaum{{ $rd['descend'] === true ? '' : ' Tiefe ' . $rd['descend'] }}@endif</div>
                                                @if ($sources)<div>Quellen: {{ $sources }}</div>@endif
                                            </div>
                                        @endforeach
                                        @php
                                            $channels = collect($feed['available_channel_types'])->map(function ($cmeta, $ctype) use ($feed) {
                                                $ch = collect($feed['channels'])->firstWhere('type', $ctype);
                                                return ($ch && $ch['is_active']) ? $cmeta['label'] . ($ch['summary'] ? ' (' . $ch['summary'] . ')' : '') : null;
                                            })->filter()->implode(', ');
                                        @endphp
                                        <div>Kanäle: {{ $channels ?: 'keine aktiv' }}</div>
                                    </div>
                                </div>
                            @empty
                                <x-nx-empty icon="heroicon-o-document-text">Keine Berichte in diesem Umfang. Neue Feeds per <code class="text-xs">core.verbalization.feeds.POST</code>.</x-nx-empty>
                            @endforelse
                        </x-nx-panel>
                    </div>
                @endif

                {{-- ═══ Aktivität ═══ --}}
                @if ($activeTab === 'activity')
                    <x-nx-panel flush title="Aktivität" subtitle="Änderungen, Zeitbuchungen und Notizen">
                        <livewire:organization.activity-feed :entityId="$entity->id" :key="'activity-'.$entity->id" />
                    </x-nx-panel>
                @endif

                {{-- ═══ Einstellungen: Stammdaten, Fremd-IDs, Modul-Zugang, Perspektive ═══ --}}
                @if ($activeTab === 'data')
                    <div class="space-y-5">
                        <x-nx-panel title="Stammdaten">
                            <div class="space-y-3">
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <x-nx-input-text name="name" label="Name" wire:model.live="form.name" errorKey="form.name" required />
                                    <x-nx-input-text name="code" label="Code" wire:model.live="form.code" errorKey="form.code" placeholder="optional" />
                                </div>
                                <x-nx-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" rows="3" />
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <x-nx-input-select name="entity_type_id" label="Typ" :options="$this->entityTypes->flatten()" optionValue="id" optionLabel="name"
                                        wire:model.live="form.entity_type_id" errorKey="form.entity_type_id" required />
                                    <x-nx-input-select name="parent_entity_id" label="Übergeordnete Einheit" :options="$this->parentEntities" optionValue="id" optionLabel="name"
                                        nullable nullLabel="Keine" wire:model.live="form.parent_entity_id" errorKey="form.parent_entity_id" />
                                </div>
                                <x-nx-input-select name="linked_user_id" label="Verknüpfter User" hint="nur für Personen und Agenten" :options="$this->teamUsers" optionValue="id" optionLabel="name"
                                    nullable nullLabel="Keiner" wire:model.live="form.linked_user_id" errorKey="form.linked_user_id" />
                                <x-nx-input-checkbox label="Aktiv" wire:model.live="form.is_active" />
                            </div>
                        </x-nx-panel>

                        <x-nx-panel flush title="Fremd-IDs" subtitle="Kennungen dieser Einheit in anderen Systemen — die Kostenstelle ist das System „kostenstelle“">
                            <x-slot name="actions">
                                <x-nx-button size="sm" variant="ghost" wire:click="addIdentifier">
                                    @svg('heroicon-o-plus', 'w-4 h-4')
                                    <span>Hinzufügen</span>
                                </x-nx-button>
                            </x-slot>
                            <datalist id="org-known-systems">
                                @foreach (\Platform\Organization\Models\OrganizationEntityExternalId::KNOWN_SYSTEMS as $sysKey => $sysLabel)
                                    <option value="{{ $sysKey }}">{{ $sysLabel }}</option>
                                @endforeach
                            </datalist>
                            @if (empty($identifiers))
                                <x-nx-empty icon="heroicon-o-identification">Noch keine Fremd-IDs.</x-nx-empty>
                            @else
                                <div class="space-y-2 p-4">
                                    <div class="hidden gap-2 px-0.5 text-xs font-medium text-[color:var(--nx-faint)] md:grid md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)_2rem]">
                                        <span>System</span><span>Wert</span><span>Bezeichnung</span><span></span>
                                    </div>
                                    @foreach ($identifiers as $i => $identifier)
                                        <div wire:key="identifier-{{ $i }}" class="grid grid-cols-1 items-start gap-2 md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)_2rem]">
                                            <input type="text" list="org-known-systems" wire:model.live="identifiers.{{ $i }}.system" placeholder="z. B. kostenstelle" class="{{ $field }}" />
                                            <input type="text" wire:model.live="identifiers.{{ $i }}.value" placeholder="z. B. KST-4200" class="{{ $field }}" />
                                            <input type="text" wire:model.live="identifiers.{{ $i }}.label" placeholder="optional" class="{{ $field }}" />
                                            <button type="button" wire:click="removeIdentifier({{ $i }})" title="Entfernen"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-faint)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-danger)]">
                                                @svg('heroicon-o-trash', 'w-4 h-4')
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </x-nx-panel>

                        @if ($this->hasLinkedUser)
                            <x-nx-panel flush title="Modul-Zugang" subtitle="Welche Module diese Person nutzen kann. Lese- und Schreibrechte auf Inhalte regelt die Organisationsstruktur.">
                                @unless ($this->canManageModuleAccess)
                                    <div class="border-b border-[color:var(--nx-line)] px-4 py-2 text-xs text-[color:var(--nx-warning)]">Nur Team-Admins können das ändern.</div>
                                @endunless
                                @foreach ($this->moduleAccessRows as $mod)
                                    <div wire:key="mod-access-{{ $mod['key'] }}" class="flex items-center justify-between gap-3 border-b border-[color:var(--nx-line)] px-4 py-2 last:border-0">
                                        <div class="min-w-0 text-sm">
                                            <span class="font-medium text-[color:var(--nx-text)]">{{ $mod['title'] }}</span>
                                            <span class="ml-1 font-mono text-xs text-[color:var(--nx-faint)]">{{ $mod['key'] }}</span>
                                        </div>
                                        <button type="button" wire:click="toggleModule('{{ $mod['key'] }}')" @disabled(! $this->canManageModuleAccess)
                                            title="{{ $mod['enabled'] ? 'Deaktivieren' : 'Aktivieren' }}"
                                            class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors disabled:opacity-40 {{ $mod['enabled'] ? 'bg-[color:var(--nx-accent)]' : 'bg-[color:var(--nx-line-strong)]' }}">
                                            <span class="inline-block h-3.5 w-3.5 rounded-full bg-[color:var(--nx-surface)] transition-transform {{ $mod['enabled'] ? 'translate-x-[18px]' : 'translate-x-[3px]' }}"></span>
                                        </button>
                                    </div>
                                @endforeach
                            </x-nx-panel>
                        @endif

                        @if ($this->isCarrierEntity)
                            @php
                                $perspectiveTeams = $this->perspectiveTeamAssignments;
                                $availableTeams = $this->perspectiveAvailableTeams;
                            @endphp
                            <x-nx-panel flush title="Perspektive für Plattform-Teams" :subtitle="'Mitglieder dieser Teams können ' . $entity->name . ' als VSM-Sicht nutzen. „Standard“ ist die Sicht für alle, die nie selbst eine gewählt haben — wichtig fürs Signal-Routing.'">
                                @forelse ($perspectiveTeams as $pt)
                                    <div class="flex items-center gap-3 border-b border-[color:var(--nx-line)] px-4 py-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $pt['team_name'] }}</div>
                                            @if (! empty($pt['parent_name']))<div class="truncate text-xs text-[color:var(--nx-faint)]">in {{ $pt['parent_name'] }}</div>@endif
                                        </div>
                                        @if ($pt['is_default'])
                                            <x-nx-badge variant="success" dot>Standard</x-nx-badge>
                                        @else
                                            <button type="button" wire:click="markPerspectiveTeamDefault({{ $pt['id'] }})" class="text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:underline">als Standard</button>
                                        @endif
                                        <button type="button" wire:click="detachTeamFromPerspective({{ $pt['id'] }})" wire:confirm="Team-Zuordnung wirklich entfernen?" title="Entfernen"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-[6px] text-[color:var(--nx-faint)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-danger)]">
                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                        </button>
                                    </div>
                                @empty
                                    <p class="m-0 border-b border-[color:var(--nx-line)] px-4 py-3 text-sm text-[color:var(--nx-faint)]">Noch keine Teams zugeordnet.</p>
                                @endforelse
                                @if (! empty($availableTeams))
                                    <div class="flex items-center gap-2 px-4 py-3" x-data="{ pickTeamId: '' }">
                                        <select x-model="pickTeamId" class="{{ $field }} flex-1">
                                            <option value="">Team auswählen …</option>
                                            @foreach ($availableTeams as $t)
                                                <option value="{{ $t['id'] }}">{{ $t['name'] }}{{ ! empty($t['parent_name']) ? ' · ' . $t['parent_name'] : ' · (Root)' }}</option>
                                            @endforeach
                                        </select>
                                        <x-nx-button size="sm" x-bind:disabled="!pickTeamId" x-on:click="$wire.attachTeamToPerspective(parseInt(pickTeamId)); pickTeamId = ''">Zuordnen</x-nx-button>
                                    </div>
                                @elseif (! empty($perspectiveTeams))
                                    <p class="m-0 px-4 py-2 text-xs text-[color:var(--nx-faint)]">Alle verfügbaren Teams sind zugeordnet.</p>
                                @endif
                            </x-nx-panel>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Plattform-Team aus der Einheit erstellen --}}
        <x-nx-modal wire:model="showCreateTeamModal" size="md">
            <x-slot name="header">Plattform-Team erstellen</x-slot>
            <div class="space-y-4">
                <x-nx-input-text name="team_name" label="Team-Name" wire:model.live="newTeam.name" errorKey="newTeam.name" required placeholder="Name des Teams" />
                <x-nx-input-select name="parent_team_id" label="Eltern-Team" :options="$this->availableTeams" optionValue="id" optionLabel="name"
                    nullable nullLabel="Keins" wire:model.live="newTeam.parent_team_id" />
            </div>
            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-nx-button variant="ghost" wire:click="closeCreateTeamModal">Abbrechen</x-nx-button>
                    <x-nx-button variant="primary" wire:click="createTeam">Erstellen</x-nx-button>
                </div>
            </x-slot>
        </x-nx-modal>

        {{-- VSM-Zuordnung --}}
        @php
            $sysCode = $vsmAssignmentForm['vsm_system'] ?? '';
            $sysLabel = \Platform\Organization\Models\OrganizationEntityVsmAssignment::VSM_DEFINITIONS[$sysCode]['label'] ?? $sysCode;
        @endphp
        <x-nx-modal wire:model="vsmAssignmentModalShow" size="md">
            <x-slot name="header">{{ $sysLabel }} besetzen</x-slot>
            <form wire:submit.prevent="addVsmAssignment" class="space-y-4">
                <p class="m-0 text-xs text-[color:var(--nx-muted)]">Aus Sicht <span class="font-medium text-[color:var(--nx-text)]">{{ $entity->name }}</span> — wer füllt {{ $sysLabel }} aus?</p>
                <x-nx-input-select name="assigned_entity_id" label="Actor" :options="$this->vsmActorEntities" optionValue="id" optionLabel="name"
                    nullable nullLabel="– auswählen –" wire:model.live="vsmAssignmentForm.assigned_entity_id" errorKey="vsmAssignmentForm.assigned_entity_id" required />
                <x-nx-input-text name="scope" label="Scope" wire:model.live="vsmAssignmentForm.scope" placeholder="optional, z. B. Cashflow, Backend" />
                <x-nx-input-textarea name="notes" label="Notiz" wire:model.live="vsmAssignmentForm.notes" placeholder="optional — warum diese Zuordnung?" rows="3" />
            </form>
            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <x-nx-button variant="ghost" wire:click="closeVsmAssignmentModal">Abbrechen</x-nx-button>
                    <x-nx-button variant="primary" wire:click="addVsmAssignment">Zuweisen</x-nx-button>
                </div>
            </x-slot>
        </x-nx-modal>
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

        const chevronSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[color:var(--nx-muted)]"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>`;
        const spinnerSvg = `<svg class="w-4 h-4 animate-spin text-[color:var(--nx-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;
        const externalSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-[color:var(--nx-muted)]"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>`;

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
                        if (val) parts.push('<span class="text-[color:var(--nx-success)]">erledigt</span>');
                        break;
                    case 'boolean_active':
                        if (val === false) parts.push('<span class="text-[color:var(--nx-warning)]">inaktiv</span>');
                        break;
                    case 'boolean_published':
                        if (val) parts.push('<span class="text-[color:var(--nx-success)]">veröffentlicht</span>');
                        break;
                    case 'boolean_pinned':
                        if (val) parts.push('angepinnt');
                        break;
                    case 'boolean_frog':
                        if (val) parts.push('<span class="text-[color:var(--nx-success)]">🐸</span>');
                        break;
                    case 'badge':
                        if (val) parts.push(escHtml(String(val)));
                        break;
                }
            }
            if (parts.length === 0) return '';
            return `<span class="inline-flex items-center gap-1.5 text-[10px] text-[color:var(--nx-muted)] flex-shrink-0">${parts.join(' · ')}</span>`;
        }

        // Shared render function for a node card (used at all Alpine levels)
        window._treeRenderNode = function(node, linkConfig, linkIconSvgs) {
            const isExpandable = node.has_children || (node.own_links_grouped && node.own_links_grouped.length > 0);
            const totalMin = node.cascaded_time ? node.cascaded_time.total_minutes : 0;
            const billedMin = node.cascaded_time ? node.cascaded_time.billed_minutes : 0;
            const openMin = totalMin - billedMin;

            let html = `<div class="group rounded-[6px] transition-colors hover:bg-[color:var(--nx-hover)] py-2 px-3 ${!node.is_active ? 'opacity-50' : ''}">`;
            html += `<div class="flex items-center gap-2 ${isExpandable ? 'cursor-pointer' : ''}" @click="nodeToggle()">`;

            // Chevron/spinner
            html += `<div class="w-5 h-5 flex items-center justify-center flex-shrink-0">`;
            if (isExpandable) {
                html += `<template x-if="nodeLoading">${spinnerSvg}</template>`;
                html += `<template x-if="!nodeLoading"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[color:var(--nx-muted)] transition-transform duration-200" :class="{ 'rotate-90': nodeExpanded }"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg></template>`;
            }
            html += `</div>`;

            // Type icon (pre-rendered SVG)
            if (node.type_icon_svg) {
                html += node.type_icon_svg;
            }

            // Name link
            html += `<a href="/organization/entities/${node.id}" class="text-sm font-semibold text-[color:var(--nx-text)] hover:text-[color:var(--nx-text)] hover:underline truncate" @click.stop>${escHtml(node.name)}</a>`;

            // Code
            if (node.code) {
                html += `<span class="text-xs text-[color:var(--nx-muted)] font-mono flex-shrink-0">${escHtml(node.code)}</span>`;
            }

            // Type badge
            html += `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)] flex-shrink-0">${escHtml(node.type_name)}</span>`;

            // Time
            if (totalMin > 0) {
                const dotClass = openMin > 0 ? 'bg-[color:var(--nx-warning)]' : 'bg-[color:var(--nx-success)]';
                html += `<div class="flex items-center gap-2 ml-auto flex-shrink-0">`;
                html += `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[color:var(--nx-text)]">`;
                html += `<span class="w-2 h-2 rounded-full flex-shrink-0 ${dotClass}"></span>`;
                html += formatTime(totalMin);
                html += `</span>`;
                if (openMin > 0) {
                    html += `<span class="text-[10px] text-[color:var(--nx-warning)] font-medium">${formatOpenTime(totalMin, billedMin)}</span>`;
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
                            html += `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)] border border-[color:var(--nx-line)]">`;
                            html += iconSvg;
                            html += `${escHtml(linkConfig[type].label)} <span class="font-semibold text-[color:var(--nx-text)]">${count}</span>`;
                            html += `</span>`;
                        }
                    }
                }
                if (node.descendant_count > 0) {
                    html += `<span class="text-[10px] text-[color:var(--nx-muted)] ml-1">inkl. ${node.descendant_count} ${node.descendant_count === 1 ? 'Untereinheit' : 'Untereinheiten'}</span>`;
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
                html += `<div x-data="{ gOpen: $store.tree.allExpanded, init() { this.$watch('$store.tree.allExpanded', v => this.gOpen = v); } }" class="ml-6 border-l border-[color:var(--nx-line)]">`;
                // Group header
                html += `<div class="group rounded-[6px] transition-colors hover:bg-[color:var(--nx-hover)] py-2 px-3 cursor-pointer" @click.stop="gOpen = !gOpen">`;
                html += `<div class="flex items-center gap-2">`;
                html += `<div class="w-5 h-5 flex items-center justify-center flex-shrink-0"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[color:var(--nx-muted)] transition-transform duration-200" :class="{ 'rotate-90': gOpen }"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg></div>`;
                html += iconSvg;
                html += `<span class="text-sm font-medium text-[color:var(--nx-text)]">${escHtml(group.label)}</span>`;
                html += `<span class="text-xs text-[color:var(--nx-muted)]">(${group.items.length})</span>`;
                if (group.group_logged_minutes > 0) {
                    html += `<span class="text-xs text-[color:var(--nx-muted)] ml-auto flex-shrink-0">${formatTime(group.group_logged_minutes)}</span>`;
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

                    html += `<div class="ml-6 border-l border-[color:var(--nx-line)]"${hasChildren ? ` x-data="{ ${linkId}: $store.tree.allExpanded, init() { this.$watch('$store.tree.allExpanded', v => this.${linkId} = v); } }"` : ''}${doneShowAttr}>`;
                    html += `<div class="group rounded-[6px] transition-colors hover:bg-[color:var(--nx-hover)] py-2 px-3">`;
                    html += `<div class="flex items-center gap-2${hasChildren ? ' cursor-pointer' : ''}"${hasChildren ? ` @click="${linkId} = !${linkId}"` : ''}>`;

                    // Chevron for expandable items
                    html += `<div class="w-5 h-5 flex items-center justify-center flex-shrink-0">`;
                    if (hasChildren) {
                        html += `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[color:var(--nx-muted)] transition-transform duration-200" :class="{ 'rotate-90': ${linkId} }"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>`;
                    }
                    html += `</div>`;

                    html += iconSvg;
                    if (link.url) {
                        html += `<a href="${escHtml(link.url)}" class="text-sm font-medium text-[color:var(--nx-text)] hover:text-[color:var(--nx-text)] hover:underline truncate${doneNameClass}" @click.stop>${escHtml(link.name)}</a>`;
                    } else {
                        html += `<span class="text-sm font-medium text-[color:var(--nx-text)] truncate${doneNameClass}">${escHtml(link.name)}</span>`;
                    }
                    html += renderLinkMeta(link, group.type);
                    if (link.status) {
                        html += `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)] flex-shrink-0">${escHtml(link.status)}</span>`;
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
                            html += `<div class="ml-6 border-l border-[color:var(--nx-line)]"${childDoneShow}>`;
                            html += `<div class="group rounded-[6px] transition-colors hover:bg-[color:var(--nx-hover)] py-2 px-3">`;
                            html += `<div class="flex items-center gap-2">`;
                            html += `<div class="w-5 h-5 flex-shrink-0"></div>`;
                            html += childIconSvg;
                            html += `<span class="text-sm font-medium text-[color:var(--nx-text)] truncate ${childDoneClass}">${escHtml(childName)}</span>`;
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
