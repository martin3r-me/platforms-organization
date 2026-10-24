{{--
    Kunden-Tor — geteilte Seite eines Knotens. Eigene, ruhige Gestaltung (unabhängig vom
    App-Layout), hell, mobil zuerst. Inhalte kommen aus EntityShareService::pageData():
    featured (Typen mit Fortschritt, z. B. Projekte), sections (weitere Typen), areas, kpis.
--}}
@php
    $de = fn ($d, $f = 'D. MMMM YYYY') => $d ? $d->copy()->locale('de')->isoFormat($f) : null;
    $ago = fn ($d) => $d ? ($d->isPast() && $d->diffInMinutes(now()) < 1 ? 'gerade eben' : $d->copy()->locale('de')->diffForHumans()) : null;
    $stateTone = ['active' => 'run', 'done' => 'done', 'paused' => 'pause', 'planned' => 'plan'];
    $initial = mb_strtoupper(mb_substr($teamName ?: 'P', 0, 1));
@endphp
<div class="gate">
    <style>
        .gate { --g-bg: #f6f5f1; --g-card: #ffffff; --g-text: #2f2e2a; --g-muted: #74726c; --g-faint: #a19f99;
                --g-line: rgba(47,46,42,.08); --g-line-2: rgba(47,46,42,.14); --g-accent: #2f2e2a;
                --g-run: #2f6fdb; --g-done: #2f9e5b; --g-pause: #b7791f; --g-plan: #8a8780;
                min-height: 100vh; background: var(--g-bg); color: var(--g-text);
                font-family: ui-sans-serif, -apple-system, 'Segoe UI', 'Inter', Helvetica, Arial, sans-serif;
                -webkit-font-smoothing: antialiased; font-size: 15px; line-height: 1.55; }
        .gate *, .gate *::before, .gate *::after { box-sizing: border-box; }
        .gate-wrap { max-width: 1040px; margin: 0 auto; padding: 0 20px; }
        .gate-top { border-bottom: 1px solid var(--g-line); background: rgba(255,255,255,.6); backdrop-filter: blur(8px); position: sticky; top: 0; z-index: 10; }
        .gate-top .gate-wrap { display: flex; align-items: center; justify-content: space-between; height: 56px; gap: 16px; }
        .gate-brand { display: inline-flex; align-items: center; gap: 10px; font-weight: 600; font-size: 14px; min-width: 0; }
        .gate-brand span:last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .gate-mark { width: 28px; height: 28px; border-radius: 8px; background: var(--g-accent); color: #fff; display: inline-flex;
                     align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
        .gate-stamp { font-size: 12px; color: var(--g-faint); white-space: nowrap; }

        .gate-hero { padding: 48px 0 28px; }
        .gate-eyebrow { font-size: 12px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--g-faint); }
        .gate-h1 { margin: 6px 0 0; font-size: clamp(28px, 4.4vw, 40px); line-height: 1.15; font-weight: 700; letter-spacing: -.02em; }
        .gate-message { margin: 20px 0 0; max-width: 680px; padding: 16px 18px; border-radius: 12px; background: var(--g-card);
                        border: 1px solid var(--g-line); white-space: pre-line; color: var(--g-text); }
        .gate-from { margin-top: 8px; font-size: 13px; color: var(--g-muted); }

        .gate-kpis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 28px; }
        @media (min-width: 820px) { .gate-kpis { grid-template-columns: 1.4fr 1fr 1fr 1fr; } }
        .gate-kpi { background: var(--g-card); border: 1px solid var(--g-line); border-radius: 14px; padding: 16px 18px; min-width: 0; }
        .gate-kpi-label { font-size: 12px; color: var(--g-muted); }
        .gate-kpi-value { font-size: 26px; font-weight: 700; letter-spacing: -.02em; margin-top: 2px; font-variant-numeric: tabular-nums; }
        .gate-kpi-hint { font-size: 12px; color: var(--g-faint); margin-top: 2px; }
        .gate-kpi-main { grid-column: span 2; display: flex; align-items: center; gap: 16px; }
        @media (min-width: 820px) { .gate-kpi-main { grid-column: auto; } }
        @media (max-width: 819px) { .gate-kpi:last-child { grid-column: span 2; } }
        .gate-ring { --p: 0; width: 64px; height: 64px; border-radius: 50%; flex-shrink: 0;
                     background: conic-gradient(var(--g-done) calc(var(--p) * 1%), rgba(47,46,42,.08) 0); display: grid; place-items: center; }
        .gate-ring::after { content: attr(data-label); width: 50px; height: 50px; border-radius: 50%; background: var(--g-card);
                            display: grid; place-items: center; font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; }

        .gate-main { padding-bottom: 64px; }
        .gate-h2 { display: flex; align-items: baseline; gap: 8px; margin: 36px 0 14px; font-size: 18px; font-weight: 650; letter-spacing: -.01em; }
        .gate-h2 small { font-size: 13px; font-weight: 500; color: var(--g-faint); }

        .gate-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; }
        @media (min-width: 900px) { .gate-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .gate-card { background: var(--g-card); border: 1px solid var(--g-line); border-radius: 16px; padding: 20px; min-width: 0;
                     box-shadow: 0 1px 2px rgba(15,15,15,.03); }
        .gate-card.is-done { background: rgba(255,255,255,.6); }
        .gate-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .gate-card-title { font-size: 17px; font-weight: 650; line-height: 1.3; letter-spacing: -.01em; overflow-wrap: anywhere; }
        .gate-card-sub { font-size: 13px; color: var(--g-muted); margin-top: 3px; }
        .gate-pill { flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px;
                     font-size: 12px; font-weight: 600; white-space: nowrap; background: color-mix(in srgb, var(--tone) 12%, transparent); color: var(--tone); }
        .gate-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .tone-run { --tone: var(--g-run); } .tone-done { --tone: var(--g-done); } .tone-pause { --tone: var(--g-pause); } .tone-plan { --tone: var(--g-plan); }

        .gate-progress { margin-top: 18px; }
        .gate-progress-row { display: flex; justify-content: space-between; align-items: baseline; font-size: 13px; color: var(--g-muted); margin-bottom: 6px; }
        .gate-progress-row strong { font-size: 15px; color: var(--g-text); font-variant-numeric: tabular-nums; }
        .gate-bar { height: 8px; border-radius: 999px; background: rgba(47,46,42,.07); overflow: hidden; }
        .gate-bar > span { display: block; height: 100%; border-radius: 999px; background: var(--g-done); transition: width .6s ease; }

        .gate-cols { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; margin-top: 18px; }
        @media (min-width: 560px) { .gate-cols { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .gate-col-label { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--g-faint); margin-bottom: 6px; }
        .gate-steps { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
        .gate-steps li { display: flex; gap: 8px; align-items: flex-start; font-size: 14px; line-height: 1.4; min-width: 0; }
        .gate-steps li span.t { flex: 1; min-width: 0; overflow-wrap: anywhere; }
        .gate-steps li span.d { flex-shrink: 0; font-size: 12px; color: var(--g-faint); padding-top: 1px; }
        .gate-steps .ic { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }
        .gate-steps .ic-done { color: var(--g-done); }
        .gate-steps .ic-next { color: var(--g-faint); }
        .gate-steps .is-empty { color: var(--g-faint); font-size: 13px; }
        .gate-card-foot { margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--g-line); font-size: 12px; color: var(--g-faint);
                          display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }

        .gate-list { background: var(--g-card); border: 1px solid var(--g-line); border-radius: 14px; overflow: hidden; }
        .gate-row { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-top: 1px solid var(--g-line); min-width: 0; }
        .gate-row:first-child { border-top: 0; }
        .gate-row-title { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; }
        .gate-row-meta { font-size: 12px; color: var(--g-faint); white-space: nowrap; }
        .gate-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--tone); flex-shrink: 0; }

        .gate-areas { display: flex; flex-wrap: wrap; gap: 8px; }
        .gate-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: var(--g-card);
                     border: 1px solid var(--g-line); font-size: 13px; }
        .gate-chip small { color: var(--g-faint); font-size: 12px; }

        .gate-empty { margin-top: 36px; text-align: center; padding: 48px 20px; border: 1px dashed var(--g-line-2); border-radius: 16px; color: var(--g-muted); }
        .gate-foot { border-top: 1px solid var(--g-line); padding: 24px 0 40px; font-size: 12px; color: var(--g-faint); }
        .gate-foot .gate-wrap { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }

        /* Zugang / Hinweise */
        .gate-center { min-height: calc(100vh - 56px); display: grid; place-items: center; padding: 32px 20px; }
        .gate-box { width: 100%; max-width: 420px; background: var(--g-card); border: 1px solid var(--g-line); border-radius: 18px; padding: 28px; }
        .gate-box h1 { margin: 6px 0 8px; font-size: 22px; line-height: 1.25; font-weight: 700; letter-spacing: -.01em; }
        .gate-box p { margin: 0; color: var(--g-muted); font-size: 14px; }
        .gate-input { width: 100%; margin-top: 18px; padding: 11px 14px; border-radius: 10px; border: 1px solid var(--g-line-2); font-size: 15px;
                      background: #fff; color: var(--g-text); outline: none; }
        .gate-input:focus { border-color: var(--g-accent); box-shadow: 0 0 0 3px rgba(47,46,42,.08); }
        .gate-btn { width: 100%; margin-top: 10px; padding: 11px 14px; border: 0; border-radius: 10px; background: var(--g-accent); color: #fff;
                    font-size: 15px; font-weight: 600; cursor: pointer; }
        .gate-btn:hover { background: #1d1c19; }
        .gate-error { margin-top: 6px; font-size: 13px; color: #c0392b; }
        .gate-ok { display: flex; gap: 10px; align-items: flex-start; margin-top: 16px; padding: 12px 14px; border-radius: 10px;
                   background: color-mix(in srgb, var(--g-done) 10%, transparent); color: var(--g-text); font-size: 14px; }
    </style>

    <header class="gate-top">
        <div class="gate-wrap">
            <span class="gate-brand"><span class="gate-mark">{{ $initial }}</span><span>{{ $teamName }}</span></span>
            @if ($status === 'ok')
                <span class="gate-stamp">Stand {{ $de(now(), 'D. MMM, HH:mm') }} Uhr</span>
            @endif
        </div>
    </header>

    @if ($status === 'request')
        {{-- Nur Eingeladene: persönlichen Link anfordern --}}
        <div class="gate-center">
            <div class="gate-box">
                <div class="gate-eyebrow">Persönlicher Zugang</div>
                <h1>{{ $title }}</h1>
                @if ($requested)
                    <div class="gate-ok">
                        @svg('heroicon-o-envelope', 'w-5 h-5 flex-shrink-0')
                        <span>Falls diese Adresse eingeladen ist, ist Ihr persönlicher Link jetzt unterwegs. Schauen Sie in Ihr Postfach.</span>
                    </div>
                @else
                    <p>Dieser Bereich ist für eingeladene Personen. Geben Sie Ihre E-Mail-Adresse ein – wir schicken Ihnen Ihren persönlichen Link.</p>
                    <form wire:submit="requestAccess">
                        <input type="email" wire:model="email" class="gate-input" placeholder="name@firma.de" autocomplete="email" required>
                        @error('email') <div class="gate-error">{{ $message }}</div> @enderror
                        <button type="submit" class="gate-btn" wire:loading.attr="disabled">Link zuschicken</button>
                    </form>
                @endif
            </div>
        </div>
    @elseif ($status !== 'ok')
        <div class="gate-center">
            <div class="gate-box">
                <div class="gate-eyebrow">Freigabe</div>
                <h1>Dieser Link ist nicht mehr aktiv</h1>
                <p>Die Freigabe wurde beendet oder ist abgelaufen. Bitte wenden Sie sich an Ihre Ansprechperson – sie schickt Ihnen gern einen neuen Link.</p>
            </div>
        </div>
    @else
        @php
            $k = $data['kpis'];
        @endphp
        <section class="gate-hero">
            <div class="gate-wrap">
                <div class="gate-eyebrow">{{ $entity->type?->name ?? 'Ihr Bereich' }}</div>
                <h1 class="gate-h1">{{ $title }}</h1>
                @if ($share->message)
                    <div class="gate-message">{{ $share->message }}</div>
                    @if ($senderName)<div class="gate-from">— {{ $senderName }}</div>@endif
                @endif

                @if ($data['featured'])
                    <div class="gate-kpis">
                        <div class="gate-kpi gate-kpi-main">
                            <div class="gate-ring" style="--p: {{ $k['progress'] ?? 0 }}" data-label="{{ $k['progress'] !== null ? $k['progress'] . ' %' : '–' }}"></div>
                            <div>
                                <div class="gate-kpi-label">Gesamtfortschritt</div>
                                <div class="gate-kpi-hint">
                                    @if ($k['steps_total'])
                                        {{ $k['steps_done'] }} von {{ $k['steps_total'] }} Schritten erledigt
                                    @else
                                        Noch keine Schritte geplant
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="gate-kpi">
                            <div class="gate-kpi-label">Laufend</div>
                            <div class="gate-kpi-value">{{ $k['active'] }}</div>
                        </div>
                        <div class="gate-kpi">
                            <div class="gate-kpi-label">Abgeschlossen</div>
                            <div class="gate-kpi-value">{{ $k['done'] }}</div>
                        </div>
                        <div class="gate-kpi">
                            <div class="gate-kpi-label">Nächster Termin</div>
                            <div class="gate-kpi-value" style="font-size: 20px; margin-top: 6px">{{ $k['next_due'] ? $de($k['next_due'], 'D. MMM') : '–' }}</div>
                            @if ($k['next_due'])<div class="gate-kpi-hint">{{ $ago($k['next_due']) }}</div>@endif
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <main class="gate-main">
            <div class="gate-wrap">
                {{-- Typen mit Fortschritt (z. B. Projekte) --}}
                @foreach ($data['featured'] as $group)
                    <h2 class="gate-h2">{{ $group['label'] }} <small>{{ count($group['items']) }}</small></h2>
                    <div class="gate-grid">
                        @foreach ($group['items'] as $item)
                            @php
                                $tone = $stateTone[$item['state'] ?? 'active'] ?? 'run';
                                $p = $item['progress'] ?? null;
                                $pct = ($p && $p['total']) ? (int) round($p['done'] / $p['total'] * 100) : null;
                                $hasSteps = array_key_exists('recent', $item) || array_key_exists('next', $item);
                            @endphp
                            <article @class(['gate-card', 'is-done' => ($item['state'] ?? '') === 'done'])>
                                <div class="gate-card-head">
                                    <div style="min-width: 0">
                                        <div class="gate-card-title">{{ $item['title'] }}</div>
                                        @if (! empty($item['area']) || ! empty($item['subtitle']))
                                            <div class="gate-card-sub">{{ collect([$item['area'] ?? null, $item['subtitle'] ?? null])->filter()->implode(' · ') }}</div>
                                        @endif
                                    </div>
                                    <span class="gate-pill tone-{{ $tone }}">{{ $item['state_label'] ?? 'Läuft' }}</span>
                                </div>

                                @if ($p && $p['total'])
                                    <div class="gate-progress">
                                        <div class="gate-progress-row"><span>{{ $p['done'] }} von {{ $p['total'] }} Schritten</span><strong>{{ $pct }} %</strong></div>
                                        <div class="gate-bar"><span style="width: {{ $pct }}%"></span></div>
                                    </div>
                                @endif

                                @if ($hasSteps && (($item['recent'] ?? []) || ($item['next'] ?? [])))
                                    <div class="gate-cols">
                                        <div>
                                            <div class="gate-col-label">Zuletzt erledigt</div>
                                            <ul class="gate-steps">
                                                @forelse ($item['recent'] ?? [] as $step)
                                                    <li>@svg('heroicon-s-check-circle', 'ic ic-done')<span class="t">{{ $step['title'] }}</span>@if (! empty($step['at']))<span class="d">{{ $de($step['at'], 'D. MMM') }}</span>@endif</li>
                                                @empty
                                                    <li class="is-empty">Noch nichts erledigt</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                        <div>
                                            <div class="gate-col-label">Als Nächstes</div>
                                            <ul class="gate-steps">
                                                @forelse ($item['next'] ?? [] as $step)
                                                    <li>@svg('heroicon-o-arrow-right-circle', 'ic ic-next')<span class="t">{{ $step['title'] }}</span>@if (! empty($step['due']))<span class="d">bis {{ $de($step['due'], 'D. MMM') }}</span>@endif</li>
                                                @empty
                                                    <li class="is-empty">Alles erledigt</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    </div>
                                @endif

                                @if (! empty($item['updated_at']) || ! empty($item['due']))
                                    <div class="gate-card-foot">
                                        <span>@if (! empty($item['updated_at']))Zuletzt bewegt {{ $ago($item['updated_at']) }}@endif</span>
                                        @if (! empty($item['due']))<span>Ziel: {{ $de($item['due']) }}</span>@endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endforeach

                {{-- Weitere Inhalte --}}
                @foreach ($data['sections'] as $group)
                    <h2 class="gate-h2">{{ $group['label'] }} <small>{{ count($group['items']) }}</small></h2>
                    <div class="gate-list">
                        @foreach ($group['items'] as $item)
                            <div class="gate-row">
                                <span class="gate-dot tone-{{ $stateTone[$item['state'] ?? 'active'] ?? 'run' }}"></span>
                                <span class="gate-row-title">{{ $item['title'] }}</span>
                                @if (! empty($item['area']))<span class="gate-row-meta">{{ $item['area'] }}</span>@endif
                                <span class="gate-row-meta">{{ $item['state_label'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                @if ($data['areas'])
                    <h2 class="gate-h2">Bereiche <small>{{ count($data['areas']) }}</small></h2>
                    <div class="gate-areas">
                        @foreach ($data['areas'] as $area)
                            <span class="gate-chip">{{ $area['name'] }}@if ($area['type'])<small>{{ $area['type'] }}</small>@endif</span>
                        @endforeach
                    </div>
                @endif

                @if (! $data['featured'] && ! $data['sections'])
                    <div class="gate-empty">
                        @svg('heroicon-o-sparkles', 'w-6 h-6')
                        <div style="margin-top: 8px; font-weight: 600; color: var(--g-text)">Hier entsteht gerade etwas</div>
                        <div style="font-size: 14px; margin-top: 4px">Sobald es Neuigkeiten gibt, sehen Sie sie an dieser Stelle.</div>
                    </div>
                @endif
            </div>
        </main>

        <footer class="gate-foot">
            <div class="gate-wrap">
                <span>Geteilt von {{ $senderName ? $senderName . ' · ' : '' }}{{ $teamName }}</span>
                <span>{{ $recipient ? 'Persönlicher Link für ' . $recipient->email : 'Immer aktuell – diese Seite zeigt den Live-Stand' }}</span>
            </div>
        </footer>
    @endif
</div>
