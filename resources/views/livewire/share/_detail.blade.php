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
    <div x-data="{ tab: @js($tabs[0]['key'] ?? null) }">
        <div class="mx-auto max-w-[1200px] px-4 pt-8 sm:px-8">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h1 class="text-2xl font-bold tracking-tight">{{ $detail['title'] }}</h1>
                <x-nx-badge :variant="$badge[$detail['state'] ?? 'active'] ?? 'neutral'" dot>{{ $detail['state_label'] ?? 'Läuft' }}</x-nx-badge>
            </div>
            @if (! empty($detail['subtitle']))
                <div class="mt-1 text-sm text-[color:var(--nx-muted)]">{{ $detail['subtitle'] }}</div>
            @endif
            @if (! empty($detail['description']))
                <div x-data="{ more: false }" class="mt-3 max-w-3xl text-sm leading-relaxed text-[color:var(--nx-muted)]">
                    <div class="whitespace-pre-line" :class="more ? '' : 'line-clamp-3'">{{ $detail['description'] }}</div>
                    @if (mb_strlen($detail['description']) > 240)
                        <button type="button" class="mt-1 text-xs font-medium text-[color:var(--nx-text)] hover:underline" @click="more = ! more" x-text="more ? 'Weniger' : 'Mehr anzeigen'"></button>
                    @endif
                </div>
            @endif

            {{-- Kennzahlen-Zeile --}}
            <div class="mt-5 flex flex-wrap items-center gap-x-8 gap-y-3">
                @if ($detailPct !== null)
                    <div class="flex min-w-[220px] items-center gap-3">
                        <div class="h-1.5 w-40 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                            <div class="h-full rounded-full bg-[color:var(--nx-success)]" style="width: {{ $detailPct }}%"></div>
                        </div>
                        <span class="text-sm"><span class="font-semibold tabular-nums">{{ $detailPct }} %</span> <span class="text-[color:var(--nx-faint)]">· {{ $detail['progress']['done'] }} von {{ $detail['progress']['total'] }} Schritten</span></span>
                    </div>
                @endif
                @foreach ($detail['facts'] ?? [] as $fact)
                    <div class="text-sm">
                        <span class="text-[color:var(--nx-faint)]">{{ $fact['label'] }}</span>
                        <span class="ml-1 font-medium tabular-nums">{{ $fact['value'] }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Reiter --}}
            @if (count($tabs) > 1)
                <div class="mt-6 flex gap-5 overflow-x-auto border-b border-[color:var(--nx-line)] text-sm">
                    @foreach ($tabs as $t)
                        <button type="button" @click="tab = @js($t['key'])"
                                class="-mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 pb-2 transition-colors"
                                :class="tab === @js($t['key']) ? 'border-[color:var(--nx-text)] font-medium text-[color:var(--nx-text)]' : 'border-transparent text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]'">
                            {{ $t['label'] }}
                            @if (isset($t['count']))<span class="text-xs text-[color:var(--nx-faint)]">{{ $t['count'] }}</span>@endif
                        </button>
                    @endforeach
                </div>
            @else
                <div class="mt-6 border-b border-[color:var(--nx-line)]"></div>
            @endif
        </div>

        @foreach ($tabs as $t)
            <div x-show="tab === @js($t['key'])" @if (! $loop->first) style="display: none" @endif wire:key="tab-{{ $t['key'] }}">
                @include($t['view'], $t['data'] + ['link' => $detailLink])
            </div>
        @endforeach
    </div>
@endif
