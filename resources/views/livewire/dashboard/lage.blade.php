{{--
    Lage + Karte der Organisation — oberster Teil der Übersicht. Erwartet $lage (Dashboard::orgLage), $lagePeriod.
    Satz der Perspektive, darunter die Einheiten als Karte: Fläche = gebuchte Zeit, Farbe = Wärme, Rand = Richtung.
--}}
@php
    $h = function ($min) {
        $v = round($min / 60, 1);
        return str_replace('.', ',', (string) ($v == (int) $v ? (int) $v : $v)) . ' h';
    };
    $periods = ['week' => 'Woche', 'month' => 'Monat', 'quarter' => 'Quartal'];
    $root = $lage['root'] ?? null;
    $f = $lage['facts'] ?? null;
    $map = $lage['map'] ?? [];
    $maxMin = max(1, collect($map)->flatMap(fn ($g) => $g['active'])->max('minutes') ?? 1);
    // Wärme 0..1 → Hintergrund aus dem Warn-Ton; still = neutrale Fläche
    $heat = fn ($min) => $min <= 0 ? 'background: var(--nx-accent-soft)' : 'background: color-mix(in oklab, var(--nx-warning) ' . (18 + (int) round(62 * min(1, $min / $maxMin))) . '%, transparent)';
    $edge = fn ($c) => $c['bad'] > $c['good'] ? 'box-shadow: inset 0 -3px 0 var(--nx-danger)' : ($c['good'] > $c['bad'] ? 'box-shadow: inset 0 -3px 0 var(--nx-success)' : '');
@endphp

@if ($root)
    <section class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 max-w-3xl flex-1">
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

        @if ($map !== [])
            <div>
                <div class="flex flex-col gap-1.5 sm:h-64 sm:flex-row">
                    @foreach ($map as $g)
                        @php $gFlex = 1 + $g['minutes'] / 60 / 2; @endphp
                        <div class="flex min-w-0 flex-wrap gap-1 sm:flex-col sm:flex-nowrap" style="flex: {{ round($gFlex, 2) }} 1 0">
                            <div class="w-full truncate text-[10px] font-medium uppercase tracking-wide text-[color:var(--nx-muted)] sm:w-auto" title="{{ $g['type'] }}">{{ $g['type'] }}</div>
                            @foreach ($g['active'] as $c)
                                <a href="{{ route('organization.entities.show', $c['id']) }}" wire:navigate
                                   title="{{ $c['story'] ?? $c['name'] }}"
                                   class="flex min-h-[44px] min-w-[30%] flex-1 flex-col justify-between overflow-hidden rounded-[6px] px-2 py-1.5 text-[color:var(--nx-text)] transition-opacity hover:opacity-80 sm:min-h-0 sm:min-w-0"
                                   style="flex-grow: {{ round(max(0.7, $c['minutes'] / 60), 2) }}; {{ $heat($c['minutes']) }}; {{ $edge($c) }}">
                                    <span class="truncate text-xs font-semibold leading-tight">{{ $c['name'] }}</span>
                                    <span class="truncate text-[11px] tabular-nums opacity-80">
                                        {{ $c['minutes'] > 0 ? $h($c['minutes']) : 'keine Zeit' }}
                                        @if ($c['good']) ▲{{ $c['good'] }} @endif
                                        @if ($c['bad']) ▼{{ $c['bad'] }} @endif
                                    </span>
                                </a>
                            @endforeach
                            @if ($g['quiet'] !== [])
                                <div class="flex min-h-[44px] min-w-[30%] flex-1 flex-col justify-between overflow-hidden rounded-[6px] px-2 py-1.5 text-[color:var(--nx-muted)] sm:min-h-0 sm:min-w-0"
                                     style="flex-grow: 0.7; background: var(--nx-accent-soft)" title="{{ implode(', ', $g['quiet']) }}">
                                    <span class="truncate text-xs font-semibold leading-tight">{{ count($g['quiet']) }} ruhig</span>
                                    <span class="truncate text-[11px]">{{ implode(', ', array_slice($g['quiet'], 0, 3)) }}{{ count($g['quiet']) > 3 ? ' …' : '' }}</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-[color:var(--nx-muted)]">
                    <span>Fläche = gebuchte Zeit</span>
                    <span><span class="mr-1 inline-block h-2 w-2 rounded-[2px]" style="background: color-mix(in oklab, var(--nx-warning) 70%, transparent)"></span>warm</span>
                    <span><span class="mr-1 inline-block h-2 w-2 rounded-[2px] bg-[color:var(--nx-accent-soft)]"></span>still</span>
                    <span><span class="mr-1 inline-block h-2 w-2 rounded-[2px]" style="background: var(--nx-danger)"></span>mehr Kritisches als Gutes</span>
                    <span><span class="mr-1 inline-block h-2 w-2 rounded-[2px]" style="background: var(--nx-success)"></span>mehr Gutes</span>
                    @if (($lage['observed'] ?? 0) > 0)
                        <span class="sm:ml-auto">dazu {{ $lage['observed'] }} Externe und Software</span>
                    @endif
                </div>
            </div>
        @endif
    </section>
@endif
