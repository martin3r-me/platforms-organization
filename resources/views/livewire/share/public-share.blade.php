{{--
    Kunden-Tor — geteilte Seite eines Knotens im nx-Look der Plattform: links die Navigation
    (wie die Hauptsidebar), rechts Übersicht oder Detailseite eines Objekts. Inhalte kommen aus
    EntityShareService::pageData() (Übersicht + Navigation) bzw. ::detailData() (Modul-Detail).
--}}
@php
    $de = fn ($d, $f = 'D. MMMM YYYY') => $d ? $d->copy()->locale('de')->isoFormat($f) : null;
    $ago = fn ($d) => $d ? ($d->isPast() && $d->diffInMinutes(now()) < 1 ? 'gerade eben' : $d->copy()->locale('de')->diffForHumans()) : null;
    $duePhrase = function ($d) {
        if (! $d) {
            return null;
        }
        $diff = (int) now()->startOfDay()->diffInDays($d->copy()->startOfDay(), false);

        return match (true) {
            $diff < -1 => 'seit ' . abs($diff) . ' Tagen fällig',
            $diff === -1 => 'seit gestern fällig',
            $diff === 0 => 'heute',
            $diff === 1 => 'morgen',
            $diff < 7 => 'in ' . $diff . ' Tagen',
            default => $d->copy()->locale('de')->isoFormat('D. MMM'),
        };
    };
    $badge = ['active' => 'info', 'done' => 'success', 'paused' => 'warning', 'planned' => 'neutral'];
    $dot = ['active' => 'var(--nx-info)', 'done' => 'var(--nx-success)', 'paused' => 'var(--nx-warning)', 'planned' => 'var(--nx-faint)'];
    $pct = fn ($p) => ($p && ($p['total'] ?? 0)) ? (int) round($p['done'] / $p['total'] * 100) : null;
    $initial = mb_strtoupper(mb_substr($teamName ?: 'P', 0, 1));
@endphp

