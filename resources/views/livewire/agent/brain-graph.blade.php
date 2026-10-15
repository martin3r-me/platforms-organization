<div class="space-y-6">

    {{-- Kopf + Modell-Erklärung --}}
    <div class="rounded-lg border border-[var(--ui-border)] bg-[var(--ui-surface)] p-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <h3 class="text-sm font-semibold text-[var(--ui-primary)]">Gehirn — das Innenleben eines engram</h3>
            <div class="text-[11px] text-[var(--ui-muted)] text-right">
                @if ($hasSnapshot)
                    Snapshot {{ $snapshotAt->diffForHumans() }}
                    <span class="block">{{ $episodeCount }} Episoden · {{ $factCount }} Fakten · {{ $edgeCount }} Kanten · {{ $skillCount }} Skills</span>
                @else
                    <span class="text-amber-700">Noch kein Gehirn-Push gemeldet</span>
                @endif
            </div>
        </div>
        <p class="text-[11px] text-[var(--ui-muted)] mt-1 leading-relaxed">
            Wie ein biologisches Gehirn in Regionen: <b>wahrnehmen</b> (Episoden) · <b>wissen</b> (Neocortex-Graph) · <b>können</b> (Skills) ·
            <b>fühlen</b> (Affekt) · <b>sich kennen</b> (Selbstmodell) · <b>entscheiden</b> (Gate) · <b>ruhen</b> (Schlaf). Alles gepusht,
            host-agnostisch — egal wo der Agent läuft. Zeitgestempelter Snapshot, kein Live-Zwang.
        </p>
        @unless ($hasSnapshot)
            <p class="text-[11px] text-amber-700 mt-2">Die Regionen unten sind bereits <b>angedeutet</b> — sie füllen sich mit dem nächsten Push (~10 min), sobald der Daemon läuft.</p>
        @endunless
    </div>

    {{-- Effizienz: abgestufter Kortex (Anstrengungs-Governor) --}}
    <div class="rounded-lg border border-[var(--ui-border)] p-4">
        <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Effizienz — abgestufter Kortex</h4>
        <p class="text-[11px] text-[var(--ui-muted)] mb-2">Nicht flach denken: <b>System 1</b> (billiges Modell) für Routine, <b>System 2</b> (das teure) nur für Neues/Riskantes/Unklares/Angespanntes. Gewählt aus Lage + Affekt. Das Change-Gate spart „Welt unverändert → 0", dies ist der mittlere Gang.</p>
        @if ($effort && ($effort['at'] ?? 0) > 0)
            @php
                $sys = (int) ($effort['system'] ?? 2);
                $score = (float) ($effort['score'] ?? 0);
            @endphp
            <div class="flex items-center gap-3 flex-wrap text-[12px]">
                <span class="shrink-0 rounded px-2 py-0.5 text-[11px] font-semibold {{ $sys === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700' }}">System {{ $sys }} · {{ $sys === 1 ? 'billig/schnell' : 'teuer/gründlich' }}</span>
                <span class="text-[var(--ui-muted)]">Modell: <span class="font-semibold text-[var(--ui-fg)]">{{ ($effort['tier'] ?? '') !== '' ? $effort['tier'] : 'default (bestes)' }}</span></span>
                <div class="flex items-center gap-2 flex-1 min-w-[160px]">
                    <span class="text-[var(--ui-muted)] shrink-0">Anstrengung</span>
                    <div class="flex-1 h-2 rounded bg-[var(--ui-muted-5,#0001)] overflow-hidden">
                        <div class="h-full rounded" style="width: {{ round($score * 100) }}%; background: hsl({{ round(140 - $score * 140) }},65%,50%);"></div>
                    </div>
                    <span class="w-8 text-right font-semibold text-[var(--ui-fg)]">{{ number_format($score, 2) }}</span>
                </div>
            </div>
            @if (! empty($effort['reason']))<div class="text-[11px] text-[var(--ui-muted)] mt-1">{{ $effort['reason'] }}</div>@endif
        @else
            <div class="text-[11px] text-[var(--ui-muted)] italic py-2">— noch nicht gemeldet. Zeigt pro Takt die gewählte Kortex-Stufe (System 1/2) + wie hart der Takt war. Routing ist opt-in (env <code>CHEAP_CORTEX</code>); ohne es läuft alles auf System 2.</div>
        @endif
    </div>

    {{-- 1) Wissensgraph (Neocortex) — navigierbarer 3D-Neuralraum, wie die Org-Mindmap --}}
    <div>
        <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Wissensgraph — Neocortex</h4>
        <p class="text-[11px] text-[var(--ui-muted)] mb-2">Das <b>Wissen</b>: Fakten als Knoten, typisierte Relationen als Kanten. Fällt im Schlaf aus den Episoden aus (Konsolidierung). Frei navigierbar — ziehen zum Drehen, scrollen zum Zoomen.</p>
        @if (count($graph['nodes']) === 0)
            <div class="rounded-lg border border-dashed border-[var(--ui-border)] p-6 text-center text-[11px] text-[var(--ui-muted)] italic">Noch keine Fakten konsolidiert — der Graph erscheint, sobald der Neocortex im Schlaf gewachsen ist.</div>
        @else
            <div class="relative rounded-lg border border-[var(--ui-border)] overflow-hidden" style="height: 70vh; min-height: 520px; background:#080c18;">
                <div wire:ignore id="braingraph-3d-{{ $entity->id }}" class="absolute inset-0"></div>
                <script type="application/json" id="bgdata-{{ $entity->id }}">@json($graph)</script>

                {{-- Navigation (Google-Maps-Stil, wie die Mindmap) --}}
                <div class="absolute top-3 right-3 z-20 flex gap-1">
                    <div class="bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-lg shadow-2xl flex overflow-hidden">
                        <button type="button" data-bg-zoom="in" class="w-9 h-9 flex items-center justify-center text-gray-300 hover:bg-white/10 hover:text-white transition-colors text-lg font-light border-r border-gray-700/50">+</button>
                        <button type="button" data-bg-zoom="out" class="w-9 h-9 flex items-center justify-center text-gray-300 hover:bg-white/10 hover:text-white transition-colors text-lg font-light">&minus;</button>
                    </div>
                    <button type="button" data-bg-fit title="Alles einpassen" class="bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-lg shadow-2xl w-9 h-9 flex items-center justify-center text-gray-400 hover:bg-white/10 hover:text-white transition-colors">
                        @svg('heroicon-o-arrows-pointing-in', 'w-4 h-4')
                    </button>
                    <button type="button" data-bg-fs title="Vollbild" class="bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-lg shadow-2xl w-9 h-9 flex items-center justify-center text-gray-400 hover:bg-white/10 hover:text-white transition-colors">
                        @svg('heroicon-o-arrows-pointing-out', 'w-4 h-4')
                    </button>
                </div>

                <div class="absolute bottom-2 left-3 z-10 text-[10px] text-gray-400/80 bg-gray-900/60 rounded px-1.5 py-0.5 pointer-events-none">
                    {{ count($graph['nodes']) }} Knoten · {{ count($graph['links']) }} Kanten · ziehen · scrollen · Knoten für Details
                </div>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- 2) Affekt (Psyche) --}}
        <div class="rounded-lg border border-[var(--ui-border)] p-4">
            <div class="flex items-center justify-between mb-1">
                <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide">Affekt — die Psyche</h4>
                @if ($mood)<span class="text-[11px] px-2 py-0.5 rounded-full bg-[var(--ui-muted-5,#0001)] text-[var(--ui-fg)]">{{ $mood }}</span>@endif
            </div>
            <p class="text-[11px] text-[var(--ui-muted)] mb-2">Der <b>Gemütszustand</b>: fünf Neuromodulatoren über dem festen Genom, die mit dem Erleben schwanken und homöostatisch verklingen. Beobachtend — steuert die Kognition (noch) nicht.</p>
            @if ($affect)
                @php
                    $psyche = [
                        ['Antrieb', 'antrieb', false], ['Stimmung', 'stimmung', false], ['Anspannung', 'anspannung', true],
                        ['Fokus', 'fokus', false], ['Erschöpfung', 'erschoepfung', true],
                    ];
                @endphp
                <div class="space-y-1.5">
                    @foreach ($psyche as $p)
                        @php $v = (float) ($affect[$p[1]] ?? 0); $good = $p[2] ? (1 - $v) : $v; @endphp
                        <div class="flex items-center gap-2 text-[12px]">
                            <span class="w-24 shrink-0 text-[var(--ui-muted)]">{{ $p[0] }}</span>
                            <div class="flex-1 h-2 rounded bg-[var(--ui-muted-5,#0001)] overflow-hidden">
                                <div class="h-full rounded" style="width: {{ round($v * 100) }}%; background: hsl({{ round($good * 140) }},65%,50%);"></div>
                            </div>
                            <span class="w-8 text-right font-semibold text-[var(--ui-fg)]">{{ number_format($v, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-[11px] text-[var(--ui-muted)] italic py-2">— noch nicht gemeldet. Zeigt Antrieb · Stimmung · Anspannung · Fokus · Erschöpfung.</div>
            @endif
        </div>

        {{-- 3) Kognitives Genom --}}
        <div class="rounded-lg border border-[var(--ui-border)] p-4">
            <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Kognitives Genom</h4>
            <p class="text-[11px] text-[var(--ui-muted)] mb-2">Die festen <b>Anlagen</b> — was für ein Geist: wie breit er erinnert, wie schnell er vergisst, wie früh er fragt.</p>
            @if ($genome)
                @php
                    $regler = [
                        ['Arbeitsgedächtnis-Spanne', $genome['working_capacity'] ?? null, ''],
                        ['Retention', $genome['retention_days'] ?? null, ' Tage'],
                        ['Salienz-Boden', isset($genome['retention_floor']) ? number_format($genome['retention_floor'], 2) : null, ''],
                        ['Salienz-Default', isset($genome['salience_threshold']) ? number_format($genome['salience_threshold'], 2) : null, ''],
                        ['Recall-Breite', $genome['recall_breadth'] ?? null, ' /Cue'],
                        ['Assoziations-Tiefe', $genome['assoc_hops'] ?? null, ' Hops'],
                        ['Erkennungs-Aggression', isset($genome['recognition_aggression']) ? number_format($genome['recognition_aggression'], 2) : null, ''],
                        ['Lernrate (Hebbian)', $genome['learning_gain'] ?? null, ''],
                        ['Schlaf-Schwelle', $genome['sleep_after_events'] ?? null, ' Events'],
                        ['Confidence-Schwelle', isset($genome['confidence_threshold']) ? number_format($genome['confidence_threshold'], 2) : null, ''],
                        ['Kortex-Tier', ($genome['cortex_tier'] ?? '') !== '' ? $genome['cortex_tier'] : 'auto', ''],
                    ];
                @endphp
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-[12px]">
                    @foreach ($regler as $r)
                        <dt class="text-[var(--ui-muted)]">{{ $r[0] }}</dt>
                        <dd class="text-right font-semibold text-[var(--ui-fg)]">{{ $r[1] ?? '—' }}{{ $r[1] !== null ? $r[2] : '' }}</dd>
                    @endforeach
                </dl>
            @else
                <div class="text-[11px] text-[var(--ui-muted)] italic py-2">— noch nicht gemeldet. Zeigt die ~10 Regler (Arbeitsspanne, Retention, Recall-Breite, Confidence-Schwelle …).</div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- 4) Zustandsvektor --}}
        <div class="rounded-lg border border-[var(--ui-border)] p-4">
            <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Zustandsvektor — die Lage</h4>
            <p class="text-[11px] text-[var(--ui-muted)] mb-2">Die <b>Situation</b>, in der zuletzt gehandelt wurde (7 Achsen) — steuert den situationsabhängigen Abruf.</p>
            @if ($state)
                @php
                    $achsen = [
                        ['Zeitdruck', 'zeitdruck'], ['Risiko', 'risiko'], ['Unsicherheit', 'unsicherheit'],
                        ['Ressourcen', 'ressourcen'], ['Sozial / Extern', 'sozial'], ['Verantwortung', 'verantwortung'], ['Exploration', 'exploration'],
                    ];
                @endphp
                <div class="space-y-1.5">
                    @foreach ($achsen as $a)
                        @php $v = (float) ($state[$a[1]] ?? 0); @endphp
                        <div class="flex items-center gap-2 text-[12px]">
                            <span class="w-28 shrink-0 text-[var(--ui-muted)]">{{ $a[0] }}</span>
                            <div class="flex-1 h-2 rounded bg-[var(--ui-muted-5,#0001)] overflow-hidden">
                                <div class="h-full rounded" style="width: {{ round($v * 100) }}%; background: hsl({{ round(140 - $v * 140) }},65%,50%);"></div>
                            </div>
                            <span class="w-8 text-right font-semibold text-[var(--ui-fg)]">{{ number_format($v, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-[11px] text-[var(--ui-muted)] italic py-2">— noch keine Lage geschätzt. Zeigt Zeitdruck · Risiko · Unsicherheit · Ressourcen · Sozial · Verantwortung · Exploration.</div>
            @endif
        </div>

        {{-- 5) Selbstmodell / Kalibrierung --}}
        <div class="rounded-lg border border-[var(--ui-border)] p-4">
            <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Selbstmodell — Kalibrierung</h4>
            <p class="text-[11px] text-[var(--ui-muted)] mb-2">Weiß der Agent, <b>was er weiß</b>? Über-/Unterkonfidenz, Brier, schwächste Themen — und ob die eigene Korrektur wirkt.</p>
            @if ($calibration && ($calibration['n'] ?? 0) > 0)
                @php
                    $gap = (float) ($calibration['gap'] ?? 0);
                    $lab = $gap > 0.05 ? 'überkonfident' : ($gap < -0.05 ? 'zu vorsichtig' : 'kalibriert');
                    $col = $gap > 0.05 ? 'text-amber-600' : ($gap < -0.05 ? 'text-sky-600' : 'text-emerald-600');
                @endphp
                <div class="text-[13px] space-y-1">
                    <div><span class="font-semibold {{ $col }}">Gap {{ sprintf('%+.2f', $gap) }} ({{ $lab }})</span>
                        <span class="text-[var(--ui-muted)]">· ECE {{ number_format($calibration['ece'] ?? 0, 2) }} · Brier {{ number_format($calibration['brier'] ?? 0, 2) }} · Schärfe {{ number_format($calibration['resolution'] ?? 0, 2) }} · {{ $calibration['n'] }} Paare</span>
                    </div>
                    @if (! empty($calibration['intervened']))
                        <div class="text-[var(--ui-muted)]">Seit Selbst-Korrektur: Gap {{ sprintf('%+.2f', $calibration['before_gap'] ?? 0) }} → {{ sprintf('%+.2f', $calibration['after_gap'] ?? 0) }}
                            @if (abs($calibration['after_gap'] ?? 0) < abs($calibration['before_gap'] ?? 0) - 0.02)<span class="text-emerald-600">✓ besser</span>@endif
                        </div>
                    @endif
                    @if (! empty($calibration['worst_themes']))
                        <div class="text-[var(--ui-muted)]">Schwächste Themen: @foreach ($calibration['worst_themes'] as $w)<span class="mr-2">{{ $w['subject'] ?? '?' }} ({{ number_format(($w['accuracy'] ?? 0) * 100, 0) }}%)</span>@endforeach</div>
                    @endif
                </div>
            @else
                <div class="text-[11px] text-[var(--ui-muted)] italic py-2">— noch keine Confidence-Paare. Erscheint, sobald der Agent Vorhersagen macht, die sich bestätigen/widerlegen.</div>
            @endif
        </div>
    </div>

    {{-- 6) Rhythmus + Change-Gate + Budget --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-[13px]">
        <div class="rounded-lg border border-[var(--ui-border)] p-4">
            <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Schlaf-Rhythmus</h4>
            <p class="text-[11px] text-[var(--ui-muted)] mb-2">Wann <b>ruht</b> er: Schlafdruck & Konsolidierung.</p>
            @if ($rhythm)
                <div class="text-[var(--ui-muted)]">Zyklen: <span class="font-semibold text-[var(--ui-fg)]">{{ $rhythm['sleeps'] ?? 0 }}</span></div>
                <div class="text-[var(--ui-muted)]">Druck: <span class="font-semibold text-[var(--ui-fg)]">{{ $rhythm['events_since_sleep'] ?? 0 }}</span> / {{ $rhythm['threshold'] ?? 0 }}</div>
                @if (! empty($rhythm['last_sleep_at']))<div class="text-[11px] text-[var(--ui-muted)] mt-1">zuletzt: {{ $rhythm['last_sleep_at'] }}</div>@endif
            @else
                <div class="text-[11px] text-[var(--ui-muted)] italic">— noch nicht gemeldet</div>
            @endif
        </div>
        <div class="rounded-lg border border-[var(--ui-border)] p-4">
            <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Change-Gate</h4>
            <p class="text-[11px] text-[var(--ui-muted)] mb-2"><b>Habituation</b>: nicht denken, wenn die Welt unverändert ist.</p>
            @if ($changeGate)
                <div class="text-[var(--ui-muted)]">Letzter Takt: <span class="font-semibold text-[var(--ui-fg)]">{{ ! empty($changeGate['last_noop']) ? 'NOOP' : 'gedacht' }}</span></div>
                @php $tu = (int) ($changeGate['thought_unix'] ?? 0); @endphp
                @if ($tu > 0)<div class="text-[11px] text-[var(--ui-muted)] mt-1">zuletzt gedacht: {{ \Illuminate\Support\Carbon::createFromTimestamp($tu)->diffForHumans() }}</div>@endif
            @else
                <div class="text-[11px] text-[var(--ui-muted)] italic">— noch nicht gemeldet</div>
            @endif
        </div>
        <div class="rounded-lg border border-[var(--ui-border)] p-4">
            <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Budget &amp; Nutzung</h4>
            <p class="text-[11px] text-[var(--ui-muted)] mb-2">Was der Kortex <b>kostet</b> (Token) und das Abo-Fenster.</p>
            @if ($usage && ! empty($usage['ok']))
                <div class="text-[var(--ui-muted)]">Abo: 5h <span class="font-semibold text-[var(--ui-fg)]">{{ number_format($usage['five_hour_pct'] ?? 0, 0) }}%</span> · 7d <span class="font-semibold text-[var(--ui-fg)]">{{ number_format($usage['seven_day_pct'] ?? 0, 0) }}%</span></div>
            @endif
            @if ($budget)
                <div class="text-[var(--ui-muted)]">Token: <span class="font-semibold text-[var(--ui-fg)]">{{ number_format($budget['tokens_today'] ?? 0) }}</span> heute · {{ number_format($budget['tokens_7d'] ?? 0) }} 7d</div>
                <div class="text-[11px] text-[var(--ui-muted)]">Burn {{ number_format($budget['burn_per_hour'] ?? 0) }}/h</div>
            @endif
            @if (! $budget && ! ($usage && ! empty($usage['ok'])))
                <div class="text-[11px] text-[var(--ui-muted)] italic">— noch nicht gemeldet</div>
            @endif
        </div>
    </div>

    {{-- 7) Episoden --}}
    <div class="rounded-lg border border-[var(--ui-border)] p-4">
        <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Episoden — das autobiografische Gedächtnis</h4>
        <p class="text-[11px] text-[var(--ui-muted)] mb-2">Was der Agent <b>erlebt</b> hat (jüngste zuerst), gewichtet nach Salienz. Der Rohstoff, aus dem im Schlaf Wissen wird.</p>
        @if (count($episodes))
            <ul class="space-y-1.5 max-h-72 overflow-y-auto">
                @foreach ($episodes as $e)
                    <li class="text-[12px] flex items-start gap-2">
                        <span class="mt-0.5 shrink-0 inline-block w-1.5 h-1.5 rounded-full" style="background: hsl({{ round(140 - (float)($e['sal'] ?? 0) * 140) }},65%,50%);" title="Salienz {{ number_format((float)($e['sal'] ?? 0), 2) }}"></span>
                        <span class="break-words">{{ $e['gist'] ?? '' }}@if (! empty($e['entities']))<span class="text-[10px] text-[var(--ui-muted)]"> · {{ $e['entities'] }}</span>@endif</span>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="text-[11px] text-[var(--ui-muted)] italic py-2">— noch keine Episoden. Jeder echte Tick (kein NOOP) schreibt eine.</div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- 8a) Skills --}}
        <div class="rounded-lg border border-[var(--ui-border)] p-4">
            <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Skills — prozedural</h4>
            <p class="text-[11px] text-[var(--ui-muted)] mb-2">Was der Agent <b>kann</b> — eingeschliffene Handgriffe, gehärtet durch Wiederholung.</p>
            @if (count($skills))
                <ul class="space-y-1 max-h-60 overflow-y-auto text-[12px]">
                    @foreach ($skills as $s)
                        <li class="flex items-center justify-between gap-2">
                            <span class="break-words">{{ $s['name'] ?? '' }}</span>
                            <span class="shrink-0 text-[var(--ui-muted)]">×{{ $s['count'] ?? 0 }}@if (! empty($s['hardened'])) <span class="text-emerald-600" title="gehärtet">✓</span>@endif</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="text-[11px] text-[var(--ui-muted)] italic py-2">— noch keine Skills.</div>
            @endif
        </div>

        {{-- 8b) Gate-Log --}}
        <div class="rounded-lg border border-[var(--ui-border)] p-4">
            <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Gate-Log — selbst tun vs. fragen</h4>
            <p class="text-[11px] text-[var(--ui-muted)] mb-2">Die <b>Entscheidung</b> an der Schwelle: bei Confidence & Risiko selbst handeln oder rückfragen.</p>
            @if (count($gateLog))
                <ul class="space-y-1 max-h-60 overflow-y-auto text-[12px]">
                    @foreach ($gateLog as $g)
                        <li class="flex items-start gap-2">
                            <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium {{ ($g['decision'] ?? '') === 'ask' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ ($g['decision'] ?? '') === 'ask' ? 'fragt' : 'tut' }}</span>
                            <span class="text-[var(--ui-muted)] shrink-0">c{{ number_format((float)($g['conf'] ?? 0), 2) }}·r{{ number_format((float)($g['risk'] ?? 0), 2) }}</span>
                            <span class="break-words">{{ $g['action'] ?? '' }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="text-[11px] text-[var(--ui-muted)] italic py-2">— noch keine Gate-Entscheidungen.</div>
            @endif
        </div>
    </div>

    {{-- 9) Arbeitsgedächtnis --}}
    <div class="rounded-lg border border-[var(--ui-border)] p-4">
        <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Arbeitsgedächtnis — der aktuelle Faden</h4>
        <p class="text-[11px] text-[var(--ui-muted)] mb-2">Der <b>Prefrontal-Puffer</b>: was gerade „im Kopf" ist (begrenzt durch die Arbeitsspanne des Genoms).</p>
        @if (count($working))
            <div class="space-y-1.5 max-h-60 overflow-y-auto text-[12px]">
                @foreach ($working as $w)
                    <div class="flex items-start gap-2">
                        <span class="shrink-0 text-[10px] uppercase tracking-wide text-[var(--ui-muted)] w-12">{{ $w['role'] ?? '' }}</span>
                        <span class="break-words">{{ $w['text'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-[11px] text-[var(--ui-muted)] italic py-2">— gerade leer (kein aktiver Faden).</div>
        @endif
    </div>

    @verbatim
    <script type="module">
        import * as THREE from 'https://esm.sh/three@0.179.0';
        import ForceGraph3D from 'https://esm.sh/3d-force-graph@1.80.0?deps=three@0.179.0';

        function trunc(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }
        function hueOf(s) { var h = 0; s = String(s || ''); for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0; return h % 360; }
        function colorOf(type, name) { return 'hsl(' + hueOf(type || name) + ',62%,62%)'; }
        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

        // Text-Sprite als Knoten-Label (dunkle Pille, wie in der Mindmap)
        function makeLabel(text, color) {
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            var fs = 42, font = '600 ' + fs + 'px -apple-system, system-ui, sans-serif';
            ctx.font = font;
            var tw = ctx.measureText(text).width, padX = 18, padY = 12;
            canvas.width = tw + padX * 2; canvas.height = fs + padY * 2;
            var r = canvas.height / 2;
            ctx.fillStyle = 'rgba(8,12,24,0.72)';
            ctx.beginPath(); ctx.roundRect(0, 0, canvas.width, canvas.height, r); ctx.fill();
            ctx.font = font; ctx.fillStyle = color || '#e8e8e8';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
            ctx.fillText(text, canvas.width / 2, canvas.height / 2);
            var tex = new THREE.CanvasTexture(canvas);
            var sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex, depthTest: false, transparent: true }));
            sp.scale.set(canvas.width / 16, canvas.height / 16, 1);
            return sp;
        }

        function init(container) {
            if (container.__bgInit) return;
            if (container.clientWidth === 0 || container.clientHeight === 0) return; // Tab noch versteckt
            var eid = container.id.replace('braingraph-3d-', '');
            var dataEl = document.getElementById('bgdata-' + eid);
            if (!dataEl) return;
            var data; try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }
            container.__bgInit = true;

            var nodes = (data.nodes || []).map(function (n) {
                var use = +n.use || 0;
                return { id: n.name, name: n.name, type: n.type || '', note: n.note || '', use: use,
                    val: 3 + Math.sqrt(use + 1) * 2, color: colorOf(n.type, n.name) };
            });
            var ids = {}; nodes.forEach(function (n) { ids[n.id] = true; });
            var links = [];
            (data.links || []).forEach(function (l) { if (ids[l.src] && ids[l.dst] && l.src !== l.dst) links.push({ source: l.src, target: l.dst }); });

            var graph = ForceGraph3D()(container)
                .backgroundColor('#080c18')
                .showNavInfo(false)
                .enableNodeDrag(true)
                .nodeRelSize(1)
                .graphData({ nodes: nodes, links: links })
                .nodeThreeObject(function (node) {
                    var g = new THREE.Group();
                    var radius = node.val;
                    g.add(new THREE.Mesh(
                        new THREE.SphereGeometry(radius, 20, 20),
                        new THREE.MeshPhongMaterial({ color: node.color, emissive: node.color, emissiveIntensity: 0.35, shininess: 80 })
                    ));
                    // weicher neuronaler Schein
                    g.add(new THREE.Mesh(
                        new THREE.SphereGeometry(radius * 1.9, 12, 12),
                        new THREE.MeshBasicMaterial({ color: node.color, transparent: true, opacity: 0.12, depthWrite: false })
                    ));
                    var label = makeLabel(trunc(node.name, 28), node.color);
                    label.position.y = -(radius + 3);
                    label.name = 'label';
                    label.visible = radius >= 6; // häufig abgerufene Fakten direkt beschriftet
                    g.add(label);
                    return g;
                })
                .nodeLabel(function (n) {
                    var lines = ['<b style="color:' + n.color + '">' + esc(n.name) + '</b>'];
                    if (n.type) lines.push('<span style="opacity:.7">' + esc(n.type) + '</span>');
                    if (n.use) lines.push('<span style="opacity:.6">×' + n.use + ' abgerufen</span>');
                    if (n.note) lines.push(esc(trunc(n.note, 180)));
                    return '<div style="background:rgba(0,0,0,.85);color:#ccc;padding:6px 10px;border-radius:6px;font-size:11px;line-height:1.5;max-width:280px;text-align:left">' + lines.join('<br/>') + '</div>';
                })
                .linkColor(function () { return 'rgba(140,150,190,0.35)'; })
                .linkWidth(0.5)
                .linkOpacity(0.5)
                .linkDirectionalParticles(2)
                .linkDirectionalParticleWidth(0.8)
                .linkDirectionalParticleSpeed(0.006)
                .linkDirectionalParticleColor(function () { return '#9fb0ff'; })
                .warmupTicks(60)
                .cooldownTicks(140)
                .d3AlphaDecay(0.018)
                .d3VelocityDecay(0.28);

            graph.d3Force('charge').strength(-120);
            graph.d3Force('link').distance(42);

            // Beleuchtung + Sternenfeld → „Gehirn im Raum"
            var scene = graph.scene();
            scene.add(new THREE.AmbientLight(0x445577, 0.7));
            var dir = new THREE.DirectionalLight(0xffffff, 0.75); dir.position.set(100, 200, 150); scene.add(dir);
            (function () {
                var geo = new THREE.BufferGeometry(), pos = new Float32Array(1500 * 3);
                for (var i = 0; i < 1500; i++) { pos[i * 3] = (Math.random() - .5) * 2200; pos[i * 3 + 1] = (Math.random() - .5) * 2200; pos[i * 3 + 2] = (Math.random() - .5) * 2200; }
                geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
                scene.add(new THREE.Points(geo, new THREE.PointsMaterial({ color: 0x8888aa, size: .7, transparent: true, opacity: .5 })));
            })();

            graph.onEngineStop(function () { if (!container.__bgFitted) { container.__bgFitted = true; graph.zoomToFit(700, 50); } });

            // Hover: Nachbar-Labels einblenden
            graph.onNodeHover(function (node) {
                graph.graphData().nodes.forEach(function (n) {
                    if (!n.__threeObj) return;
                    var l = n.__threeObj.getObjectByName('label');
                    if (l) l.visible = (n.val >= 6) || (node && n.id === node.id);
                });
            });

            // Steuerung (Zoom / Fit / Vollbild)
            var panel = container.parentNode;
            function camZoom(f) {
                var c = graph.camera().position, t = (graph.controls() && graph.controls().target) || { x: 0, y: 0, z: 0 };
                graph.cameraPosition({ x: t.x + (c.x - t.x) * f, y: t.y + (c.y - t.y) * f, z: t.z + (c.z - t.z) * f }, t, 350);
            }
            var zi = panel.querySelector('[data-bg-zoom="in"]'); if (zi) zi.addEventListener('click', function () { camZoom(0.7); });
            var zo = panel.querySelector('[data-bg-zoom="out"]'); if (zo) zo.addEventListener('click', function () { camZoom(1.4); });
            var ft = panel.querySelector('[data-bg-fit]'); if (ft) ft.addEventListener('click', function () { graph.zoomToFit(600, 50); });
            var fb = panel.querySelector('[data-bg-fs]'); if (fb) fb.addEventListener('click', function () {
                if (document.fullscreenElement) { document.exitFullscreen(); } else if (panel.requestFullscreen) { panel.requestFullscreen(); }
            });

            new ResizeObserver(function () { graph.width(container.clientWidth).height(container.clientHeight); }).observe(container);
        }

        function scan() {
            document.querySelectorAll('[id^="braingraph-3d-"]').forEach(function (c) {
                if (c.__bgInit) return;
                if (c.clientWidth > 0 && c.clientHeight > 0) { init(c); return; }
                // Tab versteckt (x-cloak/display:none) → warten, bis sichtbar
                if (c.__bgObserved) return; c.__bgObserved = true;
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) { if (e.isIntersecting && e.target.clientWidth > 0) { init(e.target); io.disconnect(); } });
                });
                io.observe(c);
            });
        }
        document.addEventListener('livewire:navigated', scan);
        if (document.readyState !== 'loading') scan(); else document.addEventListener('DOMContentLoaded', scan);
    </script>
    @endverbatim
</div>
