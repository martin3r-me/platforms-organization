{{--
    Kunden-Tor · Detailseite eines Objekts (Rahmen). Kopf + Kennzahlen kommen hier, der Inhalt
    der Reiter bzw. einer Unterseite aus dem Modul (view + data, siehe ProvidesShareView).
--}}
@php
    $detailPct = $pct($detail['progress'] ?? null);
    $tabs = $detail['tabs'] ?? [];
@endphp

@if (! empty($detail['page']))
    {{-- Unterseite (z. B. ein Canvas) --}}
    <div class="px-4 py-6 sm:px-8 md:py-8">
        <a href="{{ $detailLink() }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">
            @svg('heroicon-o-arrow-left', 'w-4 h-4') {{ $detail['title'] }}
        </a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight">{{ $detail['page']['title'] }}</h1>
        <div class="mt-6">
            @include($detail['page']['view'], $detail['page']['data'] + ['link' => $detailLink])
        </div>
    </div>
@else
    @php
        $tabKeys = array_column($tabs, 'key');
    @endphp
    {{-- Reiter merken sich ihren Stand im Anker (#board, #verlauf …) — Zurück/Teilen funktioniert --}}
    <div x-data="{ tab: @js($tabKeys[0] ?? null), keys: @js($tabKeys) }"
         x-init="const h = location.hash.slice(1); if (keys.includes(h)) tab = h; $watch('tab', v => history.replaceState(null, '', '#' + v))">
        <div class="mx-auto max-w-[1120px] px-4 pt-8 sm:px-8">
            <div class="flex items-start gap-4">
                {!! $tile($detail['title'], $activeSlug . $activeId, 44) !!}
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 class="text-2xl font-bold leading-tight tracking-tight">{{ $detail['title'] }}</h1>
                        <x-nx-badge :variant="$badge[$detail['state'] ?? 'active'] ?? 'neutral'" dot>{{ $detail['state_label'] ?? 'Läuft' }}</x-nx-badge>
                    </div>
                    @if (! empty($detail['subtitle']))
                        <div class="mt-1 text-sm text-[color:var(--nx-muted)]">{{ $detail['subtitle'] }}</div>
                    @endif
                    @if (! empty($detail['description']))
                        <div x-data="{ more: false }" class="mt-2 max-w-3xl text-sm leading-relaxed text-[color:var(--nx-muted)]">
                            <div class="whitespace-pre-line" @if (mb_strlen($detail['description']) > 240) :class="more ? '' : 'line-clamp-3'" @endif>{{ $detail['description'] }}</div>
                            @if (mb_strlen($detail['description']) > 240)
                                <button type="button" class="mt-1 text-xs font-medium text-[color:var(--nx-text)] hover:underline" @click="more = ! more" x-text="more ? 'Weniger' : 'Mehr anzeigen'"></button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Kennzahlen --}}
            <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                @if ($detailPct !== null)
                    <div class="col-span-2 flex items-center gap-3 rounded-[12px] border border-[color:var(--nx-line)] p-3 md:col-span-1">
                        {!! $ring($detailPct, 40, true) !!}
                        <div class="min-w-0 text-xs">
                            <div class="font-medium text-[color:var(--nx-text)]">Fortschritt</div>
                            <div class="text-[color:var(--nx-muted)]">{{ $detail['progress']['done'] }} von {{ $detail['progress']['total'] }} Schritten</div>
                        </div>
                    </div>
                @endif
                @foreach ($detail['facts'] ?? [] as $fact)
                    <div @class(['rounded-[12px] border border-[color:var(--nx-line)] px-3 py-2.5', 'col-span-2 md:col-span-1' => $loop->last && $loop->count % 2 === 1])>
                        <div class="text-xs text-[color:var(--nx-muted)]">{{ $fact['label'] }}</div>
                        <div class="mt-0.5 truncate text-lg font-semibold tabular-nums">{{ $fact['value'] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Reiter --}}
            @if (count($tabs) > 1)
                <div class="mt-8 flex gap-6 overflow-x-auto border-b border-[color:var(--nx-line)] text-sm">
                    @foreach ($tabs as $t)
                        <button type="button" @click="tab = @js($t['key'])" @class(['gp-tab', 'is-on' => $loop->first]) :class="{ 'is-on': tab === @js($t['key']) }">
                            {{ $t['label'] }}
                            @if (isset($t['count']))<span class="text-xs text-[color:var(--nx-faint)]">{{ $t['count'] }}</span>@endif
                        </button>
                    @endforeach
                </div>
            @else
                <div class="mt-8 border-b border-[color:var(--nx-line)]"></div>
            @endif
        </div>

        @foreach ($tabs as $t)
            <div x-show="tab === @js($t['key'])" @if (! $loop->first) style="display: none" @endif wire:key="tab-{{ $t['key'] }}">
                @include($t['view'], $t['data'] + ['link' => $detailLink])
            </div>
        @endforeach
    </div>
@endif
