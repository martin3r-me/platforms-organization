{{--
    Übersicht der Organisation — beantwortet „Wie geht es uns, und wo muss ich heute hin?"
    Ausgangspunkt ist immer die aktive Perspektive. Reihenfolge = Lesereihenfolge:
    Lage → Karte → Braucht Aufmerksamkeit / Was sich bewegt → Wer trägt / Umwelt / zuletzt.
    Leere Blöcke entfallen; was nichts aussagt, steht hier nicht.
--}}
@php
    $lage = $this->orgLage;
    $attention = $this->attention;
    $envRadar = $this->environmentRadar;
    $writer = resolve(\Platform\Organization\Services\Story\EntityStoryWriter::class);
    $f = $lage['facts'] ?? null;

    $h = function ($min) {
        $v = round($min / 60, 1);
        return str_replace('.', ',', (string) ($v == (int) $v ? (int) $v : $v)) . ' h';
    };
    $num = fn ($v, $unit) => $unit === 'minutes' ? $h($v) : (is_float($v) && $v != (int) $v ? number_format($v, 1, ',', '.') : number_format((int) $v, 0, ',', '.'));
    $sevColor = fn ($s) => match ($s) {
        'algedonic', 'critical' => 'var(--nx-danger)',
        'warning' => 'var(--nx-warning)',
        default => 'var(--nx-info)',
    };
    $vorperiode = ['week' => 'Vorwoche', 'month' => '30 Tage davor', 'quarter' => 'gleich lang davor'][$lagePeriod];

    // Umwelt: nur Quellen mit Ausschlag, sonst ein Satz
    $envMoved = collect($envRadar['sources'] ?? [])->filter(fn ($s) => ($s['delta']['sentiment_change'] ?? 0) != 0)->take(4);
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Übersicht" icon="heroicon-o-building-office" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'icon' => 'building-office'],
        ]">
            <x-nx-button size="sm" :href="route('organization.entities.index')" wire:navigate>
                @svg('heroicon-o-rectangle-stack', 'w-4 h-4')
                <span>Einheiten</span>
            </x-nx-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-6">
            @if (session()->has('message'))
                <x-nx-callout variant="success">{{ session('message') }}</x-nx-callout>
            @endif

            <div class="flex flex-wrap items-baseline gap-2">
                <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">Übersicht</h1>
                <span class="text-sm text-[color:var(--nx-muted)]">{{ now()->locale('de')->isoFormat('dddd, D. MMMM') }}</span>
            </div>

            {{-- 1 + 2: Lage und Karte --}}
            @include('organization::livewire.dashboard.lage', ['lage' => $lage])

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                {{-- 3: Braucht Aufmerksamkeit — immer der Stand von jetzt --}}
                <x-nx-panel flush title="Braucht Aufmerksamkeit" :subtitle="$attention['open_total'] > 0 ? $attention['open_total'] . ' offene Signale im Bereich' : 'jetzt'">
                    @if ($attention['open_total'] > count($attention['items']))
                        <x-slot name="actions">
                            <a href="{{ route('organization.signals.index') }}" wire:navigate class="text-xs font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle Signale</a>
                        </x-slot>
                    @endif
                    @forelse ($attention['items'] as $item)
                        <div class="flex items-start gap-3 border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-0">
                            @if ($item['focused'])
                                <button type="button" wire:click="unfocusSignal({{ $item['signal_id'] }})" title="Aus Fokus entfernen" class="mt-0.5 shrink-0 text-[color:var(--nx-warning)] hover:opacity-70">
                                    @svg('heroicon-s-star', 'w-4 h-4')
                                </button>
                            @else
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" style="background: {{ $sevColor($item['severity']) }}"></span>
                            @endif
                            <a href="{{ $item['href'] }}" wire:navigate class="min-w-0 flex-1">
                                <span class="block text-sm text-[color:var(--nx-text)] hover:underline">{{ $item['title'] }}</span>
                                <span class="block text-xs text-[color:var(--nx-faint)]">{{ collect([$item['why'], $item['entity']])->filter()->implode(' · ') }}</span>
                            </a>
                        </div>
                    @empty
                        <p class="m-0 px-4 py-3 text-sm text-[color:var(--nx-muted)]">Nichts, das heute drängt.</p>
                    @endforelse
                </x-nx-panel>

                {{-- 4: Was sich bewegt — quer über die Einheiten der Perspektive --}}
                @if ($f)
                    <x-nx-panel title="Was sich bewegt" :subtitle="'gegenüber ' . $vorperiode">
                        @if (! empty($lage['moves']['good']) || ! empty($lage['moves']['bad']))
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
                                                <span class="shrink-0 text-xs tabular-nums">
                                                    @if (($m['flow'] ?? null) !== null)
                                                        <span class="font-semibold text-[color:var(--nx-text)]">{{ $num($m['flow'], $m['unit']) }}</span>
                                                        <span class="text-[color:var(--nx-faint)]">im Zeitraum</span>
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
                @endif
            </div>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                {{-- 6: Wer trägt — Menschen und Agenten aus der gebuchten Zeit der Perspektive --}}
                @if ($f && $f['energy']['minutes'] > 0)
                    @php
                        $e = $f['energy'];
                        $maxP = max(1, collect($e['people'])->max('minutes'));
                    @endphp
                    <x-nx-panel title="Wer trägt" :subtitle="$h($e['minutes']) . ' gebucht' . ($e['agent_minutes'] > 0 ? ', davon ' . $h($e['agent_minutes']) . ' Agenten' : '')">
                        <div class="space-y-2">
                            @foreach ($e['people'] as $p)
                                <div class="grid grid-cols-[minmax(0,1fr)_6rem_3rem] items-center gap-2 text-sm">
                                    <span class="truncate text-[color:var(--nx-text)]">{{ $writer->name($p['name']) ?? 'Unbekannt' }}@if ($p['agent']) <span class="text-xs text-[color:var(--nx-faint)]">Agent</span>@endif</span>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-[color:var(--nx-accent-soft)]">
                                        <div class="h-full rounded-full" style="width: {{ round(100 * $p['minutes'] / $maxP) }}%; background: {{ $p['agent'] ? 'var(--nx-info)' : 'var(--nx-warning)' }}"></div>
                                    </div>
                                    <span class="text-right text-xs tabular-nums text-[color:var(--nx-muted)]">{{ $h($p['minutes']) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-nx-panel>
                @endif

                {{-- 8: Umwelt — nur Ausschläge --}}
                @if ($envRadar['has_data'])
                    <x-nx-panel flush title="Umwelt" :subtitle="$envMoved->isNotEmpty() ? 'was sich draußen bewegt hat' : null">
                        <x-slot name="actions">
                            <a href="{{ route('organization.environment-snapshots.index') }}" wire:navigate class="text-xs font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle</a>
                        </x-slot>
                        @forelse ($envMoved as $src)
                            @php $up = $src['delta']['sentiment_change'] > 0; @endphp
                            <div class="border-b border-[color:var(--nx-line)] px-4 py-2.5 last:border-0">
                                <div class="flex items-baseline gap-2 text-sm">
                                    <span class="min-w-0 flex-1 truncate font-medium text-[color:var(--nx-text)]">{{ $src['name'] }}</span>
                                    <span class="shrink-0 text-xs" style="color: {{ $up ? 'var(--nx-success)' : 'var(--nx-danger)' }}">{{ $up ? 'Stimmung besser' : 'Stimmung schlechter' }}</span>
                                </div>
                                @if ($src['summary'])
                                    <p class="mb-0 mt-0.5 line-clamp-2 text-xs text-[color:var(--nx-muted)]">{{ $src['summary'] }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="m-0 px-4 py-3 text-sm text-[color:var(--nx-muted)]">Draußen ruhig: keine Quelle hat sich spürbar verändert.</p>
                        @endforelse
                    </x-nx-panel>
                @endif

                {{-- 7: Was zuletzt geschah — bis die Chronik aus Logbuch und Baum steht --}}
                <x-nx-panel flush title="Was zuletzt geschah">
                    <div class="max-h-[24rem] overflow-y-auto">
                        <livewire:organization.activity-feed lazy />
                    </div>
                </x-nx-panel>
            </div>
        </div>

        @if ($profile)
            <script type="application/json" id="org-dashboard-timings">{!! json_encode($this->timings()) !!}</script>
        @endif
    </x-ui-page-container>
</x-ui-page>
