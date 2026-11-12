{{--
    Lage der Organisation — oberster Block der Übersicht. Erwartet $lage (Dashboard::orgLage), $lagePeriod.
    Satz der Perspektive, darunter die Einheiten nach Art mit ihren Sätzen, daneben die stärksten Bewegungen.
--}}
@php
    $writer = resolve(\Platform\Organization\Services\Story\EntityStoryWriter::class);
    $h = function ($min) {
        $v = round($min / 60, 1);
        return str_replace('.', ',', (string) ($v == (int) $v ? (int) $v : $v)) . ' h';
    };
    $num = fn ($v, $unit) => $unit === 'minutes' ? $h($v) : (is_float($v) && $v != (int) $v ? number_format($v, 1, ',', '.') : number_format((int) $v, 0, ',', '.'));
    $periods = ['week' => 'Woche', 'month' => 'Monat', 'quarter' => 'Quartal'];
    $root = $lage['root'] ?? null;
    $f = $lage['facts'] ?? null;
    $maxMin = max(1, collect($lage['groups'] ?? [])->flatMap(fn ($g) => $g['active'])->max('minutes') ?? 1);
@endphp

@if ($root)
    <section class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="max-w-3xl flex-1">
                <div class="mb-1 text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Lage · {{ $root->name }}</div>
                @if ($lage['story'] ?? null)
                    <p class="m-0 text-xl leading-relaxed text-[color:var(--nx-text)]">{{ $lage['story'] }}</p>
                    <p class="mb-0 mt-2 text-xs text-[color:var(--nx-faint)]">
                        {{ ucfirst($f['period']['label']) }} · Stand {{ \Carbon\Carbon::parse($lage['as_of'])->format('d.m.') }}
                        · {{ $lage['source'] === 'llm' ? 'formuliert von KI aus den Zahlen' : 'aus den Zahlen' }}
                        · <a href="{{ route('organization.entities.show', $root) }}" wire:navigate class="hover:underline">Lage von {{ $root->name }}</a>
                    </p>
                @else
                    <p class="m-0 text-base text-[color:var(--nx-muted)]">Für diesen Zeitraum ist noch keine Lage geschrieben. Sie entsteht jeden Morgen um 08:20 (Woche täglich, Monat und Quartal montags).</p>
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
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                {{-- Einheiten nach Art --}}
                <div class="min-w-0 space-y-4 lg:col-span-2">
                    @foreach ($lage['groups'] as $g)
                        @continue(empty($g['active']) && ! $g['quiet'])
                        <div>
                            <div class="mb-2 flex items-baseline gap-2 text-xs">
                                <span class="font-medium text-[color:var(--nx-muted)]">{{ $g['type'] }}</span>
                                @if ($g['quiet'] || $g['mute'])
                                    <span class="text-[color:var(--nx-faint)]">
                                        {{ collect([$g['quiet'] ? $g['quiet'] . ' ruhig' : null, $g['mute'] ? $g['mute'] . ' ohne Daten' : null])->filter()->implode(' · ') }}
                                    </span>
                                @endif
                            </div>
                            @if (! empty($g['active']))
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @foreach ($g['active'] as $c)
                                        <a href="{{ route('organization.entities.show', $c['id']) }}" wire:navigate
                                           class="block rounded-[8px] border border-[color:var(--nx-line)] p-3 transition-colors hover:bg-[color:var(--nx-hover)]">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span class="truncate text-sm font-medium text-[color:var(--nx-text)]">{{ $c['name'] }}</span>
                                                <span class="shrink-0 text-xs tabular-nums text-[color:var(--nx-muted)]">
                                                    @if ($c['minutes'] > 0) {{ $h($c['minutes']) }} @endif
                                                    @if ($c['good']) <span style="color: var(--nx-success)">▲{{ $c['good'] }}</span> @endif
                                                    @if ($c['bad']) <span style="color: var(--nx-danger)">▼{{ $c['bad'] }}</span> @endif
                                                </span>
                                            </div>
                                            {{-- Wärme: gebuchte Zeit relativ zur aktivsten Einheit --}}
                                            <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-[color:var(--nx-accent-soft)]">
                                                <div class="h-full rounded-full" style="width: {{ max(2, round(100 * $c['minutes'] / $maxMin)) }}%; background: var(--nx-warning)"></div>
                                            </div>
                                            @if ($c['story'])
                                                <p class="mb-0 mt-2 text-xs leading-relaxed text-[color:var(--nx-muted)] line-clamp-3">{{ $c['story'] }}</p>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Bewegungen quer über die Einheiten --}}
                <div class="min-w-0">
                    <x-nx-panel title="Was sich bewegt" subtitle="stärkste Veränderungen darunter">
                        @php $anyMove = ! empty($lage['moves']['good']) || ! empty($lage['moves']['bad']); @endphp
                        @if ($anyMove)
                            <div class="space-y-4">
                                @foreach (['good' => ['Gut bewegt', 'var(--nx-success)'], 'bad' => ['Zu beobachten', 'var(--nx-danger)']] as $key => [$label, $color])
                                    @continue(empty($lage['moves'][$key]))
                                    <div>
                                        <div class="mb-1 text-xs font-medium" style="color: {{ $color }}">{{ $label }}</div>
                                        @foreach ($lage['moves'][$key] as $m)
                                            <a href="{{ route('organization.entities.show', $m['entity_id']) }}" wire:navigate class="flex items-baseline justify-between gap-3 rounded-[4px] py-1 text-sm hover:bg-[color:var(--nx-hover)]">
                                                <span class="min-w-0">
                                                    <span class="text-[color:var(--nx-text)]">{{ $writer->plain($m['label']) }}</span>
                                                    <span class="block truncate text-xs text-[color:var(--nx-faint)]">{{ $m['entity'] }}</span>
                                                </span>
                                                <span class="shrink-0 tabular-nums text-xs">
                                                    @if (($m['flow'] ?? null) !== null)
                                                        <span class="font-semibold text-[color:var(--nx-text)]">{{ $num($m['flow'], $m['unit']) }}</span>
                                                    @else
                                                        <span class="font-semibold text-[color:var(--nx-text)]">{{ $num($m['current'], $m['unit']) }}</span>
                                                        <span style="color: {{ $color }}">{{ ($m['delta'] > 0 ? '+' : '−') . $num(abs($m['delta']), $m['unit']) }}</span>
                                                    @endif
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="m-0 text-sm text-[color:var(--nx-muted)]">Keine Bewegung im Zeitraum.</p>
                        @endif
                    </x-nx-panel>
                </div>
            </div>
        @endif
    </section>
@endif
