{{-- Kunden-Tor · Übersicht als Lagebericht: was sich seit dem letzten Besuch getan hat, Kennzahlen, Projekte
     (laufend, ruhend mit Grund, Archiv), Aktivität, Als Nächstes, Leistung und Rechnungen. --}}
@php
    $k = $data['kpis'];
    $work = $data['work'];
    $finance = $data['finance'];
    $since = $data['since'];
    $allProjects = collect($data['featured'])->flatMap(fn ($g) => collect($g['items'])->map(fn ($i) => $i + ['slug' => $g['slug']]));
    $projectCount = $allProjects->count();
    $doneCount = $allProjects->where('state', 'done')->count();
    $runningCount = $projectCount - $doneCount;
    $openSteps = max(0, ($k['steps_total'] ?? 0) - ($k['steps_done'] ?? 0));
    $paused = $allProjects->where('state', 'paused');
    $news = array_filter([
        $since['minutes'] ? ['heroicon-s-clock', 'var(--nx-tone-violet)', $hours($since['minutes']) . ' gearbeitet', $since['people'] ? 'von ' . collect($since['people'])->take(3)->implode(', ') . (count($since['people']) > 3 ? ' u. a.' : '') : null] : null,
        $since['done'] ? ['heroicon-s-check-circle', 'var(--nx-success)', $since['done'] . ' ' . ($since['done'] === 1 ? 'Schritt' : 'Schritte') . ' erledigt', null] : null,
        $since['canvas'] ? ['heroicon-s-squares-2x2', 'var(--nx-info)', $since['canvas'] . ' Canvas-' . ($since['canvas'] === 1 ? 'Bearbeitung' : 'Bearbeitungen'), null] : null,
        $since['payments'] ? ['heroicon-s-banknotes', 'var(--nx-success)', $since['payments'] . ' ' . ($since['payments'] === 1 ? 'Zahlung' : 'Zahlungen') . ' eingegangen', null] : null,
    ]);
    $lastWork = $work['last_date'] ?? null;