<div class="min-h-screen bg-[color:var(--nx-surface)] text-[color:var(--nx-text)] antialiased" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    {{-- Navigation ab Tablet fest neben dem Inhalt (eigene Regel, unabhängig vom Tailwind-Build) --}}
    <style>@media (min-width: 768px) { .gate-nav { position: sticky; top: 0; height: 100vh; transform: none !important; } }</style>

    @if ($status !== 'ok')
        {{-- Zugang anfordern / nicht mehr aktiv --}}
        <div class="min-h-screen bg-[color:var(--nx-bg)] grid place-items-center px-4 py-10">
            <div class="w-full max-w-sm">
                <div class="flex items-center gap-2 mb-6 text-sm font-semibold">
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-[6px] bg-[color:var(--nx-accent)] text-[color:var(--nx-on-accent)] text-xs font-bold">{{ $initial }}</span>
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
        <div class="flex min-h-screen">
            {{-- Navigation — wie die Hauptsidebar; mobil als Schublade --}}
            <div x-show="nav" x-transition.opacity class="fixed inset-0 z-30 bg-black/20 md:hidden" @click="nav = false" style="display: none"></div>
            <aside class="gate-nav fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] transition-transform"
                   :class="nav ? 'translate-x-0' : '-translate-x-full'">
                <div class="flex h-11 items-center gap-2 border-b border-[color:var(--nx-line)] px-3">
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-[6px] bg-[color:var(--nx-accent)] text-[11px] font-bold text-[color:var(--nx-on-accent)]">{{ $initial }}</span>
                    <span class="truncate text-sm font-semibold">{{ $teamName }}</span>
                </div>

                <nav class="flex-1 overflow-y-auto px-2 py-3 text-sm">
                    <a href="{{ $link() }}" wire:navigate
                       @class(['flex items-center gap-2 rounded-[6px] px-2 py-1.5 transition-colors',
                               'bg-[color:var(--nx-active)] font-medium' => ! $activeSlug,
                               'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]' => $activeSlug])>
                        @svg('heroicon-o-home', 'w-4 h-4 shrink-0')
                        <span class="truncate">Übersicht</span>
                    </a>

                    @foreach ($data['nav'] as $group)
                        <div class="mt-5 mb-1 px-2 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $group['label'] }}</div>
                        @foreach ($group['items'] as $item)
                            @php
                                $isActive = $activeSlug === $group['slug'] && $activeId === (int) $item['id'];
                                $itemPct = $pct($item['progress'] ?? null);
                            @endphp
                            <a href="{{ $link($group['slug'], $item['id']) }}" wire:navigate wire:key="nav-{{ $group['slug'] }}-{{ $item['id'] }}"
                               @class(['group flex items-center gap-2 rounded-[6px] px-2 py-1.5 transition-colors',
                                       'bg-[color:var(--nx-active)] font-medium' => $isActive,
                                       'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] hover:text-[color:var(--nx-text)]' => ! $isActive])>
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $dot[$item['state'] ?? 'active'] ?? 'var(--nx-faint)' }}"></span>
                                <span class="min-w-0 flex-1 truncate">{{ $item['title'] }}</span>
                                @if ($itemPct !== null)
                                    <span class="shrink-0 text-[11px] tabular-nums text-[color:var(--nx-faint)]">{{ $itemPct }} %</span>
                                @endif
                            </a>
                        @endforeach
                    @endforeach
                </nav>

                <div class="border-t border-[color:var(--nx-line)] px-3 py-3 text-[11px] leading-relaxed text-[color:var(--nx-faint)]">
                    @if ($recipient)
                        Persönlicher Zugang für<br><span class="text-[color:var(--nx-muted)]">{{ $recipient->email }}</span>
                    @else
                        Immer aktuell – diese Seite zeigt den Live-Stand.
                    @endif
                </div>
            </aside>

            {{-- Inhalt --}}
            <div class="flex min-w-0 flex-1 flex-col" wire:poll.visible.120s>
                <header class="sticky top-0 z-20 flex h-11 items-center gap-2 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-4">
                    <button type="button" class="-ml-1 inline-flex h-8 w-8 items-center justify-center rounded-[6px] text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)] md:hidden" @click="nav = true" title="Navigation">
                        @svg('heroicon-o-bars-3', 'w-5 h-5')
                    </button>
                    <nav class="flex min-w-0 flex-1 items-center gap-1.5 text-sm">
                        @if ($activeSlug || $notFound)
                            <a href="{{ $link() }}" wire:navigate class="shrink-0 text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Übersicht</a>
                            @if ($detail)
                                @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                                @if (! empty($detail['page']))
                                    <a href="{{ $link($activeSlug, $activeId) }}" wire:navigate class="truncate text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">{{ $detail['title'] }}</a>
                                    @svg('heroicon-o-chevron-right', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                                    <span class="truncate font-medium">{{ $detail['page']['title'] }}</span>
                                @else
                                    <span class="truncate font-medium">{{ $detail['title'] }}</span>
                                @endif
                            @endif
                        @else
                            <span class="truncate font-medium">{{ $title }}</span>
                        @endif
                    </nav>
                    <span class="hidden shrink-0 text-xs text-[color:var(--nx-faint)] sm:inline">Stand {{ $de(now(), 'D. MMM, HH:mm') }} Uhr</span>
                </header>

                <main class="flex-1">
                    @if ($notFound)
                        <div class="mx-auto max-w-md px-4 py-20 text-center">
                            @svg('heroicon-o-eye-slash', 'mx-auto w-8 h-8 text-[color:var(--nx-faint)]')
                            <div class="mt-3 font-medium">Nicht (mehr) Teil dieser Freigabe</div>
                            <p class="mt-1 text-sm text-[color:var(--nx-muted)]">Der Inhalt wurde entfernt oder ist hier nicht sichtbar.</p>
                            <a href="{{ $link() }}" wire:navigate class="mt-4 inline-flex rounded-[6px] border border-[color:var(--nx-line-strong)] px-3 py-1.5 text-sm hover:bg-[color:var(--nx-hover)]">Zur Übersicht</a>
                        </div>
                    @elseif ($detail)
                        @include('organization::livewire.share._detail', ['detail' => $detail, 'detailLink' => fn (?string $sub = null) => $link($activeSlug, $activeId, $sub)])
                    @else
                        @include('organization::livewire.share._overview')
                    @endif
                </main>

                <footer class="border-t border-[color:var(--nx-line)] px-4 py-4 text-xs text-[color:var(--nx-faint)] sm:px-8">
                    Geteilt von {{ $senderName ? $senderName . ' · ' : '' }}{{ $teamName }}
                </footer>
            </div>
        </div>
    @endif
</div>
