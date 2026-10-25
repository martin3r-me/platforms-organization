{{-- Kunden-Tor · Leistung: gebuchte Zeiten mit Personen und Notizen, ohne Beträge.
     Daten: $work (EntityShareService::workData()), $compact (Übersicht: ohne Einzelbuchungen). --}}
@php
    $maxMonth = max(1, collect($work['months'])->max('minutes'));
    $planned = $work['planned_minutes'] ?? 0;
    $usedPct = $planned > 0 ? (int) round($work['total_minutes'] / $planned * 100) : null;
    $delta = $work['month_minutes'] - $work['prev_month_minutes'];
    $periodLabels = ['30' => '30 Tage', '90' => '3 Monate', '365' => '12 Monate', 'all' => 'Gesamt'];
    $activeNow = collect($work['people'])->filter(fn ($p) => $p['periods']['30'] > 0)->count();
    $weeks = collect($work['entries'])->groupBy(fn ($e) => $e['date']->copy()->startOfWeek()->format('Y-m-d'));
@endphp
<div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="gp-card p-4">
        <div class="text-xs text-[color:var(--nx-muted)]">Letzte 30 Tage</div>
        <div class="mt-1.5 text-2xl font-semibold tabular-nums tracking-tight">{{ $hours($work['period_minutes']['30']) }}</div>
        <div class="mt-0.5 text-xs text-[color:var(--nx-faint)]">{{ $activeNow ? $activeNow . ' ' . ($activeNow === 1 ? 'Person' : 'Personen') . ' aktiv' : 'gerade niemand gebucht' }}</div>
    </div>
    <div class="gp-card p-4">
        <div class="text-xs text-[color:var(--nx-muted)]">{{ $de(now(), 'MMMM') }}</div>
        <div class="mt-1.5 text-2xl font-semibold tabular-nums tracking-tight">{{ $hours($work['month_minutes']) }}</div>
        <div class="mt-0.5 text-xs text-[color:var(--nx-faint)]">Vormonat {{ $hours($work['prev_month_minutes']) }}</div>
    </div>
    @if ($usedPct !== null)
        <div class="gp-card p-4">
            <div class="text-xs text-[color:var(--nx-muted)]">Geplant</div>
            <div class="mt-1.5 text-2xl font-semibold tabular-nums tracking-tight">{{ $hours($planned) }}</div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                <div class="h-full rounded-full" style="width: {{ min(100, $usedPct) }}%; background: {{ $usedPct > 100 ? 'var(--nx-danger)' : ($usedPct > 85 ? 'var(--nx-warning)' : 'var(--nx-info)') }}"></div>
            </div>
            <div class="mt-1 text-xs text-[color:var(--nx-faint)]">{{ $usedPct }} % davon geleistet</div>
        </div>
    @else
        <div class="gp-card p-4">
            <div class="text-xs text-[color:var(--nx-muted)]">Zuletzt gearbeitet</div>
            <div class="mt-1.5 text-2xl font-semibold tracking-tight">{{ $work['last_date'] ? $dayLabel($work['last_date']) : '–' }}</div>
            <div class="mt-0.5 text-xs text-[color:var(--nx-faint)]">{{ $work['last_date'] ? $de($work['last_date'], 'D. MMMM YYYY') : '' }}</div>
        </div>
    @endif
    <div class="gp-card p-4">
        <div class="text-xs text-[color:var(--nx-muted)]">Gesamt</div>
        <div class="mt-1.5 text-2xl font-semibold tabular-nums tracking-tight">{{ $hours($work['total_minutes']) }}</div>
        <div class="mt-0.5 text-xs text-[color:var(--nx-faint)]">{{ $work['first_date'] ? 'seit ' . $de($work['first_date'], 'MMMM YYYY') . ' · ' : '' }}{{ $work['entry_count'] }} Buchungen</div>
    </div>
</div>

