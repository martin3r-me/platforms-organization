<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Einheiten', 'href' => route('organization.entities.index')],
            ['label' => $entity->name ?? 'Details', 'href' => route('organization.entities.show', $entity)],
            ['label' => 'VSM Board'],
        ]">
            <x-ui-button variant="ghost" size="sm" href="{{ route('organization.entities.mindmap', $entity) }}">
                @svg('heroicon-o-globe-alt', 'w-4 h-4')
                <span>Mindmap</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <div class="relative w-full flex-1 min-h-0 overflow-hidden" style="background:#080c18">
        {{-- Canvas layer for animated flows --}}
        <canvas id="board-flows" class="absolute inset-0 w-full h-full z-0" wire:ignore></canvas>

        {{-- DOM layer for bands and cards --}}
        <div id="board-bands" class="absolute inset-0 w-full h-full z-10 overflow-y-auto" wire:ignore>
            <div class="flex h-full">

                {{-- Left Sidebar --}}
                <div id="board-sidebar" class="w-64 shrink-0 flex flex-col gap-3 p-3 overflow-y-auto">
                    {{-- Legend --}}
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
                        <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
                            @svg('heroicon-o-swatch', 'w-4 h-4 text-gray-500')
                            <span>Legende</span>
                        </div>
                        <div class="p-2 space-y-1">
                            <div class="px-2 pt-1 pb-0.5 text-[10px] uppercase tracking-wider text-gray-500">VSM Ebenen</div>
                            @foreach(['S5' => 'Policy', 'S4' => 'Intelligence', 'S3' => 'Control', 'S2' => 'Coordination', 'S1' => 'Operations', 'ENV' => 'Environment'] as $code => $label)
                                <div class="flex items-center gap-2 px-2 py-1">
                                    <span class="w-3 h-3 rounded-sm shrink-0" style="background:{{ $this->boardData['vsmColors'][$code] }}"></span>
                                    <span class="text-gray-300">{{ $code }} · {{ $label }}</span>
                                </div>
                            @endforeach
                            <div class="border-t border-gray-700/50 my-1"></div>
                            <div class="px-2 pt-1 pb-0.5 text-[10px] uppercase tracking-wider text-gray-500">Flow-Typen</div>
                            @foreach(['service' => 'Service', 'info' => 'Information', 'supply' => 'Supply', 'hierarchy' => 'Hierarchie', 'collaboration' => 'Kollaboration'] as $cat => $label)
                                <div class="flex items-center gap-2 px-2 py-1">
                                    <span class="inline-block shrink-0" style="width:16px;height:2px;background:{{ $this->boardData['flowColors'][$cat] }};box-shadow:0 0 4px {{ $this->boardData['flowColors'][$cat] }}60"></span>
                                    <span class="text-gray-400">{{ $label }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Balance Panel (compact in sidebar) --}}
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden">
                        <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2">
                            @svg('heroicon-o-scale', 'w-4 h-4 text-blue-400')
                            <span>VSM-Balance</span>
                        </div>
                        <div class="p-2 space-y-1">
                            @php $maxCount = max(1, max(array_values($this->boardData['balance']))); @endphp
                            @foreach($this->boardData['balance'] as $code => $count)
                                @php $isEmpty = $count === 0; $pct = round(($count / $maxCount) * 100); @endphp
                                <div class="flex items-center gap-2 px-1 py-0.5 rounded {{ $isEmpty ? 'bg-red-500/10' : '' }}">
                                    <span class="w-8 text-[10px] font-bold {{ $isEmpty ? 'text-red-400' : 'text-gray-400' }}">{{ $code }}</span>
                                    <div class="flex-1 h-1.5 rounded bg-gray-800 overflow-hidden">
                                        <div class="h-full rounded {{ $isEmpty ? 'bg-red-500/50' : '' }}" style="width:{{ $pct }}%;background:{{ !$isEmpty ? ($this->boardData['vsmColors'][$code] ?? '#3b82f6') : '' }}"></div>
                                    </div>
                                    <span class="w-5 text-right tabular-nums {{ $isEmpty ? 'text-red-400' : 'text-white' }} font-bold text-[11px]">{{ $count }}</span>
                                </div>
                            @endforeach
                            @php
                                $diag = $this->boardData['diagnosis'];
                                $diagType = str_contains($diag, 'fragil') || str_contains($diag, 'leere') ? 'warn' : (str_contains($diag, 'Gleichgewicht') || str_contains($diag, 'besetzt') ? 'ok' : 'warn');
                            @endphp
                            <div class="mt-1 px-1 text-[10px] {{ $diagType === 'ok' ? 'text-emerald-400' : 'text-amber-400' }}">
                                {{ $diagType === 'ok' ? '&#10003;' : '&#9888;' }} {{ $diag }}
                            </div>
                        </div>
                    </div>

                    {{-- Info Panel (hidden until card clicked) --}}
                    <div id="board-info-panel" class="bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-xl shadow-2xl text-xs hidden">
                        <div class="p-3">
                            <div class="flex items-center gap-2 mb-2">
                                <div id="board-info-dot" class="w-3 h-3 rounded-full shrink-0 ring-2 ring-white/20"></div>
                                <span id="board-info-name" class="font-bold text-sm text-white truncate"></span>
                            </div>
                            <div id="board-info-meta" class="text-gray-400 space-y-0.5"></div>
                            <div id="board-info-actions" class="mt-2 flex gap-2"></div>
                        </div>
                    </div>
                </div>

                {{-- Main Board Area --}}
                <div class="flex-1 min-w-0 py-3 pr-3 relative">
                    {{-- ENV Boundary --}}
                    <div class="relative border border-gray-600/30 rounded-2xl p-2 h-full flex flex-col" style="background:rgba(100,116,139,0.04)">
                        <div class="absolute -top-3 left-4 px-2 py-0.5 text-[10px] uppercase tracking-widest font-bold rounded" style="color:#64748b;background:#080c18">
                            @svg('heroicon-o-globe-alt', 'w-3 h-3 inline -mt-0.5') ENV · Environment
                        </div>

                        {{-- S5 Band --}}
                        @include('organization::livewire.entity.partials.board-band', ['code' => 'S5', 'band' => $this->boardData['bands']['S5']])

                        {{-- S4 Band --}}
                        @include('organization::livewire.entity.partials.board-band', ['code' => 'S4', 'band' => $this->boardData['bands']['S4']])

                        {{-- S3 Band --}}
                        @include('organization::livewire.entity.partials.board-band', ['code' => 'S3', 'band' => $this->boardData['bands']['S3']])

                        {{-- S2 Band --}}
                        @include('organization::livewire.entity.partials.board-band', ['code' => 'S2', 'band' => $this->boardData['bands']['S2']])

                        {{-- S1 Band --}}
                        @include('organization::livewire.entity.partials.board-band', ['code' => 'S1', 'band' => $this->boardData['bands']['S1']])

                        {{-- ENV entities row --}}
                        @if(count($this->boardData['envBand']['entities']) > 0)
                            <div class="mt-1 px-2 py-1">
                                <div class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color:#64748b">ENV Entities</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($this->boardData['envBand']['entities'] as $ent)
                                        @include('organization::livewire.entity.partials.board-card', ['ent' => $ent, 'bandColor' => '#64748b'])
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Unassigned --}}
                        @if(count($this->boardData['unassigned']) > 0)
                            <div class="mt-1 px-2 py-1 border-t border-gray-700/30">
                                <div class="text-[10px] uppercase tracking-wider font-bold mb-1 text-gray-600">Nicht zugeordnet</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($this->boardData['unassigned'] as $ent)
                                        @include('organization::livewire.entity.partials.board-card', ['ent' => $ent, 'bandColor' => '#374151'])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Right Ticker --}}
                <div id="board-ticker" class="w-72 shrink-0 p-3 overflow-hidden">
                    <div class="bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl text-xs overflow-hidden h-full flex flex-col">
                        <div class="px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2 shrink-0">
                            @svg('heroicon-o-signal', 'w-4 h-4 text-cyan-400')
                            <span>Live Feed</span>
                            <span class="ml-auto flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span class="text-[9px] text-emerald-400 uppercase tracking-wider">Live</span>
                            </span>
                        </div>
                        <div id="ticker-list" class="flex-1 overflow-y-auto p-2 space-y-1.5">
                            @foreach($this->boardData['ticker'] as $event)
                                @php
                                    $typeColors = [
                                        'regulation' => 'border-blue-500/30 text-blue-400',
                                        'intervention' => 'border-amber-500/30 text-amber-400',
                                        'emergence' => 'border-emerald-500/30 text-emerald-400',
                                        'alert' => 'border-red-500/30 text-red-400',
                                    ];
                                    $badgeBg = [
                                        'regulation' => 'bg-blue-500/15 text-blue-400',
                                        'intervention' => 'bg-amber-500/15 text-amber-400',
                                        'emergence' => 'bg-emerald-500/15 text-emerald-400',
                                        'alert' => 'bg-red-500/15 text-red-400',
                                    ];
                                    $tc = $typeColors[$event['type']] ?? 'border-gray-500/30 text-gray-400';
                                    $bg = $badgeBg[$event['type']] ?? 'bg-gray-500/15 text-gray-400';
                                @endphp
                                <div class="ticker-event rounded-lg border {{ $tc }} p-2 bg-gray-900/60 transition-all duration-300" style="animation:tickerSlideIn 0.4s ease-out">
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <span class="text-[9px] text-gray-500 tabular-nums">{{ $event['timestamp'] }}</span>
                                        <span class="px-1 py-0.5 rounded text-[8px] font-bold uppercase {{ $bg }}">{{ $event['level'] }}</span>
                                    </div>
                                    <div class="font-medium text-[11px] text-gray-200 mb-0.5">{{ $event['message'] }}</div>
                                    <div class="text-[10px] text-gray-500 leading-snug">{{ $event['detail'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script id="board-data" type="application/json">@json($this->boardData)</script>

    <style>
        @keyframes tickerSlideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes movementPulse {
            0%, 100% { box-shadow: none; }
            50% { box-shadow: 0 0 8px 2px var(--pulse-color); }
        }
        .board-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .board-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }
        .board-card.selected {
            ring: 2px;
            ring-color: white;
            box-shadow: 0 0 15px rgba(255,255,255,0.15);
        }
        .movement-positive {
            --pulse-color: rgba(16, 185, 129, 0.5);
            animation: movementPulse 2s ease-in-out infinite;
        }
        .movement-negative {
            --pulse-color: rgba(239, 68, 68, 0.5);
            animation: movementPulse 2s ease-in-out infinite;
        }
    </style>

    <script type="module">
        // ---- Data ----
        const data = JSON.parse(document.getElementById('board-data').textContent);
        const canvas = document.getElementById('board-flows');
        const ctx = canvas.getContext('2d');

        // ---- Resize canvas ----
        function resizeCanvas() {
            const parent = canvas.parentElement;
            canvas.width = parent.clientWidth;
            canvas.height = parent.clientHeight;
        }
        resizeCanvas();
        new ResizeObserver(resizeCanvas).observe(canvas.parentElement);

        // ---- Collect card positions ----
        function getCardPositions() {
            const cards = document.querySelectorAll('.board-card[data-entity-id]');
            const positions = {};
            const boardRect = canvas.parentElement.getBoundingClientRect();
            cards.forEach(card => {
                const id = parseInt(card.dataset.entityId);
                const rect = card.getBoundingClientRect();
                positions[id] = {
                    x: rect.left - boardRect.left + rect.width / 2,
                    y: rect.top - boardRect.top + rect.height / 2,
                    w: rect.width,
                    h: rect.height,
                };
            });
            return positions;
        }

        // ---- Particles ----
        const particles = [];
        const relationships = data.relationships || [];

        relationships.forEach((rel, idx) => {
            const count = 2 + Math.floor(Math.random() * 3);
            for (let i = 0; i < count; i++) {
                particles.push({
                    relIdx: idx,
                    t: Math.random(),
                    speed: 0.001 + Math.random() * 0.002,
                });
            }
        });

        // ---- Regulation loop Y positions ----
        const bandYPositions = {};
        function updateBandPositions() {
            const boardRect = canvas.parentElement.getBoundingClientRect();
            document.querySelectorAll('[data-band-code]').forEach(el => {
                const rect = el.getBoundingClientRect();
                bandYPositions[el.dataset.bandCode] = {
                    y: rect.top - boardRect.top + rect.height / 2,
                    top: rect.top - boardRect.top,
                    bottom: rect.top - boardRect.top + rect.height,
                };
            });
        }

        // ---- Animation loop ----
        let animFrame;
        let time = 0;

        function draw() {
            time += 0.016;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const positions = getCardPositions();
            updateBandPositions();

            // Draw relationships (Bezier curves)
            relationships.forEach((rel, idx) => {
                const from = positions[rel.from];
                const to = positions[rel.to];
                if (!from || !to) return;

                const dx = to.x - from.x;
                const dy = to.y - from.y;
                const cx1 = from.x + dx * 0.3;
                const cy1 = from.y;
                const cx2 = from.x + dx * 0.7;
                const cy2 = to.y;

                ctx.beginPath();
                ctx.moveTo(from.x, from.y);
                ctx.bezierCurveTo(cx1, cy1, cx2, cy2, to.x, to.y);
                ctx.strokeStyle = rel.color + '40';
                ctx.lineWidth = 1.5;
                ctx.stroke();

                // Arrowhead
                const arrowSize = 6;
                const angle = Math.atan2(to.y - cy2, to.x - cx2);
                ctx.beginPath();
                ctx.moveTo(to.x, to.y);
                ctx.lineTo(to.x - arrowSize * Math.cos(angle - 0.4), to.y - arrowSize * Math.sin(angle - 0.4));
                ctx.lineTo(to.x - arrowSize * Math.cos(angle + 0.4), to.y - arrowSize * Math.sin(angle + 0.4));
                ctx.closePath();
                ctx.fillStyle = rel.color + '80';
                ctx.fill();
            });

            // Draw particles
            particles.forEach(p => {
                p.t += p.speed;
                if (p.t > 1) p.t -= 1;

                const rel = relationships[p.relIdx];
                if (!rel) return;
                const from = positions[rel.from];
                const to = positions[rel.to];
                if (!from || !to) return;

                const dx = to.x - from.x;
                const cx1 = from.x + dx * 0.3;
                const cy1 = from.y;
                const cx2 = from.x + dx * 0.7;
                const cy2 = to.y;

                // Cubic bezier point
                const t = p.t;
                const mt = 1 - t;
                const px = mt*mt*mt*from.x + 3*mt*mt*t*cx1 + 3*mt*t*t*cx2 + t*t*t*to.x;
                const py = mt*mt*mt*from.y + 3*mt*mt*t*cy1 + 3*mt*t*t*cy2 + t*t*t*to.y;

                ctx.beginPath();
                ctx.arc(px, py, 2, 0, Math.PI * 2);
                ctx.fillStyle = rel.color;
                ctx.fill();

                // Glow
                ctx.beginPath();
                ctx.arc(px, py, 5, 0, Math.PI * 2);
                ctx.fillStyle = rel.color + '30';
                ctx.fill();
            });

            // Draw regulation loops (left side of board)
            const loops = data.regulationLoops || [];
            const sidebarWidth = document.getElementById('board-sidebar')?.offsetWidth || 256;
            const loopX = sidebarWidth + 16;

            loops.forEach(loop => {
                const fromBand = bandYPositions[loop.from];
                const toBand = bandYPositions[loop.to];
                if (!fromBand || !toBand) return;

                const fromY = fromBand.y;
                const toY = toBand.y;
                const curveOffset = 30 + Math.abs(fromY - toY) * 0.15;

                const opacity = 0.25 + 0.15 * Math.sin(time * 1.2 + loops.indexOf(loop));

                ctx.beginPath();
                ctx.moveTo(loopX, fromY);
                ctx.bezierCurveTo(
                    loopX - curveOffset, fromY,
                    loopX - curveOffset, toY,
                    loopX, toY
                );
                ctx.strokeStyle = loop.color + Math.round(opacity * 255).toString(16).padStart(2, '0');
                ctx.lineWidth = 2;
                ctx.setLineDash([4, 4]);
                ctx.stroke();
                ctx.setLineDash([]);

                // Arrow
                const arrowSize = 5;
                const arrowAngle = fromY < toY ? Math.PI / 2 : -Math.PI / 2;
                ctx.beginPath();
                ctx.moveTo(loopX, toY);
                ctx.lineTo(loopX - arrowSize * Math.cos(arrowAngle - 0.5), toY - arrowSize * Math.sin(arrowAngle - 0.5));
                ctx.lineTo(loopX - arrowSize * Math.cos(arrowAngle + 0.5), toY - arrowSize * Math.sin(arrowAngle + 0.5));
                ctx.closePath();
                ctx.fillStyle = loop.color + Math.round(opacity * 255).toString(16).padStart(2, '0');
                ctx.fill();

                // Label
                const midY = (fromY + toY) / 2;
                ctx.save();
                ctx.font = '9px -apple-system, system-ui, sans-serif';
                ctx.fillStyle = loop.color + '90';
                ctx.textAlign = 'right';
                ctx.fillText(loop.label, loopX - curveOffset - 4, midY + 3);
                ctx.restore();
            });

            animFrame = requestAnimationFrame(draw);
        }

        draw();

        // ---- Card click -> Info panel ----
        let selectedCardId = null;

        document.addEventListener('click', function(e) {
            const card = e.target.closest('.board-card[data-entity-id]');
            if (card) {
                const id = card.dataset.entityId;
                selectCard(id, card);
                return;
            }
            // Click outside cards
            if (!e.target.closest('#board-info-panel') && !e.target.closest('.board-card')) {
                deselectCard();
            }
        });

        document.addEventListener('dblclick', function(e) {
            const card = e.target.closest('.board-card[data-entity-id]');
            if (card) {
                window.location.href = '/organization/entities/' + card.dataset.entityId;
            }
        });

        function selectCard(id, cardEl) {
            // Remove previous selection
            document.querySelectorAll('.board-card.selected').forEach(c => c.classList.remove('selected'));
            cardEl.classList.add('selected');
            selectedCardId = id;

            // Find entity data
            let entityData = null;
            let bandColor = '#3b82f6';
            for (const [code, band] of Object.entries(data.bands)) {
                const found = band.entities.find(e => e.id == id);
                if (found) { entityData = found; bandColor = band.color; break; }
            }
            if (!entityData) {
                const found = data.envBand.entities.find(e => e.id == id);
                if (found) { entityData = found; bandColor = data.envBand.color; }
            }
            if (!entityData) {
                const found = data.unassigned.find(e => e.id == id);
                if (found) { entityData = found; bandColor = '#374151'; }
            }

            if (!entityData) return;

            const panel = document.getElementById('board-info-panel');
            document.getElementById('board-info-dot').style.background = bandColor;
            document.getElementById('board-info-dot').style.boxShadow = '0 0 8px ' + bandColor;
            document.getElementById('board-info-name').textContent = entityData.name;

            let meta = '<div class="text-gray-500">' + entityData.type + '</div>';
            const m = entityData.metrics;
            if (m.items_total > 0) {
                const pct = Math.round(m.items_done / m.items_total * 100);
                meta += '<div>Items: <span class="text-white">' + m.items_done + '/' + m.items_total + '</span> <span class="text-gray-600">(' + pct + '%)</span></div>';
            }
            if (m.time_h > 0) meta += '<div>Zeit: <span class="text-white">' + m.time_h + 'h</span></div>';
            if (m.okr_perf != null) {
                const okrColor = m.okr_perf >= 70 ? '#10b981' : m.okr_perf >= 30 ? '#f59e0b' : '#ef4444';
                meta += '<div>OKR: <span style="color:' + okrColor + '">' + m.okr_perf + '%</span></div>';
            }
            const mv = entityData.movement;
            if (mv && mv.delta_count > 0) {
                meta += '<div class="mt-1 border-t border-gray-700/50 pt-1">';
                meta += '<div class="text-[9px] uppercase tracking-wider text-gray-500 mb-0.5">Bewegung (7d)</div>';
                meta += '<div>Score: <span class="' + (mv.score > 0 ? 'text-emerald-400' : mv.score < 0 ? 'text-red-400' : 'text-gray-400') + '">' + (mv.score > 0 ? '+' : '') + mv.score + '</span></div>';
                if (mv.top_delta) meta += '<div class="text-gray-500">' + mv.top_delta + '</div>';
                meta += '</div>';
            }
            document.getElementById('board-info-meta').innerHTML = meta;

            let actions = '<a href="/organization/entities/' + id + '" class="px-2.5 py-1 bg-white/10 text-white rounded hover:bg-white/20 transition-colors flex items-center gap-1 text-[11px]">Details</a>';
            actions += '<a href="/organization/entities/' + id + '/mindmap" class="px-2.5 py-1 bg-white/5 text-gray-400 rounded hover:bg-white/10 transition-colors flex items-center gap-1 text-[11px]">Mindmap</a>';
            document.getElementById('board-info-actions').innerHTML = actions;

            panel.classList.remove('hidden');
        }

        function deselectCard() {
            selectedCardId = null;
            document.querySelectorAll('.board-card.selected').forEach(c => c.classList.remove('selected'));
            document.getElementById('board-info-panel').classList.add('hidden');
        }

        // ---- Ticker auto-cycling ----
        const tickerList = document.getElementById('ticker-list');
        let tickerPaused = false;

        tickerList?.addEventListener('mouseenter', () => { tickerPaused = true; });
        tickerList?.addEventListener('mouseleave', () => { tickerPaused = false; });

        function cycleTickerEvent() {
            if (tickerPaused || !tickerList) return;
            const events = tickerList.querySelectorAll('.ticker-event');
            if (events.length === 0) return;

            // Move last to first with animation
            const last = events[events.length - 1];
            const clone = last.cloneNode(true);
            clone.style.animation = 'tickerSlideIn 0.4s ease-out';
            tickerList.insertBefore(clone, tickerList.firstChild);

            // Update timestamp to now
            const timeEl = clone.querySelector('.tabular-nums');
            if (timeEl) {
                const now = new Date();
                timeEl.textContent = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
            }

            // Remove last if too many
            if (tickerList.children.length > 15) {
                tickerList.removeChild(tickerList.lastChild);
            }
        }

        setInterval(cycleTickerEvent, 8000 + Math.random() * 7000);

        // ---- Livewire integration ----
        Livewire.on('board-data-updated', function(event) {
            window.location.reload();
        });

        // ---- Cleanup ----
        document.addEventListener('livewire:navigating', () => {
            cancelAnimationFrame(animFrame);
        });
    </script>
</x-ui-page>
