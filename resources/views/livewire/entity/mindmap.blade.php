<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Einheiten', 'href' => route('organization.entities.index')],
            ['label' => $entity->name ?? 'Details', 'href' => route('organization.entities.show', $entity)],
            ['label' => 'Mindmap'],
        ]" />
    </x-slot>

    <div class="relative w-full flex-1 min-h-0">
        <div wire:ignore id="3d-graph" class="w-full h-full"></div>

        {{-- Sidebar --}}
        <div id="sidebar" class="absolute top-3 left-3 z-20 w-72 bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl shadow-2xl text-xs overflow-hidden">
            <button type="button" data-collapse-target="filter-list" class="w-full px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2 hover:bg-white/5 transition-colors">
                @svg('heroicon-o-funnel', 'w-4 h-4 text-gray-500')
                <span class="flex-1 text-left">Layer</span>
                @svg('heroicon-s-chevron-up', 'w-3.5 h-3.5 text-gray-500 collapse-icon transition-transform')
            </button>
            <div class="flex items-center gap-1 px-2 py-1.5 border-b border-gray-700/40 text-[10px] uppercase tracking-wider">
                <button type="button" id="filter-select-all" class="px-2 py-0.5 rounded text-gray-400 hover:bg-white/5 hover:text-white transition-colors">Alle</button>
                <button type="button" id="filter-select-none" class="px-2 py-0.5 rounded text-gray-400 hover:bg-white/5 hover:text-white transition-colors">Keine</button>
            </div>
            <div id="filter-list" class="p-2 space-y-0.5 max-h-[55vh] overflow-y-auto"></div>
        </div>

        {{-- Info panel --}}
        <div id="info-panel" class="absolute bottom-3 left-3 z-20 w-72 bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-xl shadow-2xl text-xs hidden">
            <div class="p-3">
                <div class="flex items-center gap-2 mb-2">
                    <div id="info-dot" class="w-3 h-3 rounded-full shrink-0 ring-2 ring-white/20"></div>
                    <span id="info-name" class="font-bold text-sm text-white truncate"></span>
                </div>
                <div id="info-meta" class="text-gray-400 space-y-0.5"></div>
                <div id="info-actions" class="mt-2 flex gap-2"></div>
            </div>
        </div>

        {{-- Navigation controls (Google Maps style) --}}
        <div class="absolute top-3 right-3 z-20 flex flex-col items-end gap-2">
            {{-- Zoom + Fullscreen row --}}
            <div class="flex gap-1">
                <div class="bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-lg shadow-2xl flex overflow-hidden">
                    <button id="btn-zoom-in" class="w-9 h-9 flex items-center justify-center text-gray-300 hover:bg-white/10 hover:text-white transition-colors text-lg font-light border-r border-gray-700/50">+</button>
                    <button id="btn-zoom-out" class="w-9 h-9 flex items-center justify-center text-gray-300 hover:bg-white/10 hover:text-white transition-colors text-lg font-light">&minus;</button>
                </div>
                <button id="btn-fullscreen" class="bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-lg shadow-2xl w-9 h-9 flex items-center justify-center text-gray-400 hover:bg-white/10 hover:text-white transition-colors">
                    @svg('heroicon-o-arrows-pointing-out', 'w-4 h-4')
                </button>
            </div>
            {{-- Directional pad --}}
            <div class="bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-lg shadow-2xl grid grid-cols-3 w-[calc(2.25rem*3+2px)]">
                <div></div>
                <button data-pan="up" class="h-8 flex items-center justify-center text-gray-400 hover:bg-white/10 hover:text-white transition-colors">@svg('heroicon-s-chevron-up', 'w-3.5 h-3.5')</button>
                <div></div>
                <button data-pan="left" class="h-8 flex items-center justify-center text-gray-400 hover:bg-white/10 hover:text-white transition-colors">@svg('heroicon-s-chevron-left', 'w-3.5 h-3.5')</button>
                <button id="btn-home" class="h-8 flex items-center justify-center text-gray-500 hover:bg-white/10 hover:text-white transition-colors">@svg('heroicon-s-stop', 'w-2.5 h-2.5')</button>
                <button data-pan="right" class="h-8 flex items-center justify-center text-gray-400 hover:bg-white/10 hover:text-white transition-colors">@svg('heroicon-s-chevron-right', 'w-3.5 h-3.5')</button>
                <div></div>
                <button data-pan="down" class="h-8 flex items-center justify-center text-gray-400 hover:bg-white/10 hover:text-white transition-colors">@svg('heroicon-s-chevron-down', 'w-3.5 h-3.5')</button>
                <div></div>
            </div>

            {{-- Timeline Slider --}}
            @php
                $availableDates = $this->availableDates;
                $snapshotCount = count($availableDates);
                // slider positions: 0..N-1 = snapshots, N = Live
                $sliderMax = $snapshotCount; // allows N+1 positions
                $currentIdx = $snapshotCount; // default = Live
                if ($snapshotDate) {
                    foreach ($availableDates as $i => $s) {
                        if ($s['key'] === $snapshotDate) { $currentIdx = $i; break; }
                    }
                }
            @endphp
            <div id="timeline-slider" wire:ignore class="bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-lg shadow-2xl w-[calc(2.25rem*3+2px)] px-2 py-2 text-xs">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-gray-500 font-medium uppercase tracking-wider" style="font-size:9px">Timeline</span>
                    <span id="timeline-badge" class="px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase tracking-wider {{ !$snapshotDate ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30' }}">
                        {{ !$snapshotDate ? 'Live' : 'Snap' }}
                    </span>
                </div>
                @if($snapshotCount > 0)
                <input type="range" id="timeline-range" min="0" max="{{ $sliderMax }}" value="{{ $currentIdx }}" step="1" class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer accent-blue-500">
                <div class="text-center mt-1">
                    <span id="timeline-date" class="text-gray-300 font-medium tabular-nums" style="font-size:9px">{{ $snapshotDate ? (collect($availableDates)->firstWhere('key', $snapshotDate)['label'] ?? $snapshotDate) : 'Live' }}</span>
                </div>
                @else
                <div class="flex items-center justify-center gap-1.5 text-gray-600 py-1">
                    @svg('heroicon-o-clock', 'w-3 h-3')
                    <span style="font-size:9px">Keine Snapshots</span>
                </div>
                @endif
            </div>
        </div>

        {{-- VSM Balance Panel (shown when VSM dimension is active) --}}
        <div id="vsm-balance" class="absolute top-3 left-1/2 -translate-x-1/2 z-20 bg-gray-900/80 backdrop-blur-md border border-gray-700/50 rounded-xl shadow-2xl text-xs overflow-hidden hidden">
            <div class="px-3 py-1.5 border-b border-gray-700/40 font-bold text-gray-300 text-[11px] uppercase tracking-wider flex items-center gap-2">
                @svg('heroicon-o-squares-2x2', 'w-3.5 h-3.5 text-blue-400')
                <span>VSM-Balance</span>
                <span id="vsm-balance-diagnosis" class="ml-2 text-[9px] normal-case tracking-normal font-normal"></span>
            </div>
            <div id="vsm-balance-rows" class="p-1.5 flex items-stretch gap-1"></div>
        </div>

        {{-- Legend (link types) --}}
        <div class="absolute bottom-3 right-3 z-20 w-72 bg-gray-900/50 backdrop-blur-md border border-gray-700/40 rounded-xl shadow-2xl text-xs overflow-hidden">
            <button type="button" data-collapse-target="legend" class="w-full px-3 py-2 border-b border-gray-700/40 font-bold text-gray-300 text-sm flex items-center gap-2 hover:bg-white/5 transition-colors">
                @svg('heroicon-o-link', 'w-4 h-4 text-gray-500')
                <span class="flex-1 text-left">Verbindungen</span>
                @svg('heroicon-s-chevron-up', 'w-3.5 h-3.5 text-gray-500 collapse-icon transition-transform')
            </button>
            <div class="p-2 space-y-0.5 max-h-[55vh] overflow-y-auto" id="legend"></div>
        </div>

    </div>

    <script id="mindmap-data" type="application/json">@json($this->graphData)</script>
    <script id="timeline-dates" type="application/json">@json($this->availableDates)</script>

    <script type="module">
        import * as THREE from 'https://esm.sh/three@0.179.0';
        import ForceGraph3D from 'https://esm.sh/3d-force-graph@1.80.0?deps=three@0.179.0';

        // ─── Data ───
        var raw = JSON.parse(document.getElementById('mindmap-data').textContent);
        var allNodes = raw.nodes;
        var allLinks = raw.links;
        var categories = raw.categories;
        var entityGroups = raw.entityGroups;

        // ─── Filter state ───
        var filters = {};
        Object.keys(categories).forEach(function(k) { filters[k] = true; });
        var groupFilters = {};
        Object.keys(entityGroups).forEach(function(k) { groupFilters[k] = true; });

        // ─── Dimensions (VSM + Cost Centers) ───
        var dimensions = { vsm: false, costCenter: false };
        // VSM Y-layers — S1 (Operations) bottom, S5 (Policy) top
        var VSM_Y = { S1: -360, S2: -180, S3: 0, S4: 180, S5: 360 };
        var VSM_LABELS = { S1: 'S1 · Operations', S2: 'S2 · Coordination', S3: 'S3 · Control', S4: 'S4 · Intelligence', S5: 'S5 · Policy' };
        // Per-level accent colors (HSV ramp warm→cool)
        var VSM_COLORS = {
            S1: { hex: 0x10b981, css: '#10b981' }, // emerald — operations
            S2: { hex: 0x06b6d4, css: '#06b6d4' }, // cyan — coordination
            S3: { hex: 0x3b82f6, css: '#3b82f6' }, // blue — control
            S4: { hex: 0x8b5cf6, css: '#8b5cf6' }, // violet — intelligence
            S5: { hex: 0xec4899, css: '#ec4899' }, // pink — identity
        };
        // VSM grid layout — hard-positioned per level
        var VSM_GRID_SPACING = 95;

        function hasVsm(n) {
            return n && n.vsm && n.vsm.code && VSM_Y.hasOwnProperty(n.vsm.code);
        }

        function isUmweltEntity(n) {
            if (!n) return false;
            if ((n.category || 'entity') !== 'entity') return false;
            if (n.val > 15) return false; // center = system itself
            return !hasVsm(n);
        }

        function assignVsmGridPositions() {
            var codes = ['S1', 'S2', 'S3', 'S4', 'S5'];
            var byLevel = { S1: [], S2: [], S3: [], S4: [], S5: [] };
            allNodes.forEach(function(n) {
                if (hasVsm(n)) byLevel[n.vsm.code].push(n);
            });
            codes.forEach(function(code) {
                var nodes = byLevel[code];
                nodes.sort(function(a, b) {
                    var ka = (a.type || '') + '|' + (a.name || '');
                    var kb = (b.type || '') + '|' + (b.name || '');
                    return ka.localeCompare(kb);
                });
                var N = nodes.length;
                if (N === 0) return;
                var cols = Math.max(1, Math.ceil(Math.sqrt(N * 1.6)));
                var rows = Math.ceil(N / cols);
                var gridW = (cols - 1) * VSM_GRID_SPACING;
                var gridD = (rows - 1) * VSM_GRID_SPACING;
                var y = VSM_Y[code];
                nodes.forEach(function(n, idx) {
                    var col = idx % cols;
                    var row = Math.floor(idx / cols);
                    n.fx = -gridW / 2 + col * VSM_GRID_SPACING;
                    n.fy = y;
                    n.fz = -gridD / 2 + row * VSM_GRID_SPACING;
                });
            });
        }

        function clearVsmGridPositions() {
            allNodes.forEach(function(n) {
                if (hasVsm(n)) {
                    n.fx = null;
                    n.fy = null;
                    n.fz = null;
                }
            });
        }

        function getFilteredData() {
            var visibleIds = new Set();
            var nodes = allNodes.filter(function(n) {
                var cat = n.category || 'entity';
                if (!filters[cat]) return false;
                if (cat === 'entity' && n.group && !groupFilters[n.group]) return false;
                // In VSM mode, hide Umwelt entities (they become fog)
                if (dimensions.vsm && isUmweltEntity(n)) return false;
                visibleIds.add(n.id);
                return true;
            });
            var links = allLinks.filter(function(l) {
                var s = typeof l.source === 'object' ? l.source.id : l.source;
                var t = typeof l.target === 'object' ? l.target.id : l.target;
                return visibleIds.has(s) && visibleIds.has(t);
            });
            return { nodes: nodes, links: links };
        }

        // ─── Sidebar ───
        function buildSidebar() {
            var el = document.getElementById('filter-list');
            el.innerHTML = '';

            Object.keys(categories).forEach(function(key) {
                var cat = categories[key];
                var row = document.createElement('label');
                row.className = 'flex items-center gap-2 px-2 py-1.5 rounded hover:bg-white/5 cursor-pointer select-none';
                row.innerHTML =
                    '<input type="checkbox" ' + (filters[key] ? 'checked' : '') + ' class="rounded border-gray-600 bg-gray-800 text-blue-500 w-3.5 h-3.5" data-cat="' + key + '">' +
                    '<span class="w-2.5 h-2.5 rounded-full shrink-0 shadow-sm" style="background:' + cat.color + ';box-shadow:0 0 6px ' + cat.color + '50"></span>' +
                    '<span class="flex-1 text-gray-300">' + cat.label + '</span>' +
                    '<span class="text-gray-600 tabular-nums">' + cat.count + '</span>';
                el.appendChild(row);
            });

            if (Object.keys(entityGroups).length > 0) {
                var sep = document.createElement('div');
                sep.className = 'border-t border-gray-700/50 my-1';
                el.appendChild(sep);

                Object.keys(entityGroups).forEach(function(key) {
                    var g = entityGroups[key];
                    var row = document.createElement('label');
                    row.className = 'flex items-center gap-2 px-2 py-1 rounded hover:bg-white/5 cursor-pointer select-none ml-1';
                    row.innerHTML =
                        '<input type="checkbox" ' + (groupFilters[key] ? 'checked' : '') + ' class="rounded border-gray-600 bg-gray-800 text-blue-500 w-3 h-3" data-group="' + key + '">' +
                        '<span class="w-2 h-2 rounded-full shrink-0" style="background:' + g.color + ';box-shadow:0 0 4px ' + g.color + '40"></span>' +
                        '<span class="flex-1 text-gray-400">' + key + '</span>' +
                        '<span class="text-gray-600 tabular-nums">' + g.count + '</span>';
                    el.appendChild(row);
                });
            }

            // Dimensions section
            var dimSep = document.createElement('div');
            dimSep.className = 'border-t border-gray-700/50 my-1';
            el.appendChild(dimSep);

            var dimHeader = document.createElement('div');
            dimHeader.className = 'px-2 pt-1 pb-0.5 text-[10px] uppercase tracking-wider text-gray-500';
            dimHeader.textContent = 'Dimensionen';
            el.appendChild(dimHeader);

            [
                { key: 'vsm', label: 'VSM-Ebenen', color: '#60A5FA' },
                { key: 'costCenter', label: 'Kostenstellen', color: '#F59E0B' },
            ].forEach(function(d) {
                var row = document.createElement('label');
                row.className = 'flex items-center gap-2 px-2 py-1 rounded hover:bg-white/5 cursor-pointer select-none';
                row.innerHTML =
                    '<input type="checkbox" ' + (dimensions[d.key] ? 'checked' : '') + ' class="rounded border-gray-600 bg-gray-800 text-blue-500 w-3.5 h-3.5" data-dim="' + d.key + '">' +
                    '<span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:' + d.color + ';box-shadow:0 0 6px ' + d.color + '50"></span>' +
                    '<span class="flex-1 text-gray-300">' + d.label + '</span>';
                el.appendChild(row);
            });

            el.querySelectorAll('[data-cat]').forEach(function(cb) {
                cb.addEventListener('change', function() { filters[cb.dataset.cat] = cb.checked; graph.graphData(getFilteredData()); });
            });
            el.querySelectorAll('[data-group]').forEach(function(cb) {
                cb.addEventListener('change', function() { groupFilters[cb.dataset.group] = cb.checked; graph.graphData(getFilteredData()); });
            });
            el.querySelectorAll('[data-dim]').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    dimensions[cb.dataset.dim] = cb.checked;
                    if (cb.dataset.dim === 'vsm') toggleVsmLayers(cb.checked);
                    if (cb.dataset.dim === 'costCenter') toggleCostCenterHalos(cb.checked);
                });
            });
        }

        function setAllFilters(value) {
            Object.keys(filters).forEach(function(k) { filters[k] = value; });
            Object.keys(groupFilters).forEach(function(k) { groupFilters[k] = value; });
            buildSidebar();
            graph.graphData(getFilteredData());
        }
        document.getElementById('filter-select-all').addEventListener('click', function() { setAllFilters(true); });
        document.getElementById('filter-select-none').addEventListener('click', function() { setAllFilters(false); });

        // ─── Label factory ───
        function makeLabel(text, fontSize, color) {
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            var bold = fontSize > 30;
            var fontStr = (bold ? '600 ' : '500 ') + fontSize + 'px -apple-system, system-ui, sans-serif';
            ctx.font = fontStr;
            var tw = ctx.measureText(text).width;
            var padX = 16;
            var padY = 10;
            canvas.width = tw + padX * 2;
            canvas.height = fontSize + padY * 2;
            // Dark pill background for contrast
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            var r = canvas.height / 2;
            ctx.fillStyle = 'rgba(8, 12, 24, 0.75)';
            ctx.beginPath();
            ctx.roundRect(0, 0, canvas.width, canvas.height, r);
            ctx.fill();
            // Text
            ctx.font = fontStr;
            ctx.fillStyle = color || '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, canvas.width / 2, canvas.height / 2);
            var tex = new THREE.CanvasTexture(canvas);
            var mat = new THREE.SpriteMaterial({ map: tex, depthTest: false, transparent: true });
            var sprite = new THREE.Sprite(mat);
            sprite.scale.set(canvas.width / 14, canvas.height / 14, 1);
            return sprite;
        }

        // ─── Neighbors ───
        var neighbors = {};
        allLinks.forEach(function(l) {
            var s = typeof l.source === 'object' ? l.source.id : l.source;
            var t = typeof l.target === 'object' ? l.target.id : l.target;
            if (!neighbors[s]) neighbors[s] = new Set();
            if (!neighbors[t]) neighbors[t] = new Set();
            neighbors[s].add(t);
            neighbors[t].add(s);
        });

        // ─── Graph ───
        var container = document.getElementById('3d-graph');
        var focusedNodeId = null;

        var graph = ForceGraph3D()(container)
            .backgroundColor('#080c18')
            .showNavInfo(false)
            .enableNodeDrag(true)
            .enableNavigationControls(true);

        // Forces - tighter layout, entity_links stay near parent
        graph.d3Force('charge').strength(-300);
        graph.d3Force('link').distance(function(link) {
            var ltype = link.ltype || '';
            if (ltype === 'entity_link') return 20;
            if (ltype === 'hierarchy') return 45;
            return 60;
        });

        // Clustering force — groups non-fixed nodes by group/category
        graph.d3Force('cluster', function(alpha) {
            var centroids = {};
            var counts = {};
            allNodes.forEach(function(n) {
                if (n.x == null) return;
                // Fixed nodes (VSM grid) don't participate in clustering
                if (n.fx != null) return;
                var g = n.group || n.category || 'x';
                if (!centroids[g]) { centroids[g] = { x: 0, y: 0, z: 0 }; counts[g] = 0; }
                centroids[g].x += n.x; centroids[g].y += n.y; centroids[g].z += n.z;
                counts[g]++;
            });
            Object.keys(centroids).forEach(function(g) {
                centroids[g].x /= counts[g]; centroids[g].y /= counts[g]; centroids[g].z /= counts[g];
            });
            allNodes.forEach(function(n) {
                if (n.x == null || n.fx != null) return;
                var g = n.group || n.category || 'x';
                var c = centroids[g];
                if (!c) return;
                var s = alpha * 0.25;
                n.vx += (c.x - n.x) * s;
                n.vy += (c.y - n.y) * s;
                n.vz += (c.z - n.z) * s;
            });
        });

        graph.graphData(getFilteredData())
            .nodeThreeObject(function(node) {
                var group = new THREE.Group();
                var isCenter = node.val > 15;
                var isLinked = node.category && node.category !== 'entity';
                var depth = node.depth || 0;
                var isSun = node.isSun && !isCenter;
                var m = node.metrics;
                var hasActivity = m && m.time_h > 0;

                // Size by depth: suns=6, root=5, depth1=3.5, depth2=2.5, depth3+=2
                var baseRadius;
                if (isCenter) { baseRadius = 7; }
                else if (isLinked) { baseRadius = 2.5; }
                else if (isSun) { baseRadius = 6; }
                else {
                    baseRadius = depth === 0 ? 5 : (depth === 1 ? 3.5 : (depth === 2 ? 2.5 : 2));
                }
                var radius = baseRadius + (m ? Math.min(m.items_total * 0.15, 2) : 0);
                node.__radius = radius;

                // VSM membership detection
                var nodeHasVsm = node.vsm && node.vsm.code && VSM_COLORS[node.vsm.code];
                var vsmCol = nodeHasVsm ? VSM_COLORS[node.vsm.code] : null;

                // Core sphere — VSM entities get +40% emissive boost
                var emissiveIntensity = isCenter ? 0.4 : isSun ? 0.35 : Math.max(0.05, 0.2 - depth * 0.05);
                if (nodeHasVsm) emissiveIntensity *= 1.4;
                var sphere = new THREE.Mesh(
                    new THREE.SphereGeometry(radius, 24, 24),
                    new THREE.MeshPhongMaterial({
                        color: node.color,
                        emissive: node.color,
                        emissiveIntensity: emissiveIntensity,
                        shininess: isSun ? 120 : 80,
                    })
                );
                group.add(sphere);

                // VSM pedestal ring — flat disc beneath sphere in VSM-level color
                if (nodeHasVsm) {
                    var pedestal = new THREE.Mesh(
                        new THREE.RingGeometry(radius * 1.4, radius * 2.2, 32),
                        new THREE.MeshBasicMaterial({
                            color: vsmCol.hex,
                            transparent: true,
                            opacity: 0.55,
                            side: THREE.DoubleSide,
                            blending: THREE.AdditiveBlending,
                            depthWrite: false,
                        })
                    );
                    pedestal.rotation.x = -Math.PI / 2;
                    pedestal.position.y = -radius * 0.15;
                    pedestal.name = 'vsm_pedestal';
                    pedestal.visible = dimensions.vsm;
                    group.add(pedestal);

                    // VSM-colored halo sphere
                    var vsmHalo = new THREE.Mesh(
                        new THREE.SphereGeometry(radius * 2.0, 16, 16),
                        new THREE.MeshBasicMaterial({
                            color: vsmCol.hex,
                            transparent: true,
                            opacity: 0.22,
                            blending: THREE.AdditiveBlending,
                            depthWrite: false,
                        })
                    );
                    vsmHalo.name = 'vsm_halo';
                    vsmHalo.visible = dimensions.vsm;
                    group.add(vsmHalo);
                }

                // Sun corona - bright layered glow shells
                if (isSun) {
                    // Inner corona
                    group.add(new THREE.Mesh(
                        new THREE.SphereGeometry(radius * 1.6, 16, 16),
                        new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.22 })
                    ));
                    // Mid corona — uses node color but brighter
                    group.add(new THREE.Mesh(
                        new THREE.SphereGeometry(radius * 2.4, 12, 12),
                        new THREE.MeshBasicMaterial({ color: node.color, transparent: true, opacity: 0.18 })
                    ));
                    // Outer corona
                    group.add(new THREE.Mesh(
                        new THREE.SphereGeometry(radius * 3.5, 8, 8),
                        new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.12 })
                    ));
                } else if (!isLinked && depth <= 1) {
                    // Regular glow for non-sun top-level
                    var glowSize = radius * (isCenter ? 2.5 : 1.8);
                    var glowIntensity = isCenter ? 0.25 : (hasActivity ? 0.16 + Math.min(m.time_h / 80, 0.1) : 0.12);
                    group.add(new THREE.Mesh(
                        new THREE.SphereGeometry(glowSize, 12, 12),
                        new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: glowIntensity * 0.5 })
                    ));
                    group.add(new THREE.Mesh(
                        new THREE.SphereGeometry(glowSize * 0.85, 12, 12),
                        new THREE.MeshBasicMaterial({ color: node.color, transparent: true, opacity: glowIntensity })
                    ));
                }

                // Cost center halo — soft additive sphere tinted by CC color
                if (node.cost_center && node.cost_center.color) {
                    var ccColor = new THREE.Color(node.cost_center.color);
                    var ccHalo = new THREE.Mesh(
                        new THREE.SphereGeometry(radius * 2.2, 16, 16),
                        new THREE.MeshBasicMaterial({
                            color: ccColor,
                            transparent: true,
                            opacity: 0.18,
                            blending: THREE.AdditiveBlending,
                            depthWrite: false,
                        })
                    );
                    ccHalo.name = 'cc_halo';
                    ccHalo.visible = dimensions.costCenter;
                    group.add(ccHalo);
                }

                // Label - bigger for root/depth1, smaller for deeper
                var labelText = node.type ? node.type + '  ' + node.name : node.name;
                var fontSize;
                if (isCenter) { fontSize = 42; }
                else if (isLinked) { fontSize = 24; }
                else { fontSize = depth === 0 ? 40 : (depth === 1 ? 34 : 24); }
                var label = makeLabel(isLinked ? node.name : labelText, fontSize, isCenter ? '#ffffff' : node.color);
                label.position.y = -(radius + 2.5);
                // Top-level labels (depth 0-1) start visible
                label.visible = (!isLinked && depth <= 1) || isCenter;
                label.name = 'label';
                group.add(label);

                return group;
            })
            .nodeLabel(function(node) {
                var lines = ['<b style="color:' + node.color + '">' + node.name + '</b>'];
                if (node.group) lines.push(node.group);
                if (node.type) lines.push(node.type);
                var m = node.metrics;
                if (m) {
                    if (m.items_total > 0) lines.push('Items: ' + m.items_done + '/' + m.items_total);
                    if (m.time_h > 0) lines.push('Zeit: ' + m.time_h + 'h');
                }
                return '<div style="background:rgba(0,0,0,0.85);color:#ccc;padding:6px 10px;border-radius:6px;font-size:11px;line-height:1.6;text-align:left">' + lines.join('<br/>') + '</div>';
            })
            .linkColor('color')
            .linkWidth(function(l) {
                var ltype = l.ltype || '';
                if (ltype === 'hierarchy') return 0.6;
                if (ltype === 'relation') return 0.7;
                return 0.35;
            })
            .linkOpacity(0.4)
            .linkLabel(function(l) {
                if (l.ltype !== 'relation' || !l.rel_label) return '';
                return '<div style="background:rgba(0,0,0,0.85);color:' + (l.color || '#fff') + ';padding:4px 8px;border-radius:6px;font-size:11px;font-weight:500">' + l.rel_label + '</div>';
            })
            .linkThreeObjectExtend(true)
            .linkThreeObject(function(l) {
                if (l.ltype !== 'relation' || !l.rel_label) return null;
                var sprite = makeLabel(l.rel_label, 22, l.color || '#F59E0B');
                sprite.name = 'rel_label';
                sprite.visible = false;
                return sprite;
            })
            .linkPositionUpdate(function(sprite, pos) {
                if (!sprite || sprite.name !== 'rel_label') return false;
                sprite.position.set(
                    (pos.start.x + pos.end.x) / 2,
                    (pos.start.y + pos.end.y) / 2,
                    (pos.start.z + pos.end.z) / 2
                );
                return true;
            })
            .linkDirectionalParticles(function(l) { return l.ltype === 'relation' ? 3 : 1; })
            .linkDirectionalParticleWidth(1.0)
            .linkDirectionalParticleSpeed(0.004)
            .linkDirectionalParticleColor('color')
            .warmupTicks(80)
            .cooldownTicks(150)
            .d3AlphaDecay(0.015)
            .d3VelocityDecay(0.25);

        // ─── Neighborhood bounding sphere ───
        function neighborhoodRadius(node) {
            var nb = neighbors[node.id];
            if (!nb || nb.size === 0) return 30;
            var maxDist = 0;
            var currentNodes = graph.graphData().nodes;
            var nodeMap = {};
            currentNodes.forEach(function(n) { nodeMap[n.id] = n; });
            nb.forEach(function(nid) {
                var n = nodeMap[nid];
                if (!n || !n.x) return;
                var d = Math.sqrt(
                    Math.pow(n.x - node.x, 2) +
                    Math.pow(n.y - node.y, 2) +
                    Math.pow(n.z - node.z, 2)
                );
                if (d > maxDist) maxDist = d;
            });
            return Math.max(maxDist, 30);
        }

        function flyToNode(node) {
            focusedNodeId = node.id;
            showInfoPanel(node);

            // Distance = neighborhood radius * factor so everything fits in view
            var nbRadius = neighborhoodRadius(node);
            var dist = nbRadius * 1.3 + 15;
            var hyp = Math.hypot(node.x, node.y, node.z);
            var camPos;
            if (hyp < 1) {
                camPos = { x: 0, y: dist * 0.3, z: dist };
            } else {
                var distRatio = 1 + dist / hyp;
                camPos = { x: node.x * distRatio, y: node.y * distRatio, z: node.z * distRatio };
            }
            graph.cameraPosition(
                camPos,
                { x: node.x, y: node.y, z: node.z },
                1000
            );
        }

        // ─── Click / Double-click ───
        var lastClickTime = 0;
        var lastClickNodeId = null;

        graph.onNodeClick(function(node) {
            var now = Date.now();
            if (lastClickNodeId === node.id && now - lastClickTime < 400) {
                if (node.id && node.id.startsWith('e')) {
                    window.location.href = '/organization/entities/' + node.id.substring(1);
                }
                return;
            }
            lastClickNodeId = node.id;
            lastClickTime = now;
            flyToNode(node);
        });

        graph.onBackgroundClick(function() {
            focusedNodeId = null;
            hideInfoPanel();
            graph.cameraPosition({ x: 0, y: 0, z: 350 }, { x: 0, y: 0, z: 0 }, 800);
        });

        graph.cameraPosition({ x: 0, y: 0, z: 350 });

        // ─── Fly to center entity once graph stabilizes ───
        var hasFocusedInitial = false;
        graph.onEngineStop(function() {
            if (hasFocusedInitial) return;
            hasFocusedInitial = true;
            var centerNode = graph.graphData().nodes.find(function(n) { return n.val > 15; });
            if (centerNode) {
                setTimeout(function() { flyToNode(centerNode); }, 300);
            }
        });

        // ─── Add starfield ───
        (function() {
            var starsGeo = new THREE.BufferGeometry();
            var positions = new Float32Array(3000 * 3);
            for (var i = 0; i < 3000; i++) {
                positions[i * 3] = (Math.random() - 0.5) * 2000;
                positions[i * 3 + 1] = (Math.random() - 0.5) * 2000;
                positions[i * 3 + 2] = (Math.random() - 0.5) * 2000;
            }
            starsGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
            var starsMat = new THREE.PointsMaterial({ color: 0x888899, size: 0.5, transparent: true, opacity: 0.6 });
            graph.scene().add(new THREE.Points(starsGeo, starsMat));
        })();

        // ─── Better lighting for space ───
        (function() {
            var scene = graph.scene();
            // Add subtle ambient
            scene.add(new THREE.AmbientLight(0x334466, 0.6));
            // Directional for depth
            var dir = new THREE.DirectionalLight(0xffffff, 0.8);
            dir.position.set(100, 200, 150);
            scene.add(dir);
        })();

        // ─── VSM stats ───
        function computeVsmStats() {
            var stats = { S1: 0, S2: 0, S3: 0, S4: 0, S5: 0 };
            allNodes.forEach(function(n) {
                if (!n.vsm || !n.vsm.code) return;
                if (stats[n.vsm.code] != null) stats[n.vsm.code]++;
            });
            return stats;
        }

        // ─── VSM floor planes (dynamic) ───
        var vsmLayersGroup = new THREE.Group();
        vsmLayersGroup.visible = false;
        graph.scene().add(vsmLayersGroup);

        function rebuildVsmLayers() {
            // Dispose old children
            while (vsmLayersGroup.children.length > 0) {
                var c = vsmLayersGroup.children[0];
                vsmLayersGroup.remove(c);
                if (c.geometry) c.geometry.dispose();
                if (c.material && c.material.dispose) c.material.dispose();
            }

            var stats = computeVsmStats();
            var counts = Object.values(stats);
            var maxCount = Math.max.apply(null, counts.concat([1]));
            var size = 1100;
            var codes = ['S1', 'S2', 'S3', 'S4', 'S5'];

            codes.forEach(function(code) {
                var y = VSM_Y[code];
                var count = stats[code];
                var isEmpty = count === 0;
                var density = maxCount > 0 ? (count / maxCount) : 0;

                var accentHex = isEmpty ? 0xef4444 : VSM_COLORS[code].hex;
                var accentCss = isEmpty ? '#f87171' : VSM_COLORS[code].css;

                // 1. Filled translucent plane sheet — very subtle so entities dominate
                var planeGeo = new THREE.PlaneGeometry(size, size);
                var planeMat = new THREE.MeshBasicMaterial({
                    color: accentHex,
                    transparent: true,
                    opacity: isEmpty ? 0.05 : (0.025 + density * 0.05),
                    side: THREE.DoubleSide,
                    depthWrite: false,
                });
                var plane = new THREE.Mesh(planeGeo, planeMat);
                plane.rotation.x = -Math.PI / 2;
                plane.position.y = y;
                vsmLayersGroup.add(plane);

                // 2. Grid overlay — dimmer
                var grid = new THREE.GridHelper(size, 24, accentHex, accentHex);
                grid.position.y = y + 0.5;
                grid.material.transparent = true;
                grid.material.opacity = isEmpty ? 0.25 : (0.12 + density * 0.18);
                grid.material.depthWrite = false;
                vsmLayersGroup.add(grid);

                // 3. Outer frame ring — highlights the level boundary
                var frameGeo = new THREE.EdgesGeometry(planeGeo);
                var frameMat = new THREE.LineBasicMaterial({
                    color: accentHex,
                    transparent: true,
                    opacity: isEmpty ? 0.7 : 0.55,
                });
                var frame = new THREE.LineSegments(frameGeo, frameMat);
                frame.rotation.x = -Math.PI / 2;
                frame.position.y = y + 1;
                vsmLayersGroup.add(frame);

                // 4. Left-edge label
                var lbl = makeLabel(VSM_LABELS[code], 34, accentCss);
                lbl.position.set(-size / 2 - 60, y + 20, 0);
                vsmLayersGroup.add(lbl);

                // 5. Right-edge count label
                var rightText = isEmpty ? '⚠ LEER' : (count + ' ' + (count === 1 ? 'Entity' : 'Entities'));
                var rightLbl = makeLabel(rightText, isEmpty ? 36 : 30, accentCss);
                rightLbl.position.set(size / 2 + 60, y + 20, 0);
                vsmLayersGroup.add(rightLbl);
            });
        }

        // ─── Umwelt fog (ambient environment particles) ───
        var umweltFogGroup = new THREE.Group();
        umweltFogGroup.visible = false;
        graph.scene().add(umweltFogGroup);

        function rebuildUmweltFog() {
            while (umweltFogGroup.children.length > 0) {
                var c = umweltFogGroup.children[0];
                umweltFogGroup.remove(c);
                if (c.geometry) c.geometry.dispose();
                if (c.material && c.material.dispose) c.material.dispose();
            }

            var umweltCount = allNodes.filter(isUmweltEntity).length;
            if (umweltCount === 0) return;

            // Particles scale with entity count — each entity ~12 particles
            var particleCount = Math.max(240, umweltCount * 14);
            var positions = new Float32Array(particleCount * 3);
            var R_MIN = 1400, R_MAX = 1900;

            for (var i = 0; i < particleCount; i++) {
                var theta = Math.random() * Math.PI * 2;
                // Uniform sphere via inverse-CDF on cos(phi)
                var phi = Math.acos(2 * Math.random() - 1);
                var r = R_MIN + Math.random() * (R_MAX - R_MIN);
                var sinPhi = Math.sin(phi);
                positions[i * 3]     = r * sinPhi * Math.cos(theta);
                positions[i * 3 + 1] = r * Math.cos(phi);
                positions[i * 3 + 2] = r * sinPhi * Math.sin(theta);
            }

            var geo = new THREE.BufferGeometry();
            geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
            var mat = new THREE.PointsMaterial({
                color: 0x64748b,
                size: 5,
                sizeAttenuation: true,
                transparent: true,
                opacity: 0.45,
                depthWrite: false,
                blending: THREE.AdditiveBlending,
            });
            umweltFogGroup.add(new THREE.Points(geo, mat));

            // Ambient "UMWELT · N Entities" label floating above the stack
            var lbl = makeLabel('UMWELT · ' + umweltCount + ' Entities', 26, '#94a3b8');
            lbl.position.set(0, R_MAX * 0.55, -R_MAX * 0.7);
            umweltFogGroup.add(lbl);
        }

        function updateVsmBalancePanel() {
            var panel = document.getElementById('vsm-balance');
            var rows = document.getElementById('vsm-balance-rows');
            var diag = document.getElementById('vsm-balance-diagnosis');
            if (!panel || !rows) return;

            var stats = computeVsmStats();
            var codes = ['S1', 'S2', 'S3', 'S4', 'S5'];
            var counts = codes.map(function(c) { return stats[c]; });
            var max = Math.max.apply(null, counts.concat([1]));

            rows.innerHTML = '';
            codes.forEach(function(code) {
                var count = stats[code];
                var isEmpty = count === 0;
                var pct = max > 0 ? Math.round((count / max) * 100) : 0;
                var shortLabel = VSM_LABELS[code].split(' · ')[1] || code;

                var cell = document.createElement('div');
                cell.className = 'flex flex-col items-center gap-0.5 px-2 py-1 rounded ' + (isEmpty ? 'bg-red-500/15 border border-red-500/40' : 'bg-white/5');
                cell.style.minWidth = '56px';
                cell.innerHTML =
                    '<div class="text-[9px] uppercase tracking-wider ' + (isEmpty ? 'text-red-300' : 'text-gray-500') + '">' + code + '</div>' +
                    '<div class="text-base font-bold tabular-nums ' + (isEmpty ? 'text-red-400' : 'text-white') + '">' + count + '</div>' +
                    '<div class="text-[8px] ' + (isEmpty ? 'text-red-400' : 'text-gray-500') + '">' + shortLabel + '</div>' +
                    '<div class="w-full h-0.5 rounded ' + (isEmpty ? 'bg-red-500/30' : 'bg-blue-500/20') + ' mt-0.5 overflow-hidden">' +
                        '<div class="h-full ' + (isEmpty ? 'bg-red-500' : 'bg-blue-400') + '" style="width:' + pct + '%"></div>' +
                    '</div>';
                rows.appendChild(cell);
            });

            // Diagnosis: S3/S4 balance + empty-level warning
            var empty = codes.filter(function(c) { return stats[c] === 0; });
            var s3 = stats.S3, s4 = stats.S4;
            var diagText = '';
            var diagClass = 'text-gray-500';
            if (empty.length >= 3) {
                diagText = '✗ ' + empty.length + ' leere Ebenen — fragil';
                diagClass = 'text-red-400';
            } else if (s3 > 0 && s4 === 0) {
                diagText = '⚠ S3 ohne S4 — operativ, aber ohne Zukunftsradar';
                diagClass = 'text-amber-400';
            } else if (s4 > 0 && s3 === 0) {
                diagText = '⚠ S4 ohne S3 — Vision ohne Steuerung';
                diagClass = 'text-amber-400';
            } else if (s3 > 0 && s4 > 0 && Math.abs(s3 - s4) <= 1) {
                diagText = '✓ S3/S4 im Gleichgewicht';
                diagClass = 'text-emerald-400';
            } else if (empty.length > 0) {
                diagText = '⚠ leere Ebenen: ' + empty.join(', ');
                diagClass = 'text-amber-400';
            } else {
                diagText = '✓ alle Ebenen besetzt';
                diagClass = 'text-emerald-400';
            }
            diag.textContent = diagText;
            diag.className = 'ml-2 text-[9px] normal-case tracking-normal font-normal ' + diagClass;
        }

        function refreshVsmAll() {
            rebuildVsmLayers();
            rebuildUmweltFog();
            updateVsmBalancePanel();
        }

        // Initial build
        refreshVsmAll();

        function toggleVsmLayers(on) {
            vsmLayersGroup.visible = on;
            umweltFogGroup.visible = on;
            var panel = document.getElementById('vsm-balance');
            if (panel) panel.classList.toggle('hidden', !on);

            if (on) {
                assignVsmGridPositions();
                refreshVsmAll();
                graph.graphData(getFilteredData()); // Umwelt entities drop out
                // Softer repulsion — VSM nodes are fixed, others drift naturally
                graph.d3Force('charge').strength(-200);
                graph.cameraPosition(
                    { x: 900, y: 600, z: 1400 },
                    { x: 0, y: 0, z: 0 },
                    1400
                );
            } else {
                clearVsmGridPositions();
                graph.graphData(getFilteredData()); // Umwelt entities return
                graph.d3Force('charge').strength(-300);
                graph.cameraPosition(
                    { x: 0, y: 0, z: 350 },
                    { x: 0, y: 0, z: 0 },
                    1200
                );
            }

            // Toggle per-node pedestals and halos (after graphData refresh — __threeObj rebuilt)
            setTimeout(function() {
                var data = graph.graphData();
                data.nodes.forEach(function(n) {
                    if (!n.__threeObj) return;
                    var ped = n.__threeObj.getObjectByName('vsm_pedestal');
                    if (ped) ped.visible = on;
                    var halo = n.__threeObj.getObjectByName('vsm_halo');
                    if (halo) halo.visible = on;
                });
            }, 50);

            graph.d3ReheatSimulation();
        }

        function toggleCostCenterHalos(on) {
            var data = graph.graphData();
            data.nodes.forEach(function(n) {
                if (!n.__threeObj) return;
                var halo = n.__threeObj.getObjectByName('cc_halo');
                if (halo) halo.visible = on;
            });
        }

        // ─── Info panel ───
        function showInfoPanel(node) {
            var panel = document.getElementById('info-panel');
            document.getElementById('info-dot').style.background = node.color;
            document.getElementById('info-dot').style.boxShadow = '0 0 8px ' + node.color;
            document.getElementById('info-name').textContent = node.name;

            var meta = '';
            if (node.group) meta += '<div class="text-gray-500">' + node.group + '</div>';
            if (node.type) meta += '<div class="text-gray-500">' + node.type + '</div>';
            var m = node.metrics;
            if (m) {
                if (m.items_total > 0) {
                    var pct = Math.round(m.items_done / m.items_total * 100);
                    meta += '<div class="mt-1">Items: <span class="text-white">' + m.items_done + '/' + m.items_total + '</span> <span class="text-gray-600">(' + pct + '%)</span></div>';
                }
                if (m.time_h > 0) meta += '<div>Zeit: <span class="text-white">' + m.time_h + 'h</span> <span class="text-gray-600">(abger. ' + m.time_billed_h + 'h)</span></div>';
                if (m.links_count > 0) meta += '<div>Links: <span class="text-white">' + m.links_count + '</span></div>';
            }
            document.getElementById('info-meta').innerHTML = meta;

            var actions = '';
            if (node.id && node.id.startsWith('e')) {
                var eid = node.id.substring(1);
                actions += '<a href="/organization/entities/' + eid + '" class="px-2.5 py-1 bg-white/10 text-white rounded hover:bg-white/20 transition-colors flex items-center gap-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Details</a>';
                actions += '<a href="/organization/entities/' + eid + '/mindmap" class="px-2.5 py-1 bg-white/5 text-gray-400 rounded hover:bg-white/10 transition-colors flex items-center gap-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Hierhin fliegen</a>';
            } else if (node.url) {
                actions += '<a href="' + node.url + '" target="_blank" class="px-2.5 py-1 bg-white/10 text-white rounded hover:bg-white/20 transition-colors flex items-center gap-1"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg> Im Modul öffnen</a>';
            }
            document.getElementById('info-actions').innerHTML = actions;
            panel.classList.remove('hidden');
        }

        function hideInfoPanel() {
            document.getElementById('info-panel').classList.add('hidden');
        }

        // ─── LOD ───
        var tick = 0;
        graph.onEngineTick(function() {
            if (++tick % 6 !== 0) return;
            var cam = graph.camera().position;
            var currentData = graph.graphData();
            currentData.nodes.forEach(function(node) {
                if (!node.__threeObj) return;
                var label = node.__threeObj.getObjectByName('label');
                if (!label) return;

                var dx = cam.x - (node.x || 0), dy = cam.y - (node.y || 0), dz = cam.z - (node.z || 0);
                var dist = Math.sqrt(dx * dx + dy * dy + dz * dz);

                var isLinked = node.category && node.category !== 'entity';
                var isCenter = node.val > 15;
                var depth = node.depth || 0;
                // Top-level always visible, all others show when camera is close
                var threshold = isCenter ? 9999 : (!isLinked && depth <= 1) ? 9999 : 120;

                if (focusedNodeId) {
                    var nb = neighbors[focusedNodeId];
                    if (node.id === focusedNodeId || (nb && nb.has(node.id))) threshold = 9999;
                }

                label.visible = dist < threshold;

                // Fade opacity
                var sphere = node.__threeObj.children[0];
                if (sphere && sphere.material) {
                    var op = dist < 150 ? 1.0 : Math.max(0.15, 1.0 - (dist - 150) / 300);
                    sphere.material.opacity = op;
                    sphere.material.transparent = op < 1;
                }
            });

            // Relation link labels LOD — show only when camera is close
            currentData.links.forEach(function(link) {
                if (link.ltype !== 'relation' || !link.__lineObj) return;
                var sprite = link.__lineObj.__linkThreeObj || link.__curve;
                // ForceGraph3D stores the extended three object on the link
                var labelSprite = link.__threeObj;
                if (!labelSprite || labelSprite.name !== 'rel_label') return;

                var sx = (link.source && link.source.x) || 0;
                var sy = (link.source && link.source.y) || 0;
                var sz = (link.source && link.source.z) || 0;
                var tx = (link.target && link.target.x) || 0;
                var ty = (link.target && link.target.y) || 0;
                var tz = (link.target && link.target.z) || 0;
                var mx = (sx + tx) / 2, my = (sy + ty) / 2, mz = (sz + tz) / 2;
                var ddx = cam.x - mx, ddy = cam.y - my, ddz = cam.z - mz;
                var d = Math.sqrt(ddx * ddx + ddy * ddy + ddz * ddz);
                labelSprite.visible = d < 180;
            });
        });

        // ─── Sidebar + Legend ───
        buildSidebar();
        buildLegend();

        function buildLegend() {
            var el = document.getElementById('legend');
            el.innerHTML = '';

            // Aggregate links by ltype, then by sub-key:
            //   hierarchy   → single entry
            //   relation    → per rel_label
            //   entity_link → per category (= morph alias)
            var groups = { hierarchy: {}, relation: {}, entity_link: {} };
            allLinks.forEach(function(l) {
                var ltype = l.ltype || 'entity_link';
                if (!groups[ltype]) groups[ltype] = {};
                var subKey, color;
                if (ltype === 'hierarchy') {
                    subKey = 'Hierarchie';
                    color = l.color;
                } else if (ltype === 'relation') {
                    subKey = l.rel_label || 'Beziehung';
                    color = l.color;
                } else {
                    var s = typeof l.source === 'object' ? l.source : null;
                    var t = typeof l.target === 'object' ? l.target : null;
                    var targetNode = t || allNodes.find(function(n) { return n.id === l.target; });
                    var cat = targetNode && targetNode.category ? targetNode.category : 'link';
                    subKey = (categories[cat] && categories[cat].label) || cat;
                    color = l.color;
                }
                if (!groups[ltype][subKey]) groups[ltype][subKey] = { color: color, count: 0 };
                groups[ltype][subKey].count++;
            });

            var sectionTitles = {
                hierarchy: 'Hierarchie',
                relation: 'Beziehungen',
                entity_link: 'Verknüpfungen',
            };
            var first = true;
            ['hierarchy', 'relation', 'entity_link'].forEach(function(ltype) {
                var keys = Object.keys(groups[ltype] || {});
                if (keys.length === 0) return;

                if (!first) {
                    var sep = document.createElement('div');
                    sep.className = 'border-t border-gray-700/50 my-1';
                    el.appendChild(sep);
                }
                first = false;

                var header = document.createElement('div');
                header.className = 'px-2 pt-1 pb-0.5 text-[10px] uppercase tracking-wider text-gray-500';
                header.textContent = sectionTitles[ltype];
                el.appendChild(header);

                keys.forEach(function(key) {
                    var g = groups[ltype][key];
                    var row = document.createElement('div');
                    row.className = 'flex items-center gap-2 px-2 py-1 rounded select-none';
                    row.innerHTML =
                        '<span class="inline-block shrink-0" style="width:18px;height:2px;background:' + g.color + ';box-shadow:0 0 4px ' + g.color + '60"></span>' +
                        '<span class="flex-1 text-gray-300 truncate">' + key + '</span>' +
                        '<span class="text-gray-600 tabular-nums">' + g.count + '</span>';
                    el.appendChild(row);
                });
            });
        }

        // ─── Collapse handling ───
        document.querySelectorAll('[data-collapse-target]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var target = document.getElementById(btn.dataset.collapseTarget);
                var icon = btn.querySelector('.collapse-icon');
                if (!target) return;
                var isHidden = target.classList.toggle('hidden');
                if (icon) icon.style.transform = isHidden ? 'rotate(180deg)' : '';
            });
        });

        // ─── Zoom buttons ───
        document.getElementById('btn-zoom-in').addEventListener('click', function() {
            var cam = graph.camera().position;
            var lookAt = graph.controls().target || { x: 0, y: 0, z: 0 };
            var factor = 0.7;
            graph.cameraPosition(
                { x: lookAt.x + (cam.x - lookAt.x) * factor, y: lookAt.y + (cam.y - lookAt.y) * factor, z: lookAt.z + (cam.z - lookAt.z) * factor },
                lookAt, 400
            );
        });
        document.getElementById('btn-zoom-out').addEventListener('click', function() {
            var cam = graph.camera().position;
            var lookAt = graph.controls().target || { x: 0, y: 0, z: 0 };
            var factor = 1.4;
            graph.cameraPosition(
                { x: lookAt.x + (cam.x - lookAt.x) * factor, y: lookAt.y + (cam.y - lookAt.y) * factor, z: lookAt.z + (cam.z - lookAt.z) * factor },
                lookAt, 400
            );
        });

        // ─── Home button ───
        document.getElementById('btn-home').addEventListener('click', function() {
            focusedNodeId = null;
            hideInfoPanel();
            graph.cameraPosition({ x: 0, y: 0, z: 350 }, { x: 0, y: 0, z: 0 }, 800);
        });

        // ─── Pan buttons ───
        document.querySelectorAll('[data-pan]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var cam = graph.camera().position;
                var lookAt = graph.controls().target || { x: 0, y: 0, z: 0 };
                var step = 40;
                var dx = 0, dy = 0;
                switch (btn.dataset.pan) {
                    case 'up': dy = step; break;
                    case 'down': dy = -step; break;
                    case 'left': dx = -step; break;
                    case 'right': dx = step; break;
                }
                graph.cameraPosition(
                    { x: cam.x + dx, y: cam.y + dy, z: cam.z },
                    { x: lookAt.x + dx, y: lookAt.y + dy, z: lookAt.z },
                    300
                );
            });
        });

        // ─── Fullscreen ───
        document.getElementById('btn-fullscreen').addEventListener('click', function() {
            var el = document.getElementById('3d-graph').parentElement;
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (el.requestFullscreen) {
                el.requestFullscreen();
            }
        });

        // ─── Keyboard navigation ───
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT') return;
            var cam = graph.camera().position;
            var lookAt = graph.controls().target || { x: 0, y: 0, z: 0 };
            var step = 30;
            var handled = true;

            switch (e.key) {
                case 'ArrowUp': case 'w': case 'W':
                    graph.cameraPosition({ x: cam.x, y: cam.y + step, z: cam.z }, { x: lookAt.x, y: lookAt.y + step, z: lookAt.z }, 200);
                    break;
                case 'ArrowDown': case 's': case 'S':
                    graph.cameraPosition({ x: cam.x, y: cam.y - step, z: cam.z }, { x: lookAt.x, y: lookAt.y - step, z: lookAt.z }, 200);
                    break;
                case 'ArrowLeft': case 'a': case 'A':
                    graph.cameraPosition({ x: cam.x - step, y: cam.y, z: cam.z }, { x: lookAt.x - step, y: lookAt.y, z: lookAt.z }, 200);
                    break;
                case 'ArrowRight': case 'd': case 'D':
                    graph.cameraPosition({ x: cam.x + step, y: cam.y, z: cam.z }, { x: lookAt.x + step, y: lookAt.y, z: lookAt.z }, 200);
                    break;
                case '+': case '=':
                    document.getElementById('btn-zoom-in').click();
                    break;
                case '-':
                    document.getElementById('btn-zoom-out').click();
                    break;
                case 'Escape':
                    document.getElementById('btn-home').click();
                    break;
                default:
                    handled = false;
            }
            if (handled) e.preventDefault();
        });

        // ─── Resize when terminal/sidebar changes ───
        new ResizeObserver(function() {
            graph.width(container.clientWidth).height(container.clientHeight);
        }).observe(container);

        // ─── Timeline slider ───
        var timelineDates = JSON.parse(document.getElementById('timeline-dates').textContent || '[]');
        var timelineRange = document.getElementById('timeline-range');
        var timelineDate = document.getElementById('timeline-date');
        var timelineBadge = document.getElementById('timeline-badge');

        function applyGraphUpdate(newData) {
            allNodes = newData.nodes;
            allLinks = newData.links;
            categories = newData.categories;
            entityGroups = newData.entityGroups;

            neighbors = {};
            allLinks.forEach(function(l) {
                var s = typeof l.source === 'object' ? l.source.id : l.source;
                var t = typeof l.target === 'object' ? l.target.id : l.target;
                if (!neighbors[s]) neighbors[s] = new Set();
                if (!neighbors[t]) neighbors[t] = new Set();
                neighbors[s].add(t);
                neighbors[t].add(s);
            });

            filters = {};
            Object.keys(categories).forEach(function(k) { filters[k] = true; });
            groupFilters = {};
            Object.keys(entityGroups).forEach(function(k) { groupFilters[k] = true; });

            if (dimensions.vsm) assignVsmGridPositions();
            graph.graphData(getFilteredData());
            buildSidebar();
            document.getElementById('legend').innerHTML = '';
            buildLegend();
            if (dimensions.vsm) refreshVsmAll();
        }

        Livewire.on('graph-data-updated', function(event) {
            applyGraphUpdate(event.data);
        });

        function updateTimelineBadge(isLive) {
            if (!timelineBadge) return;
            timelineBadge.textContent = isLive ? 'Live' : 'Snap';
            timelineBadge.className = 'px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase tracking-wider ' +
                (isLive
                    ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                    : 'bg-blue-500/20 text-blue-400 border border-blue-500/30');
        }

        function getLabelForIdx(idx) {
            if (idx >= timelineDates.length) return 'Live';
            var s = timelineDates[idx];
            return (s && s.label) ? s.label : 'Live';
        }

        if (timelineRange && timelineDates.length > 0) {
            // Prevent 3d-force-graph from stealing pointer events during drag
            ['pointerdown', 'mousedown', 'touchstart'].forEach(function(evt) {
                timelineRange.addEventListener(evt, function(e) { e.stopPropagation(); }, { passive: true });
            });

            timelineRange.addEventListener('input', function() {
                var idx = parseInt(timelineRange.value);
                if (timelineDate) timelineDate.textContent = getLabelForIdx(idx);
                updateTimelineBadge(idx >= timelineDates.length);
            });

            timelineRange.addEventListener('change', function() {
                var idx = parseInt(timelineRange.value);
                if (idx >= timelineDates.length) {
                    @this.set('snapshotDate', null);
                } else {
                    var s = timelineDates[idx];
                    @this.set('snapshotDate', s.key);
                }
            });
        }
    </script>
</x-ui-page>