<div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-5">
    <div class="gp-card p-4 lg:col-span-3">
        <div class="flex items-baseline justify-between">
            <span class="text-sm font-medium">Stunden je Monat</span>
            <span class="text-xs text-[color:var(--nx-faint)]">letzte 12 Monate</span>
        </div>
        <div class="gp-bars mt-4">
            @foreach ($work['months'] as $m)
                <div title="{{ $m['long'] }}: {{ $hours($m['minutes']) }}">
                    @if ($m['minutes'] > 0 && $m['minutes'] === $maxMonth)
                        <span class="tabular-nums" style="color: var(--nx-muted)">{{ $hours($m['minutes']) }}</span>
                    @endif
                    <div @class(['gp-bar', 'is-now' => $loop->last]) style="height: {{ $m['minutes'] ? max(4, (int) round($m['minutes'] / $maxMonth * 72)) : 2 }}px"></div>
                    <span>{{ $m['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
    <div class="gp-card p-4 lg:col-span-2" x-data="{ p: '90' }">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm font-medium">Wer gearbeitet hat</span>
            <div class="inline-flex rounded-[6px] bg-[color:var(--nx-hover)] p-0.5 text-[11px]">
                @foreach ($periodLabels as $key => $label)
                    <button type="button" @click="p = '{{ $key }}'" class="rounded-[5px] px-2 py-0.5 transition-colors"
                            :style="p === '{{ $key }}' ? 'background: var(--nx-surface); color: var(--nx-text); box-shadow: 0 1px 2px rgba(0,0,0,.06)' : 'color: var(--nx-muted)'">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        @foreach ($periodLabels as $key => $label)
            @php
                [$active, $former] = collect($work['people'])->partition(fn ($x) => $x['periods'][$key] > 0);
                $active = $active->sortByDesc(fn ($x) => $x['periods'][$key])->values();
                $maxP = max(1, $active->max(fn ($x) => $x['periods'][$key]));
            @endphp
            <div x-show="p === '{{ $key }}'" @if ($key !== '90') style="display: none" @endif>
                @if ($active->isEmpty())
                    <div class="py-4 text-center text-sm text-[color:var(--nx-faint)]">In diesem Zeitraum keine Buchungen.</div>
                @endif
                <div class="space-y-2.5">
                    @foreach ($active as $person_)
                        <div class="flex items-center gap-3">
                            {!! $person($person_, 28) !!}
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2 text-sm">
                                    <span class="truncate font-medium">{{ $person_['name'] }}</span>
                                    <span class="shrink-0 tabular-nums">{{ $hours($person_['periods'][$key]) }}</span>
                                </div>
                                <div class="mt-1 h-1 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                                    <div class="h-full rounded-full bg-[color:var(--nx-tone-violet)]" style="width: {{ (int) round($person_['periods'][$key] / $maxP * 100) }}%"></div>
                                </div>
                                <div class="mt-0.5 text-[11px] text-[color:var(--nx-faint)]">zuletzt {{ $dayLabel($person_['last_at']) }} · {{ $person_['entries'] }} {{ $person_['entries'] === 1 ? 'Buchung' : 'Buchungen' }} an {{ $person_['days'] }} {{ $person_['days'] === 1 ? 'Tag' : 'Tagen' }} insgesamt</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($key !== 'all' && $former->isNotEmpty())
                    <div class="mt-3 border-t border-[color:var(--nx-line)] pt-2.5">
                        <div class="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Früher beteiligt</div>
                        @foreach ($former->sortByDesc('last_at') as $person_)
                            <div class="flex items-center gap-2 py-0.5 text-xs text-[color:var(--nx-muted)]">
                                <span class="opacity-60">{!! $person($person_, 18) !!}</span>
                                <span class="truncate">{{ $person_['name'] }}</span>
                                <span class="ml-auto shrink-0 text-[color:var(--nx-faint)]">bis {{ $de($person_['last_at'], 'MMMM YYYY') }} · {{ $hours($person_['minutes']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>

@unless ($compact)
    <div class="mt-8">
        <h2 class="gp-h2 mb-3">Alle Buchungen</h2>
        <div class="space-y-4">
            @foreach ($weeks as $week => $entries)
                @php
                    $start = \Illuminate\Support\Carbon::parse($week)->locale('de');
                @endphp
                <div class="gp-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-4 py-2 text-xs">
                        <span class="font-medium">KW {{ $start->isoWeek() }} <span class="font-normal text-[color:var(--nx-faint)]">· {{ $start->isoFormat('D. MMM') }} – {{ $start->copy()->endOfWeek()->isoFormat('D. MMM YYYY') }}</span></span>
                        <span class="tabular-nums font-medium">{{ $hours((int) collect($entries)->sum('minutes')) }}</span>
                    </div>
                    <div class="divide-y divide-[color:var(--nx-line)]">
                        @foreach ($entries as $e)
                            <div class="flex items-start gap-3 px-4 py-3">
                                <span class="mt-0.5">{!! $person(['name' => $e['person'], 'id' => $e['user_id'], 'has_avatar' => collect($work['people'])->firstWhere('id', $e['user_id'])['has_avatar'] ?? false], 26) !!}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline gap-x-2 text-sm">
                                        <span class="font-medium">{{ $e['person'] }}</span>
                                        <span class="text-xs text-[color:var(--nx-faint)]">{{ $de($e['date'], 'dd, D. MMM') }}</span>
                                    </div>
                                    @if ($e['context'] || $e['item'])
                                        <div class="mt-0.5 text-sm text-[color:var(--nx-text)]">
                                            {{ $e['context'] ?? $e['item'] }}
                                            @if ($e['item'] && $e['context'] && $e['item'] !== $e['context'])<span class="text-xs text-[color:var(--nx-faint)]">· {{ $e['item'] }}</span>@endif
                                        </div>
                                    @endif
                                    @if ($e['note'])
                                        <div class="mt-1 whitespace-pre-line text-[13px] leading-relaxed text-[color:var(--nx-muted)]">{{ $e['note'] }}</div>
                                    @endif
                                </div>
                                <span class="shrink-0 rounded-full bg-[color:var(--nx-hover)] px-2 py-0.5 text-xs font-medium tabular-nums">{{ $hours($e['minutes']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        @if ($work['entry_count'] > count($work['entries']))
            <p class="mt-3 text-xs text-[color:var(--nx-faint)]">Die {{ count($work['entries']) }} neuesten von {{ $work['entry_count'] }} Buchungen — ältere sind in den Monatssummen enthalten.</p>
        @endif
    </div>
@endunless
