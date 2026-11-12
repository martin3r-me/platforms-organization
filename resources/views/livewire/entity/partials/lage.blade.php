{{--
    Lage — Startansicht am Knoten: ein Satz (täglicher Lauf organization:entity-stories),
    darunter was ihn trägt: Bewegung, gebuchte Zeit, Untereinheiten, offene Signale.
    Erwartet $lage (Show::lage) und $lagePeriod.
--}}
@php
    $writer = resolve(\Platform\Organization\Services\Story\EntityStoryWriter::class);
    $f = $lage['facts'] ?? null;
    $h = fn ($min) => str_replace('.', ',', (string) (round($min / 60, 1) == (int) round($min / 60, 1) ? (int) round($min / 60, 1) : round($min / 60, 1))) . ' h';
    $num = fn ($v, $unit) => $unit === 'minutes' ? $h($v) : (is_float($v) && $v != (int) $v ? number_format($v, 1, ',', '.') : number_format((int) $v, 0, ',', '.'));
    $signed = fn ($v, $unit) => ($v > 0 ? '+' : '−') . $num(abs($v), $unit);
    $periods = ['week' => 'Woche', 'month' => 'Monat', 'quarter' => 'Quartal'];
    $vorperiode = ['week' => 'Vorwoche', 'month' => '30 Tage davor', 'quarter' => 'gleich lang davor'][$lagePeriod];
    $groups = [
        'good' => ['Gut bewegt', 'var(--nx-success)'],
        'bad' => ['Zu beobachten', 'var(--nx-danger)'],
        'neutral' => ['Sonst bewegt', 'var(--nx-muted)'],
    ];
@endphp

