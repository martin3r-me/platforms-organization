<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <div>
            <h3 class="text-sm font-semibold text-[var(--ui-primary)]">Wissensgraph — Neocortex</h3>
            <p class="text-[11px] text-[var(--ui-muted)]">
                Knoten = Fakten, Kanten = typisierte Relationen. Gepushter Snapshot (host-agnostisch, egal wo der Agent läuft).
            </p>
        </div>
        <div class="text-right text-[11px] text-[var(--ui-muted)]">
            <div><span class="font-semibold text-[var(--ui-fg)]">{{ $factCount }}</span> Fakten · <span class="font-semibold text-[var(--ui-fg)]">{{ $edgeCount }}</span> Kanten</div>
            @if ($snapshotAt)
                <div>Snapshot {{ $snapshotAt->diffForHumans() }}</div>
            @endif
        </div>
    </div>

    @if (count($graph['nodes']) === 0)
        <div class="rounded-lg border border-[var(--ui-border)] p-8 text-center text-sm text-[var(--ui-muted)]">
            Noch kein Wissensgraph gemeldet.
            <span class="block text-[11px] mt-1">Kommt mit dem nächsten Gehirn-Push des Daemons (~10 min), sobald der Neocortex Fakten konsolidiert hat.</span>
        </div>
    @else
        <div class="relative rounded-lg border border-[var(--ui-border)] bg-[var(--ui-surface)] overflow-hidden" style="height: 540px;">
            <canvas class="brain-graph-canvas" data-eid="{{ $entity->id }}" style="width:100%; height:100%; display:block; cursor:grab;"></canvas>
            <script type="application/json" id="bgdata-{{ $entity->id }}">@json($graph)</script>
            <div class="bg-tip" style="position:absolute; pointer-events:none; display:none; z-index:10; max-width:280px; padding:6px 8px; border-radius:6px; background:rgba(15,18,20,.92); color:#e8e8e8; font-size:11px; line-height:1.35;"></div>
            <div class="absolute bottom-2 left-2 text-[10px] text-[var(--ui-muted)] bg-[var(--ui-surface)]/70 rounded px-1.5 py-0.5">
                Top {{ count($graph['nodes']) }} Knoten nach Nutzung · ziehen zum Sortieren · über Knoten für Details
            </div>
        </div>
    @endif

    @verbatim
    <script>
    (function () {
        function trunc(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }
        function hueOf(s) { var h = 0; s = String(s || ''); for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0; return h % 360; }

        function run(cv) {
            if (cv.__bgStarted) return;
            var el = document.getElementById('bgdata-' + cv.dataset.eid);
            if (!el) return;
            var data;
            try { data = JSON.parse(el.textContent); } catch (e) { return; }
            // Tab noch versteckt (display:none → Breite 0) → warten, bis er sichtbar ist.
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
                    r: 4 + Math.min(11, Math.sqrt(use + 1) * 2.2),
                    hue: hueOf(n.type || n.name),
                    x: W / 2 + (Math.cos(i) * 120) + (i % 7) * 8 - 24,
                    y: H / 2 + (Math.sin(i) * 120) + (i % 5) * 8 - 20,
                    dx: 0, dy: 0
                };
            });
            var idx = {}; nodes.forEach(function (n, i) { idx[n.name] = i; });
            var links = [];
            rawLinks.forEach(function (l) {
                var s = idx[l.src], t = idx[l.dst];
                if (s != null && t != null && s !== t) links.push({ s: s, t: t, rel: l.rel || '' });
            });

            var N = nodes.length, alpha = 1, hoverNode = null, dragNode = null;
            var tip = cv.parentNode.querySelector('.bg-tip');

            function tick() {
                var k = Math.sqrt((W * H) / Math.max(1, N)) * 0.85;
                for (var i = 0; i < N; i++) { nodes[i].dx = 0; nodes[i].dy = 0; }
                for (var i = 0; i < N; i++) {
                    for (var j = i + 1; j < N; j++) {
                        var a = nodes[i], b = nodes[j];
                        var ex = a.x - b.x, ey = a.y - b.y, dist = Math.hypot(ex, ey) || 0.01;
                        var rep = (k * k) / dist, ux = ex / dist, uy = ey / dist;
                        a.dx += ux * rep; a.dy += uy * rep; b.dx -= ux * rep; b.dy -= uy * rep;
                    }
                }
                for (var m = 0; m < links.length; m++) {
                    var a = nodes[links[m].s], b = nodes[links[m].t];
                    var ex = a.x - b.x, ey = a.y - b.y, dist = Math.hypot(ex, ey) || 0.01;
                    var att = (dist * dist) / k, ux = ex / dist, uy = ey / dist;
                    a.dx -= ux * att; a.dy -= uy * att; b.dx += ux * att; b.dy += uy * att;
                }
                var cx = W / 2, cy = H / 2;
                for (var i = 0; i < N; i++) {
                    var n = nodes[i];
                    if (n === dragNode) continue;
                    n.dx += (cx - n.x) * 0.03; n.dy += (cy - n.y) * 0.03;
                    var disp = Math.hypot(n.dx, n.dy) || 0.01;
                    var lim = Math.min(disp, alpha * k * 0.5);
                    n.x += (n.dx / disp) * lim; n.y += (n.dy / disp) * lim;
                    n.x = Math.max(n.r + 2, Math.min(W - n.r - 2, n.x));
                    n.y = Math.max(n.r + 2, Math.min(H - n.r - 2, n.y));
                }
            }

            function draw() {
                ctx.clearRect(0, 0, W, H);
                ctx.strokeStyle = 'rgba(130,130,140,0.22)'; ctx.lineWidth = 1;
                for (var m = 0; m < links.length; m++) {
                    var a = nodes[links[m].s], b = nodes[links[m].t];
                    ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
                }
                for (var i = 0; i < N; i++) {
                    var n = nodes[i], hot = (n === hoverNode);
                    ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, 6.2832);
                    ctx.fillStyle = 'hsl(' + n.hue + ',58%,' + (hot ? 46 : 56) + '%)';
                    ctx.fill();
                    if (hot) { ctx.lineWidth = 2; ctx.strokeStyle = '#111'; ctx.stroke(); }
                    if (n.r >= 8 || hot) {
                        ctx.fillStyle = 'rgba(20,20,24,0.85)'; ctx.font = '11px system-ui, sans-serif';
                        ctx.fillText(trunc(n.name, 24), n.x + n.r + 3, n.y + 4);
                    }
                }
            }

            var frames = 0;
            function loop() {
                tick(); draw();
                alpha *= 0.985; frames++;
                if (alpha > 0.02 && frames < 600) requestAnimationFrame(loop);
                else draw();
            }
            requestAnimationFrame(loop);

            function at(evt) {
                var rect = cv.getBoundingClientRect();
                var mx = evt.clientX - rect.left, my = evt.clientY - rect.top, best = null, bd = 1e9;
                for (var i = 0; i < N; i++) {
                    var n = nodes[i], d = Math.hypot(n.x - mx, n.y - my);
                    if (d < n.r + 5 && d < bd) { bd = d; best = n; }
                }
                return { n: best, mx: mx, my: my };
            }
            cv.addEventListener('mousemove', function (e) {
                var h = at(e);
                if (dragNode) {
                    var rect = cv.getBoundingClientRect();
                    dragNode.x = e.clientX - rect.left; dragNode.y = e.clientY - rect.top;
                    alpha = Math.max(alpha, 0.25); frames = 0; requestAnimationFrame(loop);
                    return;
                }
                hoverNode = h.n;
                if (h.n) {
                    tip.style.display = 'block';
                    tip.style.left = (h.mx + 12) + 'px'; tip.style.top = (h.my + 10) + 'px';
                    tip.innerHTML = '<b>' + esc(h.n.name) + '</b>' + (h.n.type ? ' <span style="opacity:.6">· ' + esc(h.n.type) + '</span>' : '') + (h.n.note ? '<br>' + esc(trunc(h.n.note, 160)) : '');
                    cv.style.cursor = 'pointer';
                } else { tip.style.display = 'none'; cv.style.cursor = 'grab'; }
                draw();
            });
            cv.addEventListener('mousedown', function (e) { var h = at(e); if (h.n) { dragNode = h.n; cv.style.cursor = 'grabbing'; } });
            window.addEventListener('mouseup', function () { dragNode = null; cv.style.cursor = 'grab'; });
            cv.addEventListener('mouseleave', function () { hoverNode = null; tip.style.display = 'none'; draw(); });

            function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
        }

        function scan() { document.querySelectorAll('canvas.brain-graph-canvas').forEach(run); }
        document.addEventListener('livewire:navigated', scan);
        if (document.readyState !== 'loading') scan(); else document.addEventListener('DOMContentLoaded', scan);
    })();
    </script>
    @endverbatim
</div>
