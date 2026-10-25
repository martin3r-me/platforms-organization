{{-- Kunden-Tor · Leistung: gebuchte Zeiten mit Personen und Notizen, ohne Beträge.
     Daten: $work (EntityShareService::workData()), $compact (Übersicht: ohne Einzelbuchungen). --}}
@php
    $maxMonth = max(1, collect($work['months'])->max('minutes'));
    $planned = $work['planned_minutes'] ?? 0;
    $usedPct = $planned > 0 ? (int) round($work['total_minutes'] / $planned * 100) : null;
    $delta = $work['month_minutes'] - $work['prev_month_minutes'];
    $maxPerson = max(1, collect($work['people'])->max('minutes'));
    $weeks = collect($work['entries'])->groupBy(fn ($e) => $e['date']->copy()->startOfWeek()->format('Y-m-d'));
@endphp
<div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="gp-card p-4">
        <div class="text-xs text-[color:var(--nx-muted)]">Geleistet gesamt</div>
        <div class="mt-1.5 text-2xl font-semibold tabular-nums tracking-tight">{{ $hours($work['total_minutes']) }}</div>
        <div class="mt-0.5 text-xs text-[color:var(--nx-faint)]">{{ $work['first_date'] ? 'seit ' . $de($work['first_date'], 'MMMM YYYY') : '' }} · {{ $work['entry_count'] }} Buchungen</div>
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
        <div class="text-xs text-[color:var(--nx-muted)]">Im Team</div>
        <div class="mt-1.5 flex items-center">
            @foreach (array_slice($work['people'], 0, 5) as $p)
                <span @class(['rounded-full ring-2 ring-[color:var(--nx-surface)]', '-ml-1.5' => ! $loop->first])>{!! $person($p, 28) !!}</span>
            @endforeach
        </div>
        <div class="mt-1.5 text-xs text-[color:var(--nx-faint)]">{{ count($work['people']) }} {{ count($work['people']) === 1 ? 'Person' : 'Personen' }}</div>
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
    <div class="gp-card p-4 lg:col-span-2">
        <div class="mb-2 text-sm font-medium">Wer gearbeitet hat</div>
        <div class="space-y-2.5">
            @foreach ($work['people'] as $p)
                <div class="flex items-center gap-3">
                    {!! $person($p, 28) !!}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span class="truncate font-medium">{{ $p['name'] }}</span>
                            <span class="shrink-0 tabular-nums">{{ $hours($p['minutes']) }}</span>
                        </div>
                        <div class="mt-1 h-1 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                            <div class="h-full rounded-full bg-[color:var(--nx-tone-violet)]" style="width: {{ (int) round($p['minutes'] / $maxPerson * 100) }}%"></div>
                        </div>
                        <div class="mt-0.5 text-[11px] text-[color:var(--nx-faint)]">zuletzt {{ $dayLabel($p['last_at']) }}{{ $p['month_minutes'] ? ' · ' . $hours($p['month_minutes']) . ' diesen Monat' : '' }}</div>
                    </div>
                </div>
            @endforeach
        </div>
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