<div class="space-y-5">
    {{-- Satz + Zeitraum --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="max-w-3xl flex-1">
            @if ($lage['error'] ?? false)
                <p class="m-0 text-sm text-[color:var(--nx-muted)]">Die Lage ließ sich gerade nicht berechnen.</p>
            @else
                <p class="m-0 text-lg leading-relaxed text-[color:var(--nx-text)]">{{ $lage['story'] }}</p>
                <p class="mb-0 mt-2 text-xs text-[color:var(--nx-faint)]">
                    {{ ucfirst($f['period']['label']) }}
                    @if ($lage['as_of']) · Stand {{ \Carbon\Carbon::parse($lage['as_of'])->format('d.m.') }} @endif
                    · {{ match ($lage['source']) { 'llm' => 'formuliert von KI aus den Zahlen', 'live' => 'live gerechnet', default => 'aus den Zahlen' } }}
                </p>
            @endif
        </div>
        <div class="inline-flex shrink-0 rounded-[6px] border border-[color:var(--nx-line)] p-0.5 text-xs">
            @foreach ($periods as $key => $label)
                <button type="button" wire:click="setLagePeriod('{{ $key }}')"
                    class="rounded-[4px] px-2.5 py-1 {{ $lagePeriod === $key ? 'bg-[color:var(--nx-accent-soft)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>
    <div wire:loading.delay wire:target="setLagePeriod" class="text-xs text-[color:var(--nx-faint)]">Lädt…</div>

    @if ($f)
        @if ($f['coverage']['expanded'] ?? false)
            <x-nx-callout variant="info">Im Zeitraum ist die Erfassung gewachsen — Sprünge bei Ständen zeigen auch, was neu angebunden wurde, nicht nur neue Arbeit.</x-nx-callout>
        @endif

        <div class="grid gap-5 lg:grid-cols-2">
            {{-- Bewegung --}}
            <x-nx-panel title="Was sich bewegt hat" :subtitle="'gegenüber ' . $vorperiode">
                @php $anyMove = collect($f['movers'])->flatten(1)->isNotEmpty(); @endphp
                @if ($anyMove)
                    <div class="space-y-4">
                        @foreach ($groups as $g => [$gLabel, $gColor])
                            @continue(empty($f['movers'][$g]))
                            <div>
                                <div class="mb-1 text-xs font-medium" style="color: {{ $gColor }}">{{ $gLabel }}</div>
                                @foreach ($f['movers'][$g] as $m)
                                    <div class="flex items-baseline justify-between gap-3 py-1 text-sm">
                                        <span class="text-[color:var(--nx-text)]">{{ $writer->plain($m['label']) }}</span>
                                        <span class="shrink-0 tabular-nums">
                                            @if (($m['flow'] ?? null) !== null)
                                                <span class="font-semibold text-[color:var(--nx-text)]">{{ $num($m['flow'], $m['unit']) }}</span>
                                                <span class="text-xs text-[color:var(--nx-faint)]">im Zeitraum</span>
                                            @else
                                                <span class="font-semibold text-[color:var(--nx-text)]">{{ $num($m['current'], $m['unit']) }}</span>
                                                <span class="text-xs" style="color: {{ $gColor }}">{{ $signed($m['delta'], $m['unit']) }}</span>
                                            @endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="m-0 text-sm text-[color:var(--nx-muted)]">Keine Bewegung im Zeitraum.</p>
                @endif
            </x-nx-panel>

            {{-- Zeit --}}
            @php $e = $f['energy']; @endphp
            <x-nx-panel title="Wo die Zeit hinging" :subtitle="$e['minutes'] > 0 ? $h($e['minutes']) . ' gebucht · ' . $vorperiode . ' ' . $h($e['previous_minutes']) . ($e['agent_minutes'] > 0 ? ' · ' . $h($e['agent_minutes']) . ' Agenten' : '') : null">
                @if ($e['minutes'] > 0)
                    @php $maxCtx = max(1, collect($e['contexts'])->max('minutes')); @endphp
                    <div class="space-y-1.5">
                        @foreach ($e['contexts'] as $c)
                            @php $ctx = $writer->context($c['label']); @endphp
                            <div class="text-sm">
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="truncate text-[color:var(--nx-text)]">{{ $writer->name($ctx['was']) ?: '—' }}
                                        @if (! empty($ctx['kunde'])) <span class="text-xs text-[color:var(--nx-faint)]">{{ $ctx['kunde'] }}</span> @endif
                                    </span>
                                    <span class="shrink-0 tabular-nums text-xs text-[color:var(--nx-muted)]">{{ $h($c['minutes']) }}</span>
                                </div>
                                <div class="mt-0.5 h-1 overflow-hidden rounded-full bg-[color:var(--nx-accent-soft)]">
                                    <div class="h-full rounded-full bg-[color:var(--nx-muted)]" style="width: {{ round(100 * $c['minutes'] / $maxCtx) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if (! empty($e['people']))
                        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[color:var(--nx-muted)]">
                            @foreach ($e['people'] as $p)
                                <span><span class="text-[color:var(--nx-text)]">{{ $writer->name($p['name']) ?? 'Unbekannt' }}</span>@if ($p['agent']) <x-nx-badge variant="accent">Agent</x-nx-badge>@endif {{ $h($p['minutes']) }}</span>
                            @endforeach
                        </div>
                    @endif
                @else
                    <p class="m-0 text-sm text-[color:var(--nx-muted)]">
                        Nichts gebucht.
                        @if ($f['stillness']['days_since_last_booking'] !== null) Letzte Buchung vor {{ $f['stillness']['days_since_last_booking'] }} Tagen. @endif
                    </p>
                @endif
            </x-nx-panel>
        </div>

        {{-- Untereinheiten --}}
        @if (! empty($f['children']))
            <x-nx-panel flush title="Darunter" :subtitle="count($f['children']) . ' Einheiten, die bewegtesten zuerst'">
                <div class="divide-y divide-[color:var(--nx-line)]">
                    @foreach ($f['children'] as $c)
                        <a href="{{ route('organization.entities.show', $c['id']) }}" wire:navigate class="block px-4 py-3 hover:bg-[color:var(--nx-hover)]">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $c['name'] }}</span>
                                <span class="shrink-0 text-xs tabular-nums text-[color:var(--nx-muted)]">
                                    @if ($c['mute']) nichts angebunden
                                    @elseif ($c['quiet']) ruhig
                                    @else
                                        {{ $c['minutes'] > 0 ? $h($c['minutes']) : '' }}
                                        @if ($c['good']) <span style="color: var(--nx-success)">▲{{ $c['good'] }}</span> @endif
                                        @if ($c['bad']) <span style="color: var(--nx-danger)">▼{{ $c['bad'] }}</span> @endif
                                    @endif
                                </span>
                            </div>
                            @if (! empty($lage['child_stories'][$c['id']]) && ! $c['quiet'] && ! $c['mute'])
                                <p class="mb-0 mt-1 text-xs leading-relaxed text-[color:var(--nx-muted)] line-clamp-2">{{ $lage['child_stories'][$c['id']] }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </x-nx-panel>
        @endif

        {{-- Signale --}}
        @if ($f['signals']['open'] > 0)
            <x-nx-panel title="Offene Signale" :subtitle="$f['signals']['open'] . ' offen' . ($f['signals']['critical'] ? ', ' . $f['signals']['critical'] . ' kritisch' : '')">
                <x-slot name="actions">
                    <button type="button" x-on:click="tab = 'signals'" class="text-xs font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle Signale</button>
                </x-slot>
                <div class="space-y-1.5">
                    @foreach ($f['signals']['top'] as $s)
                        <div class="flex items-baseline gap-2 text-sm">
                            <x-nx-badge :variant="$sevVariant($s['severity'])">{{ $sevLabel($s['severity']) }}</x-nx-badge>
                            <span class="text-[color:var(--nx-text)]">{{ $s['message'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-nx-panel>
        @endif
    @endif
</div>