@endphp
<div class="gp-wrap py-8 md:py-10">
    {{-- Kopf --}}
    <div class="flex items-start gap-4">
        {!! $tile($entity->name, 'e' . $entity->id, 48) !!}
        <div class="min-w-0">
            <div class="text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $entity->type?->name ?? 'Ihr Bereich' }}</div>
            <h1 class="mt-0.5 text-2xl font-bold leading-tight tracking-tight md:text-3xl">{{ $title }}</h1>
            <div class="mt-1 text-sm text-[color:var(--nx-muted)]">
                {{ collect([$runningCount ? $runningCount . ' ' . ($runningCount === 1 ? 'laufendes Projekt' : 'laufende Projekte') : null, $doneCount ? $doneCount . ' abgeschlossen' : null, $work && $work['period_minutes']['30'] ? $hours($work['period_minutes']['30']) . ' in den letzten 30 Tagen' : null])->filter()->implode(' · ') }}{{ $projectCount || $work ? ' · ' : '' }}Stand {{ $de(now(), 'D. MMMM YYYY') }}
            </div>
        </div>
    </div>

    @if ($share->message)
        <div class="mt-6 rounded-[12px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-4 sm:p-5">
            @if ($senderName)
                <div class="mb-2 flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[color:var(--nx-accent)] text-[10px] font-semibold text-[color:var(--nx-on-accent)]">{{ mb_strtoupper(mb_substr($senderName, 0, 1)) }}</span>
                    <span class="text-sm font-medium">{{ $senderName }}</span>
                    <span class="text-xs text-[color:var(--nx-faint)]">{{ $teamName }}</span>
                </div>
            @endif
            <div class="max-w-4xl whitespace-pre-line text-[15px] leading-relaxed">{{ $share->message }}</div>
        </div>
    @endif

    {{-- Lage: was sich seit dem letzten Besuch getan hat — auch, wenn sich nichts bewegt --}}
    @if ($data['featured'] || $work)
        <div class="gp-card mt-6 p-4 sm:p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="gp-h2">{{ $since['is_visit'] ? 'Seit Ihrem letzten Besuch' : 'In den letzten 7 Tagen' }}</h2>
                <span class="text-xs text-[color:var(--nx-faint)]">{{ $since['is_visit'] ? $de($since['at'], 'dd, D. MMMM, HH:mm') . ' Uhr' : 'seit ' . $de($since['at'], 'D. MMMM') }}</span>
            </div>
            @if ($news)
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($news as [$icon, $color, $label, $hint])
                        <span class="inline-flex items-center gap-2 rounded-full border border-[color:var(--nx-line)] px-3 py-1.5 text-sm">
                            <span style="color: {{ $color }}">@svg($icon, 'w-4 h-4')</span>
                            <span class="font-medium">{{ $label }}</span>
                            @if ($hint)<span class="text-[color:var(--nx-muted)]">{{ $hint }}</span>@endif
                        </span>
                    @endforeach
                </div>
            @else
                <p class="mt-2 text-sm text-[color:var(--nx-muted)]">
                    Seitdem ist nichts Neues eingetragen.
                    @if ($lastWork) Zuletzt wurde {{ $lastWork->isToday() || $lastWork->isYesterday() ? strtolower($dayLabel($lastWork)) : 'am ' . $de($lastWork, 'D. MMMM') }} gearbeitet.@endif
                </p>
            @endif
            @if ($paused->isNotEmpty())
                <div class="mt-3 space-y-1.5 border-t border-[color:var(--nx-line)] pt-3">
                    @foreach ($paused as $p)
                        <div class="flex items-start gap-2 text-sm">
                            @svg('heroicon-s-pause-circle', 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-warning)]')
                            <span><span class="font-medium">{{ $p['title'] }}</span> ruht{{ ! empty($p['state_since']) ? ' seit ' . $de($p['state_since'], 'D. MMMM') : '' }}{!! ! empty($p['reason']) ? ' — <span class="text-[color:var(--nx-muted)]">' . e($p['reason']) . '</span>' : '' !!}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Kennzahlen --}}
        <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="gp-card col-span-2 flex items-center gap-4 p-4 lg:col-span-1">
                {!! $ring($k['progress'] ?? 0, 52, true) !!}
                <div class="min-w-0">
                    <div class="text-sm font-medium">Gesamtfortschritt</div>
                    <div class="mt-0.5 text-xs text-[color:var(--nx-muted)]">
                        {{ $k['steps_total'] ? $k['steps_done'] . ' von ' . $k['steps_total'] . ' Schritten erledigt' : 'Noch keine Schritte geplant' }}
                    </div>
                </div>
            </div>
            @foreach (array_values(array_filter([
                $work ? ['Letzte 30 Tage', $hours($work['period_minutes']['30']), 'gesamt ' . $hours($work['total_minutes']) . ($work['first_date'] ? ' seit ' . $de($work['first_date'], 'MMM YYYY') : ''), 'heroicon-o-clock', 'var(--nx-tone-violet)', $pageLink('leistung')] : ['Aktive Projekte', $k['active'], $projectCount ? 'von ' . $projectCount : null, 'heroicon-o-bolt', 'var(--nx-info)', null],
                ($k['overdue'] ?? 0)
                    ? ['Überfällig', $k['overdue'], ($k['overdue'] === 1 ? 'Schritt' : 'Schritte') . ' über dem Termin', 'heroicon-o-exclamation-triangle', 'var(--nx-danger)', null]
                    : ['Offene Schritte', $openSteps, $k['next_due'] ? 'nächster ' . $duePhrase($k['next_due']) : 'nichts terminiert', 'heroicon-o-list-bullet', 'var(--nx-tone-amber)', null],
                $finance
                    ? ['Offene Rechnungen', $money($finance['summary']['open'], $finance['currency']), $finance['summary']['overdue'] > 0 ? $money($finance['summary']['overdue'], $finance['currency']) . ' überfällig' : $money($finance['summary']['paid'], $finance['currency']) . ' bezahlt', 'heroicon-o-document-text', $finance['summary']['overdue'] > 0 ? 'var(--nx-danger)' : 'var(--nx-success)', $pageLink('rechnungen')]
                    : ['Abgeschlossen', $doneCount, $doneCount === 1 ? 'Projekt im Archiv' : 'Projekte im Archiv', 'heroicon-o-archive-box', 'var(--nx-success)', null],
            ])) as [$label, $value, $hint, $icon, $accent, $href])
                @php
                    $kTag = $href ? 'a' : 'div';
                @endphp
                <{{ $kTag }} @if ($href) href="{{ $href }}" wire:navigate @endif @class(['gp-card p-4', 'col-span-2 lg:col-span-1' => $loop->last, 'transition-colors hover:bg-[color:var(--nx-hover)]' => $href])>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-[color:var(--nx-muted)]">{{ $label }}</span>
                        <span style="color: {{ $accent }}">@svg($icon, 'w-4 h-4')</span>
                    </div>
                    <div class="mt-1.5 truncate text-2xl font-semibold tabular-nums tracking-tight">{{ $value }}</div>
                    @if ($hint)<div class="mt-0.5 truncate text-xs text-[color:var(--nx-faint)]">{{ $hint }}</div>@endif
                </{{ $kTag }}>
            @endforeach
        </div>
    @endif

    {{-- Wirkung: Kurzwerte der Module (Website, Sichtbarkeit, Social …) --}}
    @if (! empty($data['impact']))
        <section class="mt-10">
            <h2 class="gp-h2 mb-3">Wirkung</h2>
            <div class="space-y-3">
                @foreach ($data['impact'] as $g)
                    @php
                        $gHref = $g['has_section'] ? $pageLink($g['slug']) : (($g['has_detail'] && count($g['items']) === 1) ? $pageLink($g['slug'], $g['items'][0]['id']) : null);
                        $toneVar = ['success' => 'var(--nx-success)', 'warning' => 'var(--nx-warning)', 'danger' => 'var(--nx-danger)', 'info' => 'var(--nx-info)'];
                    @endphp
                    <div class="gp-card p-4">
                        <div class="mb-3 flex items-center gap-2">
                            @svg('heroicon-o-' . $g['icon'], 'w-4 h-4 text-[color:var(--nx-faint)]')
                            <span class="text-sm font-medium">{{ $g['summary']['title'] ?? $g['label'] }}</span>
                            @if (! empty($g['summary']['status']))
                                <span class="text-xs" style="color: {{ $toneVar[$g['summary']['status']['tone'] ?? ''] ?? 'var(--nx-faint)' }}">{{ $g['summary']['status']['text'] }}</span>
                            @endif
                            @if ($gHref)
                                <a href="{{ $gHref }}" wire:navigate class="ml-auto inline-flex items-center gap-1 text-sm text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Details @svg('heroicon-o-arrow-right', 'w-3.5 h-3.5')</a>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 lg:grid-cols-6">
                            @foreach ($g['summary']['kpis'] as $kpi)
                                <div class="min-w-0">
                                    <div class="truncate text-xs text-[color:var(--nx-muted)]">{{ $kpi['label'] }}</div>
                                    <div class="mt-0.5 truncate text-lg font-semibold tabular-nums tracking-tight" @if (! empty($kpi['tone'])) style="color: {{ $toneVar[$kpi['tone']] ?? 'inherit' }}" @endif>{{ $kpi['value'] }}</div>
                                    @if (! empty($kpi['hint']))<div class="truncate text-[11px] text-[color:var(--nx-faint)]">{{ $kpi['hint'] }}</div>@endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Projekte: laufend/ruhend, darunter das Archiv --}}
    @foreach ($data['featured'] as $group)
        @php
            [$archived, $running] = collect($group['items'])->partition(fn ($i) => ($i['state'] ?? '') === 'done');
        @endphp
        <section class="mt-10">
            <div class="mb-3 flex items-baseline gap-2">
                <h2 class="gp-h2">{{ $group['label'] }}</h2>
                <span class="text-sm text-[color:var(--nx-faint)]">{{ $running->count() }} laufend</span>
            </div>
            @php
                // Verortung im Baum: je Knoten eine Gruppe, in Baum-Reihenfolge
                $areas = $running->groupBy('area_id')->sortBy(fn ($g) => $g->first()['area_pos'] ?? 0);
                $showAreas = $areas->count() > 1 || ($areas->keys()->first() ?? $entity->id) != $entity->id;
            @endphp
            @foreach ($areas as $areaItems)
                @php
                    $first = $areaItems->first();
                @endphp
                @if ($showAreas)
                    <div @class(['mb-2 flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs', 'mt-5' => ! $loop->first])>
                        @svg('heroicon-o-map-pin', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                        <span @class(['text-[color:var(--nx-faint)]' => ! empty($first['area_path']), 'font-medium text-[color:var(--nx-text)]' => empty($first['area_path'])])>{{ $entity->name }}</span>
                        @foreach ($first['area_path'] ?? [] as $seg)
                            @svg('heroicon-m-chevron-right', 'w-3 h-3 shrink-0 text-[color:var(--nx-faint)]')
                            <span @class(['font-medium text-[color:var(--nx-text)]' => $loop->last, 'text-[color:var(--nx-faint)]' => ! $loop->last])>{{ $seg }}</span>
                        @endforeach
                        @if (! empty($first['area_type']) && ! empty($first['area_path']))
                            <span class="ml-1 rounded-full bg-[color:var(--nx-hover)] px-1.5 py-px text-[10px] text-[color:var(--nx-muted)]">{{ $first['area_type'] }}</span>
                        @endif
                        <span class="text-[color:var(--nx-faint)]">· {{ $areaItems->count() }}</span>
                    </div>
                @endif
                <div class="gp-card overflow-hidden divide-y divide-[color:var(--nx-line)]">
                    @foreach ($areaItems as $item)
                        @php
                            $itemPct = $pct($item['progress'] ?? null);
                            $nextStep = ($item['next'] ?? [])[0] ?? null;
                            $isPaused = ($item['state'] ?? '') === 'paused';
                            $tag = $item['has_detail'] ? 'a' : 'div';
                        @endphp
                        <{{ $tag }} @if ($item['has_detail']) href="{{ $link($group['slug'], $item['id']) }}" wire:navigate @endif wire:key="ov-{{ $group['slug'] }}-{{ $item['id'] }}"
                            @class(['group flex items-center gap-4 px-4 py-4', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $item['has_detail']])>
                            {!! $tile($item['title'], $group['slug'] . $item['id'], 36) !!}
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="truncate font-medium">{{ $item['title'] }}</span>
                                    <x-nx-badge :variant="$badge[$item['state'] ?? 'active'] ?? 'neutral'" dot>{{ $item['state_label'] ?? 'Läuft' }}</x-nx-badge>
                                </div>
                                <div class="mt-1 flex min-w-0 items-center gap-1.5 text-xs text-[color:var(--nx-muted)]">
                                    @if ($isPaused && ! empty($item['reason']))
                                        @svg('heroicon-o-pause-circle', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-warning)]')
                                        <span class="truncate">{{ $item['reason'] }}</span>
                                    @elseif ($nextStep)
                                        @svg('heroicon-o-arrow-right-circle', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                                        <span class="truncate">{{ $nextStep['title'] }}</span>
                                        @if (! empty($nextStep['due']))<span class="shrink-0 text-[color:var(--nx-faint)]">· {{ $duePhrase($nextStep['due']) }}</span>@endif
                                    @else
                                        <span class="truncate">{{ ! empty($item['updated_at']) ? 'Zuletzt bewegt ' . $ago($item['updated_at']) : '' }}</span>
                                    @endif
                                </div>
                            </div>
                            @if (! empty($item['minutes']))
                                <span class="hidden shrink-0 items-center gap-1 text-xs tabular-nums text-[color:var(--nx-muted)] md:inline-flex" title="Geleistete Zeit{{ ! empty($item['planned_minutes']) ? ' von ' . $hours($item['planned_minutes']) . ' geplant' : '' }}">
                                    @svg('heroicon-o-clock', 'w-3.5 h-3.5 text-[color:var(--nx-faint)]'){{ $hours($item['minutes']) }}@if (! empty($item['planned_minutes']))<span class="text-[color:var(--nx-faint)]">/ {{ $hours($item['planned_minutes']) }}</span>@endif
                                </span>
                            @endif
                            @if ($itemPct !== null)
                                <div class="hidden w-40 shrink-0 sm:block">
                                    <div class="flex items-baseline justify-between text-xs">
                                        <span class="font-medium tabular-nums">{{ $itemPct }} %</span>
                                        <span class="tabular-nums text-[color:var(--nx-faint)]">{{ $item['progress']['done'] }}/{{ $item['progress']['total'] }}</span>
                                    </div>
                                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                                        <div class="h-full rounded-full" style="width: {{ $itemPct }}%; background: {{ $isPaused ? 'var(--nx-warning)' : 'var(--nx-info)' }}"></div>
                                    </div>
                                </div>
                                <span class="sm:hidden">{!! $ring($itemPct, 28) !!}</span>
                            @endif
                            @if ($item['has_detail'])
                                @svg('heroicon-o-chevron-right', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)] transition-transform group-hover:translate-x-0.5 group-hover:text-[color:var(--nx-text)]')
                            @endif
                        </{{ $tag }}>
                    @endforeach
                </div>
            @endforeach
            @if ($running->isEmpty())
                <div class="rounded-[12px] border border-dashed border-[color:var(--nx-line-strong)] px-4 py-6 text-center text-sm text-[color:var(--nx-faint)]">Gerade läuft nichts — abgeschlossene finden Sie im Archiv.</div>
            @endif

            @if ($archived->isNotEmpty())
                <div x-data="{ open: {{ $running->isEmpty() ? 'true' : 'false' }} }" class="mt-3">
                    <button type="button" @click="open = ! open" class="flex w-full items-center gap-2 rounded-[8px] px-1 py-1.5 text-sm text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">
                        <span class="inline-flex transition-transform" :style="open && 'transform: rotate(90deg)'">@svg('heroicon-m-chevron-right', 'w-4 h-4')</span>
                        <span class="font-medium">Abgeschlossen</span>
                        <span class="text-[color:var(--nx-faint)]">{{ $archived->count() }} {{ $archived->count() === 1 ? 'Projekt' : 'Projekte' }}{{ $archived->sum('minutes') ? ' · ' . $hours((int) $archived->sum('minutes')) : '' }}</span>
                    </button>
                    <div x-show="open" @if ($running->isNotEmpty()) style="display: none" @endif class="gp-card mt-1 overflow-hidden divide-y divide-[color:var(--nx-line)]">
                        @foreach ($archived->sortByDesc(fn ($i) => optional($i['state_since'] ?? null)->timestamp ?? 0) as $item)
                            @php
                                $tag = $item['has_detail'] ? 'a' : 'div';
                            @endphp
                            <{{ $tag }} @if ($item['has_detail']) href="{{ $link($group['slug'], $item['id']) }}" wire:navigate @endif wire:key="ova-{{ $group['slug'] }}-{{ $item['id'] }}"
                                @class(['group flex items-center gap-3 px-4 py-3', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $item['has_detail']])>
                                @svg('heroicon-s-check-circle', 'w-5 h-5 shrink-0 text-[color:var(--nx-success)]')
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium">{{ $item['title'] }}</div>
                                    <div class="truncate text-xs text-[color:var(--nx-faint)]">
                                        {{ collect([
                                            ! empty($item['started_at']) && ! empty($item['state_since']) ? $de($item['started_at'], 'MMM YYYY') . ' – ' . $de($item['state_since'], 'MMM YYYY') : (! empty($item['state_since']) ? 'abgeschlossen ' . $de($item['state_since'], 'D. MMMM YYYY') : null),
                                            ! empty($item['progress']['total']) ? $item['progress']['done'] . ' Schritte' : null,
                                            ! empty($item['minutes']) ? $hours($item['minutes']) : null,
                                            $item['area'] ?? null,
                                        ])->filter()->implode(' · ') }}
                                    </div>
                                </div>
                                @if ($item['has_detail'])
                                    @svg('heroicon-o-chevron-right', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)] group-hover:text-[color:var(--nx-text)]')
                                @endif
                            </{{ $tag }}>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endforeach

    {{-- Aktivität und Als Nächstes --}}
    @if ($data['activity'] || $data['upcoming'])
        <div class="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-5">
            <section class="lg:col-span-3">
                <div class="mb-3 flex items-baseline justify-between">
                    <h2 class="gp-h2">Aktivität</h2>
                    @if (count($data['activity']) > 12)
                        <a href="{{ $pageLink('aktivitaet') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alles @svg('heroicon-o-arrow-right', 'w-3.5 h-3.5')</a>
                    @endif
                </div>
                @include('organization::livewire.share._activity', ['events' => $data['activity'], 'limit' => 12])
            </section>

            <section class="lg:col-span-2">
                <h2 class="gp-h2 mb-3">Als Nächstes</h2>
                @if ($data['upcoming'])
                    <div class="gp-card overflow-hidden divide-y divide-[color:var(--nx-line)]">
                        @foreach ($data['upcoming'] as $step)
                            @php
                                $overdue = ! empty($step['due']) && $step['due']->isPast() && ! $step['due']->isToday();
                                $soon = ! empty($step['due']) && ! $overdue && $step['due']->lessThan(now()->addDays(3));
                                $stepTag = $step['linkable'] ? 'a' : 'div';
                            @endphp
                            <{{ $stepTag }} @if ($step['linkable']) href="{{ $link($step['slug'], $step['parent_id']) }}" wire:navigate @endif
                                @class(['flex items-center gap-3 px-4 py-3', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $step['linkable']])>
                                <span class="h-4 w-4 shrink-0 rounded-full border-[1.5px] border-[color:var(--nx-line-strong)]"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm">{{ $step['title'] }}</div>
                                    <div class="truncate text-xs text-[color:var(--nx-faint)]">{{ $step['parent'] }}</div>
                                </div>
                                @if (! empty($step['due']))
                                    <span @class(['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                                  'bg-[rgba(224,49,49,.1)] text-[color:var(--nx-danger)]' => $overdue,
                                                  'bg-[rgba(232,89,12,.1)] text-[color:var(--nx-warning)]' => $soon,
                                                  'bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)]' => ! $overdue && ! $soon])>{{ $duePhrase($step['due']) }}</span>
                                @endif
                            </{{ $stepTag }}>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-[12px] border border-dashed border-[color:var(--nx-line-strong)] px-4 py-8 text-center text-sm text-[color:var(--nx-faint)]">Gerade nichts geplant</div>
                @endif
            </section>
        </div>
    @endif

    {{-- Leistung --}}
    @if ($work && $work['entry_count'])
        <section class="mt-10">
            <div class="flex items-baseline justify-between">
                <h2 class="gp-h2">Leistung</h2>
                <a href="{{ $pageLink('leistung') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle Buchungen @svg('heroicon-o-arrow-right', 'w-3.5 h-3.5')</a>
            </div>
            <div class="-mt-3">@include('organization::livewire.share._work', ['work' => $work, 'compact' => true])</div>
        </section>
    @endif

    {{-- Rechnungen --}}
    @if ($finance)
        <section class="mt-10">
            <div class="flex items-baseline justify-between">
                <h2 class="gp-h2">Rechnungen &amp; Zahlungen</h2>
                <a href="{{ $pageLink('rechnungen') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle Belege @svg('heroicon-o-arrow-right', 'w-3.5 h-3.5')</a>
            </div>
            <div class="-mt-3">@include('organization::livewire.share._finance', ['finance' => $finance, 'compact' => true])</div>
        </section>
    @endif

    {{-- Weitere Inhalte --}}
    @foreach (collect($data['sections'])->where('nav_mode', '!=', 'section')->where('group', 'arbeit') as $group)
        <section class="mt-10">
            <div class="mb-3 flex items-baseline gap-2">
                <h2 class="text-[15px] font-semibold">{{ $group['label'] }}</h2>
                <span class="text-sm text-[color:var(--nx-faint)]">{{ count($group['items']) }}</span>
            </div>
            <div class="overflow-hidden rounded-[12px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                @foreach ($group['items'] as $item)
                    @php
                        $rowTag = $item['has_detail'] ? 'a' : 'div';
                    @endphp
                    <{{ $rowTag }} @if ($item['has_detail']) href="{{ $link($group['slug'], $item['id']) }}" wire:navigate @endif wire:key="sec-{{ $group['slug'] }}-{{ $item['id'] }}"
                        @class(['flex items-center gap-3 px-4 py-3 text-sm', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $item['has_detail']])>
                        @svg('heroicon-o-' . $group['icon'], 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                        <span class="min-w-0 flex-1 truncate">{{ $item['title'] }}</span>
                        @if (! empty($item['area']))<span class="hidden shrink-0 text-xs text-[color:var(--nx-faint)] sm:inline">{{ $item['area'] }}</span>@endif
                        <x-nx-badge :variant="$badge[$item['state'] ?? 'active'] ?? 'neutral'">{{ $item['state_label'] ?? '' }}</x-nx-badge>
                    </{{ $rowTag }}>
                @endforeach
            </div>
        </section>
    @endforeach

    @if (! $data['featured'] && ! $data['sections'] && ! $work && ! $finance)
        <div class="mt-10 rounded-[12px] border border-dashed border-[color:var(--nx-line-strong)] px-6 py-16 text-center">
            <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-[color:var(--nx-bg)]">@svg('heroicon-o-sparkles', 'w-6 h-6 text-[color:var(--nx-faint)]')</span>
            <div class="mt-3 font-semibold">Hier entsteht gerade etwas</div>
            <div class="mt-1 text-sm text-[color:var(--nx-muted)]">Sobald es Neuigkeiten gibt, sehen Sie sie an dieser Stelle.</div>
        </div>
    @endif
</div>
