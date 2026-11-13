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
                {{-- Treemap: Arten als Flächen, darin die Einheiten (Rechtecke in Prozent, siehe Dashboard::mapLayout) --}}
                <div class="relative h-72 w-full">
                    @foreach ($map as $g)
                        @php [$gx, $gy, $gw, $gh] = $g['rect']; @endphp
                        <div class="absolute flex flex-col p-0.5" style="left: {{ $gx }}%; top: {{ $gy }}%; width: {{ $gw }}%; height: {{ $gh }}%">
                            <div class="truncate px-0.5 pb-0.5 text-[10px] font-medium uppercase tracking-wide text-[color:var(--nx-muted)]" title="{{ $g['type'] }}">{{ $g['type'] }}</div>
                            <div class="relative min-h-0 flex-1">
                                @foreach ($g['cells'] as $cell)
                                    @php
                                        [$cx, $cy, $cw, $ch] = $cell['rect'];
                                        $big = $gw * $cw / 100 > 7 && $gh * $ch / 100 > 16;
                                        $pos = "left: {$cx}%; top: {$cy}%; width: {$cw}%; height: {$ch}%";
                                    @endphp
                                    @if ($cell['kind'] === 'unit')
                                        @php $c = $cell['unit']; @endphp
                                        <a href="{{ route('organization.entities.show', $c['id']) }}" wire:navigate
                                           title="{{ $c['name'] }}{{ $c['story'] ? ': ' . $c['story'] : '' }}"
                                           class="absolute p-0.5" style="{{ $pos }}">
                                            <span class="flex h-full w-full flex-col justify-between overflow-hidden rounded-[6px] px-2 py-1.5 text-[color:var(--nx-text)] transition-opacity hover:opacity-80"
                                                  style="{{ $heat($c['minutes']) }}; {{ $edge($c) }}">
                                                @if ($big)
                                                    <span class="truncate text-xs font-semibold leading-tight">{{ $c['name'] }}</span>
                                                    <span class="truncate text-[11px] tabular-nums opacity-80">
                                                        {{ $c['minutes'] > 0 ? $h($c['minutes']) : 'keine Zeit' }}
                                                        @if ($c['good']) ▲{{ $c['good'] }} @endif
                                                        @if ($c['bad']) ▼{{ $c['bad'] }} @endif
                                                    </span>
                                                @else
                                                    <span class="truncate text-[10px] font-semibold leading-tight">{{ $c['name'] }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    @else
                                        <div class="absolute p-0.5" style="{{ $pos }}" title="{{ implode(', ', $cell['names']) }}">
                                            <div class="flex h-full w-full flex-col justify-between overflow-hidden rounded-[6px] px-2 py-1.5 text-[color:var(--nx-muted)]" style="background: var(--nx-accent-soft)">
                                                <span class="truncate text-[11px] font-semibold leading-tight">{{ count($cell['names']) }} ruhig</span>
                                                @if ($big)
                                                    <span class="truncate text-[11px]">{{ implode(', ', array_slice($cell['names'], 0, 3)) }}{{ count($cell['names']) > 3 ? ' …' : '' }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
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
