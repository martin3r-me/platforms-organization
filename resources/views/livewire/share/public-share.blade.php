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
    // Stunden ohne Beträge; Geld in Cent
    $hours = fn (int $min) => \Platform\Organization\Services\EntityShareService::hours($min);
    $money = fn (int $cents, string $cur = 'EUR') => number_format($cents / 100, 2, ',', '.') . ' ' . ($cur === 'EUR' ? '€' : $cur);
    // Person: Profilbild hinter der Freigabe, sonst Initialen
    $person = function (array|string $p, int $size = 24) use ($avatar, $tone) {
        $name = is_array($p) ? ($p['name'] ?? '?') : $p;
        $url = is_array($p) ? $avatar($p) : null;
        $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $t = $tone('p' . $name);

        return $url
            ? sprintf('<img src="%1$s" alt="" class="gp-avatar" style="width:%2$dpx;height:%2$dpx" loading="lazy">', e($url), $size)
            : sprintf('<span class="gp-avatar" style="width:%1$dpx;height:%1$dpx;font-size:%2$dpx;background:color-mix(in srgb, var(--nx-tone-%3$s) 18%%, #fff);color:var(--nx-tone-%3$s)" title="%5$s">%4$s</span>', $size, (int) round($size * 0.4), $t, e($initials ?: '?'), e($name));
    };
    $dayLabel = function ($d) use ($de) {
        if (! $d) {
            return 'Früher';
        }

        return match (true) {
            $d->isToday() => 'Heute',
            $d->isYesterday() => 'Gestern',
            $d->greaterThan(now()->subDays(6)->startOfDay()) => $d->copy()->locale('de')->isoFormat('dddd'),
            $d->year === now()->year => $de($d, 'dd, D. MMMM'),
            default => $de($d, 'D. MMMM YYYY'),
        };
    };
    $pageLink = $link; // Modul-Views bekommen ein eigenes $link (Unterseiten des Objekts)
@endphp

