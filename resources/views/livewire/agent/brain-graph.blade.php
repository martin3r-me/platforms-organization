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

    {{-- 1) Wissensgraph (Neocortex) --}}
    <div>
        <h4 class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wide mb-1">Wissensgraph — Neocortex</h4>
        <p class="text-[11px] text-[var(--ui-muted)] mb-2">Das <b>Wissen</b>: Fakten als Knoten, typisierte Relationen als Kanten. Fällt im Schlaf aus den Episoden aus (Konsolidierung).</p>
        @if (count($graph['nodes']) === 0)
            <div class="rounded-lg border border-dashed border-[var(--ui-border)] p-6 text-center text-[11px] text-[var(--ui-muted)] italic">Noch keine Fakten konsolidiert — der Graph erscheint, sobald der Neocortex im Schlaf gewachsen ist.</div>
        @else
            <div class="relative rounded-lg border border-[var(--ui-border)] bg-[var(--ui-surface)] overflow-hidden" style="height: 460px;">
                <canvas class="brain-graph-canvas" data-eid="{{ $entity->id }}" style="width:100%; height:100%; display:block; cursor:grab;"></canvas>
                <script type="application/json" id="bgdata-{{ $entity->id }}">@json($graph)</script>
                <div class="bg-tip" style="position:absolute; pointer-events:none; display:none; z-index:10; max-width:280px; padding:6px 8px; border-radius:6px; background:rgba(15,18,20,.92); color:#e8e8e8; font-size:11px; line-height:1.35;"></div>
                <div class="absolute bottom-2 left-2 text-[10px] text-[var(--ui-muted)] bg-[var(--ui-surface)]/70 rounded px-1.5 py-0.5">Top {{ count($graph['nodes']) }} Knoten · ziehen · über Knoten für Details</div>
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
    <script>
    (function () {
        function trunc(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }
        function hueOf(s) { var h = 0; s = String(s || ''); for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0; return h % 360; }
        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

        function run(cv) {
            if (cv.__bgStarted) return;
            var el = document.getElementById('bgdata-' + cv.dataset.eid);
            if (!el) return;
            var data;
            try { data = JSON.parse(el.textContent); } catch (e) { return; }
            if (cv.clientWidth === 0 || cv.clientHeight === 0) { requestAnimationFrame(function () { run(cv); }); return; }
            cv.__bgStarted = true;

            var dpr = window.devicePixelRatio || 1;
            var ctx = cv.getContext('2d');
            var W = cv.clientWidth, H = cv.clientHeight;
            cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            var raw = data.nodes || [], rawLinks = data.links || [];
            var nodes = raw.map(function (n, i) {
                var use = +n.use || 0;
                return {
                    name: n.name, type: n.type || '', note: n.note || '', use: use,
                    r: 4 + Math.min(11, Math.sqrt(use + 1) * 2.2), hue: hueOf(n.type || n.name),
                    x: W / 2 + (Math.cos(i) * 120) + (i % 7) * 8 - 24, y: H / 2 + (Math.sin(i) * 120) + (i % 5) * 8 - 20,
                    dx: 0, dy: 0
                };
            });
            var idx = {}; nodes.forEach(function (n, i) { idx[n.name] = i; });
            var links = [];
            rawLinks.forEach(function (l) { var s = idx[l.src], t = idx[l.dst]; if (s != null && t != null && s !== t) links.push({ s: s, t: t }); });

            var N = nodes.length, alpha = 1, hoverNode = null, dragNode = null, frames = 0;
            var tip = cv.parentNode.querySelector('.bg-tip');

            function tick() {
                var k = Math.sqrt((W * H) / Math.max(1, N)) * 0.85;
                for (var i = 0; i < N; i++) { nodes[i].dx = 0; nodes[i].dy = 0; }
                for (var i = 0; i < N; i++) for (var j = i + 1; j < N; j++) {
                    var a = nodes[i], b = nodes[j], ex = a.x - b.x, ey = a.y - b.y, dist = Math.hypot(ex, ey) || 0.01;
                    var rep = (k * k) / dist, ux = ex / dist, uy = ey / dist;
                    a.dx += ux * rep; a.dy += uy * rep; b.dx -= ux * rep; b.dy -= uy * rep;
                }
                for (var m = 0; m < links.length; m++) {
                    var a = nodes[links[m].s], b = nodes[links[m].t], ex = a.x - b.x, ey = a.y - b.y, dist = Math.hypot(ex, ey) || 0.01;
                    var att = (dist * dist) / k, ux = ex / dist, uy = ey / dist;
                    a.dx -= ux * att; a.dy -= uy * att; b.dx += ux * att; b.dy += uy * att;
                }
                var cx = W / 2, cy = H / 2;
                for (var i = 0; i < N; i++) {
                    var n = nodes[i]; if (n === dragNode) continue;
                    n.dx += (cx - n.x) * 0.03; n.dy += (cy - n.y) * 0.03;
                    var disp = Math.hypot(n.dx, n.dy) || 0.01, lim = Math.min(disp, alpha * k * 0.5);
                    n.x += (n.dx / disp) * lim; n.y += (n.dy / disp) * lim;
                    n.x = Math.max(n.r + 2, Math.min(W - n.r - 2, n.x)); n.y = Math.max(n.r + 2, Math.min(H - n.r - 2, n.y));
                }
            }
            function draw() {
                ctx.clearRect(0, 0, W, H);
                ctx.strokeStyle = 'rgba(130,130,140,0.22)'; ctx.lineWidth = 1;
                for (var m = 0; m < links.length; m++) { var a = nodes[links[m].s], b = nodes[links[m].t]; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke(); }
                for (var i = 0; i < N; i++) {
                    var n = nodes[i], hot = (n === hoverNode);
                    ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, 6.2832);
                    ctx.fillStyle = 'hsl(' + n.hue + ',58%,' + (hot ? 46 : 56) + '%)'; ctx.fill();
                    if (hot) { ctx.lineWidth = 2; ctx.strokeStyle = '#111'; ctx.stroke(); }
                    if (n.r >= 8 || hot) { ctx.fillStyle = 'rgba(20,20,24,0.85)'; ctx.font = '11px system-ui, sans-serif'; ctx.fillText(trunc(n.name, 24), n.x + n.r + 3, n.y + 4); }
                }
            }
            function loop() { tick(); draw(); alpha *= 0.985; frames++; if (alpha > 0.02 && frames < 600) requestAnimationFrame(loop); else draw(); }
            requestAnimationFrame(loop);

            function at(evt) {
                var rect = cv.getBoundingClientRect(), mx = evt.clientX - rect.left, my = evt.clientY - rect.top, best = null, bd = 1e9;
                for (var i = 0; i < N; i++) { var n = nodes[i], d = Math.hypot(n.x - mx, n.y - my); if (d < n.r + 5 && d < bd) { bd = d; best = n; } }
                return { n: best, mx: mx, my: my };
            }
            cv.addEventListener('mousemove', function (e) {
                var h = at(e);
                if (dragNode) { var rect = cv.getBoundingClientRect(); dragNode.x = e.clientX - rect.left; dragNode.y = e.clientY - rect.top; alpha = Math.max(alpha, 0.25); frames = 0; requestAnimationFrame(loop); return; }
                hoverNode = h.n;
                if (h.n) { tip.style.display = 'block'; tip.style.left = (h.mx + 12) + 'px'; tip.style.top = (h.my + 10) + 'px'; tip.innerHTML = '<b>' + esc(h.n.name) + '</b>' + (h.n.type ? ' <span style="opacity:.6">· ' + esc(h.n.type) + '</span>' : '') + (h.n.note ? '<br>' + esc(trunc(h.n.note, 160)) : ''); cv.style.cursor = 'pointer'; }
                else { tip.style.display = 'none'; cv.style.cursor = 'grab'; }
                draw();
            });
            cv.addEventListener('mousedown', function (e) { var h = at(e); if (h.n) { dragNode = h.n; cv.style.cursor = 'grabbing'; } });
            window.addEventListener('mouseup', function () { dragNode = null; cv.style.cursor = 'grab'; });
            cv.addEventListener('mouseleave', function () { hoverNode = null; tip.style.display = 'none'; draw(); });
        }

        function scan() { document.querySelectorAll('canvas.brain-graph-canvas').forEach(run); }
        document.addEventListener('livewire:navigated', scan);
        if (document.readyState !== 'loading') scan(); else document.addEventListener('DOMContentLoaded', scan);
    })();
    </script>
    @endverbatim
</div>
