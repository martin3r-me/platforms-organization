{{--
    Kunden-Tor — geteilte Seite eines Knotens im nx-Look der Plattform: links die Navigation
    (wie die Hauptsidebar), rechts Übersicht oder Detailseite eines Objekts. Inhalte kommen aus
    EntityShareService::pageData() (Übersicht + Navigation) bzw. ::detailData() (Modul-Detail).
    Die Hülle (Sidebar/Schublade) hat eigenes CSS — unabhängig von Tailwind-Version und -Build.
--}}
@php
    $de = fn ($d, $f = 'D. MMMM YYYY') => $d ? $d->copy()->locale('de')->isoFormat($f) : null;
    $ago = fn ($d) => $d ? ($d->diffInMinutes(now()) < 1 ? 'gerade eben' : $d->copy()->locale('de')->diffForHumans()) : null;
    $duePhrase = function ($d) {
        if (! $d) {
            return null;
        }
        $diff = (int) now()->startOfDay()->diffInDays($d->copy()->startOfDay(), false);

        return match (true) {
            $diff < -1 => 'seit ' . abs($diff) . ' Tagen fällig',
            $diff === -1 => 'seit gestern fällig',
            $diff === 0 => 'heute fällig',
            $diff === 1 => 'morgen',
            $diff < 7 => 'in ' . $diff . ' Tagen',
            default => $d->copy()->locale('de')->isoFormat('D. MMM'),
        };
    };
    $badge = ['active' => 'info', 'done' => 'success', 'paused' => 'warning', 'planned' => 'neutral'];
    $pct = fn ($p) => ($p && ($p['total'] ?? 0)) ? (int) round($p['done'] / $p['total'] * 100) : null;
    // Stabile Kachelfarbe je Objekt (nx-Tone-Palette)
    $tones = ['indigo', 'teal', 'amber', 'violet', 'sky', 'rose', 'emerald', 'pink'];
    $tone = fn ($key) => $tones[abs(crc32((string) $key)) % count($tones)];
    $tile = fn ($label, $key, $size = 28) => sprintf(
        '<span class="gp-tile" style="width:%1$dpx;height:%1$dpx;font-size:%2$dpx;background:color-mix(in srgb, var(--nx-tone-%3$s) 16%%, #fff);color:var(--nx-tone-%3$s)">%4$s</span>',
        $size, (int) round($size * 0.42), $tone($key), e(mb_strtoupper(mb_substr(trim($label) ?: '?', 0, 1)))
    );
    // Fortschrittsring (SVG); 100 % → grüner Haken-Ring
    $ring = function (?int $p, int $size = 16, bool $label = false) {
        $p = max(0, min(100, (int) $p));
        $c = 97.39;
        $color = $p >= 100 ? 'var(--nx-success)' : 'var(--nx-info)';
        $text = $label ? sprintf('<text x="18" y="18" dy=".35em" text-anchor="middle" style="font-size:9px;font-weight:600;fill:var(--nx-text)">%d%%</text>', $p) : '';

        return sprintf(
            '<svg width="%1$d" height="%1$d" viewBox="0 0 36 36" class="shrink-0" aria-hidden="true"><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--nx-line-strong)" stroke-width="%5$s"/><circle cx="18" cy="18" r="15.5" fill="none" stroke="%2$s" stroke-width="%5$s" stroke-linecap="round" stroke-dasharray="%3$.2f %4$.2f" transform="rotate(-90 18 18)"/>%6$s</svg>',
            $size, $color, $p / 100 * $c, $c, $size <= 20 ? '5' : '3.2', $text
        );
    };
    $teamInitial = mb_strtoupper(mb_substr($teamName ?: 'P', 0, 1));
@endphp