<div class="gp" x-data="{
        nav: false,
        collapsed: (() => { try { return localStorage.getItem('gp-nav') === '0' } catch (e) { return false } })(),
        toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('gp-nav', this.collapsed ? '0' : '1') } catch (e) {} },
    }" @keydown.escape.window="nav = false"
    @keydown.window="if ($event.key === '[' && ! ['INPUT', 'TEXTAREA'].includes($event.target.tagName)) toggle()">
    <style>
        .gp { min-height: 100vh; background: var(--nx-surface); color: var(--nx-text); -webkit-font-smoothing: antialiased;
              font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', Helvetica, Arial, sans-serif; }
        .gp-shell { display: flex; min-height: 100vh; }
        .gp-nav { position: fixed; inset: 0 auto 0 0; z-index: 40; width: 336px; max-width: 88vw; display: flex; flex-direction: column;
                  background: var(--nx-bg); border-right: 1px solid var(--nx-line); transform: translateX(-100%); transition: transform .2s ease; }
        .gp-nav.is-open { transform: none; box-shadow: var(--nx-shadow-pop); }
        .gp-scrim { position: fixed; inset: 0; z-index: 30; background: rgba(15, 15, 15, .25); }
        .gp-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        @media (min-width: 900px) {
            .gp-nav { position: sticky; top: 0; height: 100vh; flex-shrink: 0; transform: none; box-shadow: none !important; }
            .gp-scrim, .gp-burger { display: none !important; }
            .gp-nav.is-collapsed { display: none; }
        }
        @media (max-width: 899px) { .gp-desk { display: none !important; } }
        .gp-avatar { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 999px; object-fit: cover; font-weight: 600; line-height: 1; }
        .gp-card { border: 1px solid var(--nx-line); border-radius: 12px; background: var(--nx-surface); }
        .gp-h2 { font-size: 15px; font-weight: 600; }
        .gp-bars { display: flex; align-items: flex-end; gap: 6px; height: 96px; }
        .gp-bars > div { flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: 6px; }
        .gp-bars .gp-bar { width: 100%; max-width: 28px; border-radius: 4px 4px 2px 2px; background: color-mix(in srgb, var(--nx-info) 30%, transparent); min-height: 2px; }
        .gp-bars .gp-bar.is-now { background: var(--nx-info); }
        .gp-bars span { font-size: 10px; color: var(--nx-faint); }
        .gp-dot-new { width: 6px; height: 6px; border-radius: 999px; background: var(--nx-info); flex-shrink: 0; }
        .gp-tile { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 6px; font-weight: 700; line-height: 1; }
        .gp-live { position: relative; width: 7px; height: 7px; border-radius: 999px; background: var(--nx-success); }
        .gp-live::after { content: ''; position: absolute; inset: -3px; border-radius: 999px; background: var(--nx-success); opacity: .35; animation: gp-pulse 2s ease-out infinite; }
        @keyframes gp-pulse { 0% { transform: scale(.6); opacity: .45 } 100% { transform: scale(1.8); opacity: 0 } }
        .gp-navlink { display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 6px; color: var(--nx-muted); font-size: 14px; transition: background .12s, color .12s; }
        .gp-navlink:hover { background: var(--nx-hover); color: var(--nx-text); }
        .gp-navlink.is-active { background: var(--nx-active); color: var(--nx-text); font-weight: 500; }
        .gp-navlink > .min-w-0 { flex: 1; }
        /* Lange Namen umbrechen statt abschneiden (max. 2 Zeilen) */
        .gp-navtext { flex: 1; min-width: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; }
        .gp-navlink.is-multi { align-items: flex-start; }
        .gp-navlink.is-multi > svg, .gp-navlink.is-multi > .gp-ico { margin-top: 2px; }
        .gp-navgroup { margin: 18px 8px 4px; font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--nx-faint); }
        .gp-navsub { display: flex; align-items: center; gap: 10px; margin-top: 6px; padding: 4px 8px; font-size: 13px; font-weight: 500; color: var(--nx-text); }
        .gp-navsub span:last-child { font-size: 11px; font-weight: 400; color: var(--nx-faint); }
        .gp-navarea { display: flex; align-items: center; gap: 6px; padding: 8px 8px 2px; font-size: 11px; color: var(--nx-faint); }
        .gp-navarea span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gp-navlink .gp-meta { margin-left: auto; flex-shrink: 0; white-space: nowrap; font-size: 11px; color: var(--nx-faint); font-variant-numeric: tabular-nums; }
        .gp-tab { margin-bottom: -1px; display: inline-flex; flex-shrink: 0; align-items: center; gap: 6px; padding-bottom: 9px; border-bottom: 2px solid transparent;
                  color: var(--nx-muted); transition: color .12s, border-color .12s; }
        .gp-tab:hover { color: var(--nx-text); }
        .gp-tab.is-on { border-bottom-color: var(--nx-text); color: var(--nx-text); font-weight: 500; }
        /* Inhalt nutzt die volle Breite von main; gp-bleed (Board) läuft rechts randlos */
        .gp-wrap { width: 100%; padding-left: 1rem; padding-right: 1rem; }
        .gp-bleed { padding-left: 1rem; padding-right: 1rem; }
        @media (min-width: 640px) { .gp-wrap, .gp-bleed { padding-left: 2rem; padding-right: 2rem; } }
        @media (min-width: 1280px) { .gp-wrap, .gp-bleed { padding-left: 2.5rem; padding-right: 2.5rem; } }
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
            <aside class="gp-nav" :class="{ 'is-open': nav, 'is-collapsed': collapsed }">
                <div class="flex items-center gap-2.5 border-b border-[color:var(--nx-line)] px-3 py-3">
                    {!! $tile($entity->name, 'e' . $entity->id, 32) !!}
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold leading-tight">{{ $title }}</div>
                        <div class="truncate text-xs text-[color:var(--nx-faint)]">Portal von {{ $teamName }}</div>
                    </div>
                    <button type="button" class="gp-desk -mr-1 inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-faint)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]" @click="toggle()" title="Seitenleiste einklappen ( [ )">
                        @svg('heroicon-o-chevron-double-left', 'w-4 h-4')
                    </button>
                    <button type="button" class="gp-burger -mr-1 inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" @click="nav = false" title="Schließen">
                        @svg('heroicon-o-x-mark', 'w-5 h-5')
                    </button>
                </div>

                @if (! empty($data['areas']))
                    <div class="border-b border-[color:var(--nx-line)] px-3 py-2.5">
                        <label class="relative flex items-center gap-2 rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-2.5 py-1.5 text-sm hover:border-[color:var(--nx-line-strong)]">
                            @svg('heroicon-o-map-pin', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                            <select class="min-w-0 flex-1 cursor-pointer appearance-none bg-transparent pr-5 outline-none" wire:change="setArea($event.target.value || null)" aria-label="Bereich">
                                <option value="" @selected(! $data['area'])>Alle Bereiche</option>
                                @foreach ($data['areas'] as $opt)
                                    <option value="{{ $opt['id'] }}" @selected($data['area'] === $opt['id'])>{{ str_repeat('  ', max(0, $opt['depth'] - 1)) }}{{ $opt['name'] }}</option>
                                @endforeach
                            </select>
                            @svg('heroicon-m-chevron-up-down', 'pointer-events-none absolute right-2 w-4 h-4 text-[color:var(--nx-faint)]')
                        </label>
                    </div>
                @endif

                <nav class="flex-1 overflow-y-auto px-2 py-3">
                    <a href="{{ $link() }}" wire:navigate @class(['gp-navlink', 'is-active' => ! $activeSlug && ! $section && ! $notFound])>
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4 shrink-0')
                        <span class="truncate">Übersicht</span>
                    </a>

                    @if ($data['activity'])
                        <a href="{{ $link('aktivitaet') }}" wire:navigate @class(['gp-navlink', 'is-active' => $section === 'aktivitaet' && ! $notFound]) @click="nav = false">
                            @svg('heroicon-o-bolt', 'w-4 h-4 shrink-0')
                            <span class="min-w-0 truncate">Aktivität</span>
                        </a>
                    @endif

                    @php
                        $konto = array_values(array_filter([
                            ! empty($data['work']['entry_count']) ? ['leistung', 'Leistung', 'heroicon-o-clock', $data['work']['period_minutes']['30'] ? $hours($data['work']['period_minutes']['30']) . ' / 30 T.' : null, null] : null,
                            ! empty($data['finance']) ? ['rechnungen', 'Rechnungen', 'heroicon-o-document-text', ($data['finance']['summary']['open'] ?? 0) > 0 ? $money($data['finance']['summary']['open'], $data['finance']['currency']) . ' offen' : 'alles bezahlt', ($data['finance']['summary']['overdue'] ?? 0) > 0 ? 'danger' : null] : null,
                        ]));
                        $toneColor = ['success' => 'var(--nx-success)', 'warning' => 'var(--nx-warning)', 'danger' => 'var(--nx-danger)', 'info' => 'var(--nx-info)'];
                    @endphp
                    @foreach (\Platform\Organization\Services\EntityShareService::NAV_GROUP_LABELS as $navGroup => $navGroupLabel)
                        @php
                            $groups = $data['nav'][$navGroup] ?? collect();
                        @endphp
                        @continue(collect($groups)->isEmpty() && ! ($navGroup === 'konto' && $konto))
                        <div class="gp-navgroup">{{ $navGroupLabel }}</div>

                        @foreach ($groups as $group)
                            @if ($group['nav_mode'] === 'section')
                                @php
                                    $st = $group['summary']['status'] ?? null;
                                @endphp
                                <a href="{{ $link($group['slug']) }}" wire:navigate wire:key="nav-s-{{ $group['slug'] }}" @class(['gp-navlink', 'is-active' => $section === $group['slug'] && ! $notFound]) @click="nav = false">
                                    @svg('heroicon-o-' . $group['icon'], 'w-4 h-4 shrink-0')
                                    <span class="min-w-0 truncate">{{ $group['label'] }}</span>
                                    @if ($st)<span class="gp-meta" style="color: {{ $toneColor[$st['tone'] ?? ''] ?? 'var(--nx-faint)' }}">{{ $st['text'] }}</span>@endif
                                </a>
                                @continue
                            @endif
                        @php
                            [$archived, $running] = collect($group['items'])->partition(fn ($i) => ($i['state'] ?? '') === 'done');
                            $archiveOpen = $archived->contains(fn ($i) => $activeSlug === $group['slug'] && $activeId === (int) $i['id']);
                        @endphp
                        <div class="gp-navsub">
                            @svg('heroicon-o-' . $group['icon'], 'w-4 h-4 shrink-0')
                            <span class="flex-1 truncate">{{ $group['label'] }}</span>
                            <span class="tabular-nums">{{ $running->count() }}</span>
                        </div>
                        @php
                            $navAreas = $running->groupBy(fn ($i) => $i['area_id'] ?? 0)->sortBy(fn ($g) => $g->first()['area_pos'] ?? 0);
                            $navShowAreas = $navAreas->count() > 1 || ($navAreas->keys()->first() ?? $entity->id) != $entity->id;
                        @endphp
                        @foreach ($navAreas as $areaItems)
                            @if ($navShowAreas)
                                <div class="gp-navarea" title="{{ $entity->name }}{{ ! empty($areaItems->first()['area_path']) ? ' › ' . implode(' › ', $areaItems->first()['area_path']) : '' }}">
                                    @svg('heroicon-o-map-pin', 'w-3 h-3 shrink-0')
                                    <span>{{ ! empty($areaItems->first()['area_path']) ? implode(' › ', $areaItems->first()['area_path']) : $entity->name }}</span>
                                </div>
                            @endif
                            @foreach ($areaItems as $item)
                                @php
                                    $itemPct = $pct($item['progress'] ?? null);
                                @endphp
                                <a href="{{ $link($group['slug'], $item['id']) }}" wire:navigate wire:key="nav-{{ $group['slug'] }}-{{ $item['id'] }}"
                                   @class(['gp-navlink is-multi', 'is-active' => $activeSlug === $group['slug'] && $activeId === (int) $item['id']])
                                   @click="nav = false" title="{{ $item['title'] }}">
                                    <span class="gp-ico inline-flex shrink-0">
                                        @if (($item['state'] ?? '') === 'paused')
                                            @svg('heroicon-s-pause-circle', 'w-[15px] h-[15px] text-[color:var(--nx-warning)]')
                                        @elseif ($itemPct === null && $group['alias'] !== 'project' && ! isset($item['progress']))
                                            @svg('heroicon-o-' . $group['icon'], 'w-4 h-4 text-[color:var(--nx-faint)]')
                                        @else
                                            {!! $ring($itemPct ?? 0, 15) !!}
                                        @endif
                                    </span>
                                    <span class="gp-navtext">{{ $item['title'] }}</span>
                                    @if (! empty($item['nav_status']))
                                        <span class="gp-meta" style="color: {{ $toneColor[$item['nav_status']['tone'] ?? ''] ?? 'var(--nx-faint)' }}">{{ $item['nav_status']['text'] }}</span>
                                    @elseif ($itemPct !== null)<span class="gp-meta">{{ $itemPct }} %</span>@endif
                                </a>
                            @endforeach
                        @endforeach
                        @if ($running->isEmpty())
                            <div class="px-2 py-1 text-xs text-[color:var(--nx-faint)]">Gerade nichts laufend</div>
                        @endif
                        @if ($archived->isNotEmpty())
                            <div x-data="{ open: @js($archiveOpen) }" class="mt-1">
                                <button type="button" class="gp-navlink w-full" @click="open = ! open">
                                    <span class="inline-flex w-[15px] justify-center transition-transform" :style="open && 'transform: rotate(90deg)'">@svg('heroicon-m-chevron-right', 'w-3.5 h-3.5 shrink-0')</span>
                                    <span class="min-w-0 truncate text-left">Abgeschlossen</span>
                                    <span class="gp-meta">{{ $archived->count() }}</span>
                                </button>
                                <div x-show="open" @if (! $archiveOpen) style="display: none" @endif>
                                    @foreach ($archived as $item)
                                        <a href="{{ $link($group['slug'], $item['id']) }}" wire:navigate wire:key="nav-a-{{ $group['slug'] }}-{{ $item['id'] }}"
                                           @class(['gp-navlink is-multi', 'is-active' => $activeSlug === $group['slug'] && $activeId === (int) $item['id']])
                                           @click="nav = false" title="{{ $item['title'] }}">
                                            @svg('heroicon-s-check-circle', 'w-[15px] h-[15px] shrink-0 text-[color:var(--nx-success)]')
                                            <span class="gp-navtext">{{ $item['title'] }}</span>
                                            @if (! empty($item['state_since']))<span class="gp-meta">{{ $de($item['state_since'], 'MMM YY') }}</span>@endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @endforeach

                        @if ($navGroup === 'konto')
                            @foreach ($konto as [$key, $label, $icon, $meta, $tone])
                                <a href="{{ $link($key) }}" wire:navigate @class(['gp-navlink', 'is-active' => $section === $key && ! $notFound]) @click="nav = false">
                                    @svg($icon, 'w-4 h-4 shrink-0')
                                    <span class="min-w-0 truncate">{{ $label }}</span>
                                    @if ($meta)<span class="gp-meta" @if ($tone) style="color: {{ $toneColor[$tone] }}" @endif>{{ $meta }}</span>@endif
                                </a>
                            @endforeach
                        @endif
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
                    <button type="button" x-show="collapsed" style="display: none" class="gp-desk -ml-1.5 inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]" @click="toggle()" title="Seitenleiste ausklappen ( [ )">
                        @svg('heroicon-o-bars-3', 'w-5 h-5')
                    </button>
                    <nav class="flex min-w-0 flex-1 items-center gap-1.5 text-sm">
                        @if ($section && ! $notFound)
                            <a href="{{ $link() }}" wire:navigate class="shrink-0 text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Übersicht</a>
                            @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                            @if ($sectionPage && ! empty($sectionPage['page']))
                                <a href="{{ $link($section) }}" wire:navigate class="min-w-0 truncate text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">{{ $sectionPage['title'] }}</a>
                                @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                                <span class="min-w-0 truncate font-medium">{{ $sectionPage['page']['title'] }}</span>
                            @else
                                <span class="min-w-0 truncate font-medium">{{ \Platform\Organization\Livewire\Share\PublicShare::SECTIONS[$section] ?? ($sectionPage['title'] ?? '') }}</span>
                            @endif
                        @elseif ($activeSlug || $notFound)
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
                    @elseif ($sectionPage)
                        @include('organization::livewire.share._detail', ['detail' => $sectionPage, 'detailLink' => fn (?string $sub = null) => $link($section, null, $sub)])
                    @elseif ($section === 'leistung')
                        <div class="gp-wrap py-8 md:py-10">
                            <h1 class="text-2xl font-bold tracking-tight">Leistung</h1>
                            <p class="mt-1 text-sm text-[color:var(--nx-muted)]">Alle gebuchten Zeiten — wer wann woran gearbeitet hat.</p>
                            @include('organization::livewire.share._work', ['work' => $data['work'], 'compact' => false])
                        </div>
                    @elseif ($section === 'rechnungen')
                        <div class="gp-wrap py-8 md:py-10">
                            <h1 class="text-2xl font-bold tracking-tight">Rechnungen &amp; Zahlungen</h1>
                            <p class="mt-1 text-sm text-[color:var(--nx-muted)]">Alle Rechnungen mit Beleg, ihr Bezahlstand und die eingegangenen Zahlungen.</p>
                            @include('organization::livewire.share._finance', ['finance' => $data['finance'], 'compact' => false])
                        </div>
                    @elseif ($section === 'aktivitaet')
                        <div class="gp-wrap py-8 md:py-10">
                            <h1 class="text-2xl font-bold tracking-tight">Aktivität</h1>
                            <p class="mt-1 text-sm text-[color:var(--nx-muted)]">Was in den letzten 60 Tagen passiert ist — Arbeit, erledigte Schritte, Canvases, Zahlungen.</p>
                            <div class="mt-6 max-w-5xl">@include('organization::livewire.share._activity', ['events' => $data['activity'], 'limit' => null])</div>
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