<div class="gp" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    <style>
        .gp { min-height: 100vh; background: var(--nx-surface); color: var(--nx-text); -webkit-font-smoothing: antialiased;
              font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', Helvetica, Arial, sans-serif; }
        .gp-shell { display: flex; min-height: 100vh; }
        .gp-nav { position: fixed; inset: 0 auto 0 0; z-index: 40; width: 264px; display: flex; flex-direction: column;
                  background: var(--nx-bg); border-right: 1px solid var(--nx-line); transform: translateX(-100%); transition: transform .2s ease; }
        .gp-nav.is-open { transform: none; box-shadow: var(--nx-shadow-pop); }
        .gp-scrim { position: fixed; inset: 0; z-index: 30; background: rgba(15, 15, 15, .25); }
        .gp-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        @media (min-width: 900px) {
            .gp-nav { position: sticky; top: 0; height: 100vh; flex-shrink: 0; transform: none; box-shadow: none !important; }
            .gp-scrim, .gp-burger { display: none !important; }
        }
        .gp-tile { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 6px; font-weight: 700; line-height: 1; }
        .gp-live { position: relative; width: 7px; height: 7px; border-radius: 999px; background: var(--nx-success); }
        .gp-live::after { content: ''; position: absolute; inset: -3px; border-radius: 999px; background: var(--nx-success); opacity: .35; animation: gp-pulse 2s ease-out infinite; }
        @keyframes gp-pulse { 0% { transform: scale(.6); opacity: .45 } 100% { transform: scale(1.8); opacity: 0 } }
        .gp-navlink { display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 6px; color: var(--nx-muted); font-size: 14px; transition: background .12s, color .12s; }
        .gp-navlink:hover { background: var(--nx-hover); color: var(--nx-text); }
        .gp-navlink.is-active { background: var(--nx-active); color: var(--nx-text); font-weight: 500; }
        .gp-navlink > .min-w-0 { flex: 1; }
        .gp-navlink .gp-meta { margin-left: auto; flex-shrink: 0; white-space: nowrap; font-size: 11px; color: var(--nx-faint); font-variant-numeric: tabular-nums; }
        .gp-tab { margin-bottom: -1px; display: inline-flex; flex-shrink: 0; align-items: center; gap: 6px; padding-bottom: 9px; border-bottom: 2px solid transparent;
                  color: var(--nx-muted); transition: color .12s, border-color .12s; }
        .gp-tab:hover { color: var(--nx-text); }
        .gp-tab.is-on { border-bottom-color: var(--nx-text); color: var(--nx-text); font-weight: 500; }
        /* Breite Inhalte (Board) laufen randlos, beginnen aber bündig mit dem Seitenraster (max 1120px) */
        .gp-bleed { padding-left: max(1rem, calc((100% - 1120px) / 2 + 1rem)); padding-right: 1rem; }
        @media (min-width: 640px) { .gp-bleed { padding-left: max(2rem, calc((100% - 1120px) / 2 + 2rem)); padding-right: 2rem; } }
    </style>

    @if ($status !== 'ok')
        {{-- Zugang anfordern / nicht mehr aktiv --}}
        <div class="grid min-h-screen place-items-center bg-[color:var(--nx-bg)] px-4 py-10">
            <div class="w-full max-w-sm">
                <div class="mb-6 flex items-center gap-2 text-sm font-semibold">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-[6px] bg-[color:var(--nx-accent)] text-xs font-bold text-[color:var(--nx-on-accent)]">{{ $teamInitial }}</span>
                    {{ $teamName }}
                </div>
                <div class="rounded-[12px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-6">
                    @if ($status === 'request')
                        <div class="text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Persönlicher Zugang</div>
                        <h1 class="mt-1 text-xl font-semibold leading-snug">{{ $title }}</h1>
                        @if ($requested)
                            <div class="mt-4 flex gap-2 rounded-[8px] bg-[color:var(--nx-hover)] p-3 text-sm">
                                @svg('heroicon-o-envelope', 'w-5 h-5 shrink-0 text-[color:var(--nx-success)]')
                                <span>Falls diese Adresse eingeladen ist, ist Ihr persönlicher Link jetzt unterwegs. Schauen Sie in Ihr Postfach.</span>
                            </div>
                        @else
                            <p class="mt-2 text-sm text-[color:var(--nx-muted)]">Dieser Bereich ist für eingeladene Personen. Geben Sie Ihre E-Mail-Adresse ein – wir schicken Ihnen Ihren persönlichen Link.</p>
                            <form wire:submit="requestAccess" class="mt-4 space-y-2">
                                <input type="email" wire:model="email" placeholder="name@firma.de" autocomplete="email" required
                                       class="w-full rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-3 py-2 text-sm outline-none focus:border-[color:var(--nx-accent)]">
                                @error('email') <div class="text-xs text-[color:var(--nx-danger)]">{{ $message }}</div> @enderror
                                <button type="submit" wire:loading.attr="disabled"
                                        class="w-full rounded-[6px] bg-[color:var(--nx-accent)] px-3 py-2 text-sm font-medium text-[color:var(--nx-on-accent)] hover:bg-[color:var(--nx-accent-hover)]">Link zuschicken</button>
                            </form>
                        @endif
                    @else
                        <div class="text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Freigabe</div>
                        <h1 class="mt-1 text-xl font-semibold leading-snug">Dieser Link ist nicht mehr aktiv</h1>
                        <p class="mt-2 text-sm text-[color:var(--nx-muted)]">Die Freigabe wurde beendet oder ist abgelaufen. Bitte wenden Sie sich an Ihre Ansprechperson – sie schickt Ihnen gern einen neuen Link.</p>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="gp-shell">
            <div class="gp-scrim" x-show="nav" x-transition.opacity @click="nav = false" style="display: none"></div>

            {{-- Navigation — wie die Hauptsidebar; schmal als Schublade --}}
            <aside class="gp-nav" :class="nav && 'is-open'">
                <div class="flex items-center gap-2.5 border-b border-[color:var(--nx-line)] px-3 py-3">
                    {!! $tile($entity->name, 'e' . $entity->id, 32) !!}
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold leading-tight">{{ $title }}</div>
                        <div class="truncate text-xs text-[color:var(--nx-faint)]">Portal von {{ $teamName }}</div>
                    </div>
                    <button type="button" class="gp-burger -mr-1 inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" @click="nav = false" title="Schließen">
                        @svg('heroicon-o-x-mark', 'w-5 h-5')
                    </button>
                </div>

                <nav class="flex-1 overflow-y-auto px-2 py-3">
                    <a href="{{ $link() }}" wire:navigate @class(['gp-navlink', 'is-active' => ! $activeSlug && ! $notFound])>
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4 shrink-0')
                        <span class="truncate">Übersicht</span>
                    </a>

                    @foreach ($data['nav'] as $group)
                        <div class="mb-1 mt-5 flex items-center justify-between px-2 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">
                            <span>{{ $group['label'] }}</span>
                            <span class="tabular-nums">{{ count($group['items']) }}</span>
                        </div>
                        @foreach ($group['items'] as $item)
                            @php
                                $itemPct = $pct($item['progress'] ?? null);
                            @endphp
                            <a href="{{ $link($group['slug'], $item['id']) }}" wire:navigate wire:key="nav-{{ $group['slug'] }}-{{ $item['id'] }}"
                               @class(['gp-navlink', 'is-active' => $activeSlug === $group['slug'] && $activeId === (int) $item['id']])
                               @click="nav = false">
                                {!! $ring(($item['state'] ?? '') === 'done' ? 100 : ($itemPct ?? 0), 15) !!}
                                <span class="min-w-0 truncate">{{ $item['title'] }}</span>
                                @if ($itemPct !== null)<span class="gp-meta">{{ $itemPct }} %</span>@endif
                            </a>
                        @endforeach
                    @endforeach
                </nav>

                <div class="border-t border-[color:var(--nx-line)] px-3 py-3">
                    @if ($recipient)
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[color:var(--nx-accent-soft)] text-[11px] font-semibold uppercase">{{ mb_substr($recipient->email, 0, 1) }}</span>
                            <div class="min-w-0">
                                <div class="truncate text-xs font-medium">{{ $recipient->email }}</div>
                                <div class="text-[11px] text-[color:var(--nx-faint)]">Persönlicher Zugang</div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-2 text-[11px] text-[color:var(--nx-faint)]">
                            @svg('heroicon-o-link', 'w-3.5 h-3.5 shrink-0')
                            Freigabe-Link · ohne Anmeldung
                        </div>
                    @endif
                </div>
            </aside>

            {{-- Inhalt --}}
            <div class="gp-main" wire:poll.visible.120s>
                <header class="sticky top-0 z-20 flex h-12 items-center gap-2 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-4">
                    <button type="button" class="gp-burger -ml-1.5 inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" @click="nav = true" title="Navigation">
                        @svg('heroicon-o-bars-3', 'w-5 h-5')
                    </button>
                    <nav class="flex min-w-0 flex-1 items-center gap-1.5 text-sm">
                        @if ($activeSlug || $notFound)
                            <a href="{{ $link() }}" wire:navigate class="shrink-0 text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Übersicht</a>
                            @if ($detail)
                                @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                                @if (! empty($detail['page']))
                                    <a href="{{ $link($activeSlug, $activeId) }}" wire:navigate class="min-w-0 truncate text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">{{ $detail['title'] }}</a>
                                    @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                                    <span class="min-w-0 truncate font-medium">{{ $detail['page']['title'] }}</span>
                                @else
                                    <span class="min-w-0 truncate font-medium">{{ $detail['title'] }}</span>
                                @endif
                            @endif
                        @else
                            <span class="min-w-0 truncate font-medium">Übersicht</span>
                        @endif
                    </nav>
                    <span class="inline-flex shrink-0 items-center gap-2 rounded-full border border-[color:var(--nx-line)] px-2.5 py-1 text-xs text-[color:var(--nx-muted)]" title="Die Seite aktualisiert sich automatisch">
                        <span class="gp-live"></span>
                        <span>Live<span class="hidden sm:inline"> · {{ $de(now(), 'HH:mm') }} Uhr</span></span>
                    </span>
                </header>

                <main class="flex-1">
                    @if ($notFound)
                        <div class="mx-auto max-w-md px-4 py-24 text-center">
                            <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-[color:var(--nx-bg)]">@svg('heroicon-o-eye-slash', 'w-6 h-6 text-[color:var(--nx-faint)]')</span>
                            <div class="mt-4 font-semibold">Nicht (mehr) Teil dieser Freigabe</div>
                            <p class="mt-1 text-sm text-[color:var(--nx-muted)]">Der Inhalt wurde entfernt oder ist hier nicht sichtbar.</p>
                            <a href="{{ $link() }}" wire:navigate class="mt-5 inline-flex rounded-[6px] border border-[color:var(--nx-line-strong)] px-3 py-1.5 text-sm hover:bg-[color:var(--nx-hover)]">Zur Übersicht</a>
                        </div>
                    @elseif ($detail)
                        @include('organization::livewire.share._detail', ['detail' => $detail, 'detailLink' => fn (?string $sub = null) => $link($activeSlug, $activeId, $sub)])
                    @else
                        @include('organization::livewire.share._overview')
                    @endif
                </main>

                <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-[color:var(--nx-line)] px-4 py-4 text-xs text-[color:var(--nx-faint)] sm:px-8">
                    <span class="inline-flex items-center gap-2">
                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-[5px] bg-[color:var(--nx-accent)] text-[10px] font-bold text-[color:var(--nx-on-accent)]">{{ $teamInitial }}</span>
                        Geteilt von {{ $senderName ? $senderName . ' · ' : '' }}{{ $teamName }}
                    </span>
                    <span>Stand {{ $de(now(), 'D. MMMM YYYY, HH:mm') }} Uhr</span>
                </footer>
            </div>
        </div>
    @endif
</div>
