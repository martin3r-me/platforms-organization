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

    <div class="relative w-full" style="height: calc(100vh - 100px);">
        <div id="3d-graph" class="w-full h-full"></div>

        {{-- Sidebar --}}
        <div id="sidebar" class="absolute top-3 left-3 z-20 w-52 bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-xl shadow-2xl text-xs overflow-hidden">
            <div class="px-3 py-2 border-b border-gray-700/50 font-bold text-gray-300 text-sm flex items-center gap-2">
                @svg('heroicon-o-funnel', 'w-4 h-4 text-gray-500') Layer
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
        <div class="absolute top-3 right-3 z-20 flex flex-col gap-2">
            {{-- Zoom --}}
            <div class="bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-lg shadow-2xl overflow-hidden">
                <button id="btn-zoom-in" class="block w-9 h-9 flex items-center justify-center text-gray-300 hover:bg-white/10 hover:text-white transition-colors text-lg font-light border-b border-gray-700/50">+</button>
                <button id="btn-zoom-out" class="block w-9 h-9 flex items-center justify-center text-gray-300 hover:bg-white/10 hover:text-white transition-colors text-lg font-light">&minus;</button>
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
            {{-- Fullscreen --}}
            <button id="btn-fullscreen" class="bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-lg shadow-2xl w-9 h-9 flex items-center justify-center text-gray-400 hover:bg-white/10 hover:text-white transition-colors">
                @svg('heroicon-o-arrows-pointing-out', 'w-4 h-4')
            </button>
        </div>

        {{-- Legend --}}
        <div class="absolute bottom-3 right-3 z-20 bg-gray-900/90 backdrop-blur border border-gray-700/50 rounded-lg shadow-2xl p-2.5 text-xs">
            <div class="text-gray-500 font-medium uppercase tracking-wider mb-1.5" style="font-size:9px">Legende</div>
            <div class="space-y-1" id="legend"></div>
        </div>
    </div>

    <script id="mindmap-data" type="application/json">@json($this->graphData)</script>

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

        function getFilteredData() {
            var visibleIds = new Set();
            var nodes = allNodes.filter(function(n) {
                var cat = n.category || 'entity';
                if (!filters[cat]) return false;
                if (cat === 'entity' && n.group && !groupFilters[n.group]) return false;
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

            el.querySelectorAll('[data-cat]').forEach(function(cb) {
                cb.addEventListener('change', function() { filters[cb.dataset.cat] = cb.checked; graph.graphData(getFilteredData()); });
            });
            el.querySelectorAll('[data-group]').forEach(function(cb) {
                cb.addEventListener('change', function() { groupFilters[cb.dataset.group] = cb.checked; graph.graphData(getFilteredData()); });
            });
        }

        // ─── Label factory ───
        function makeLabel(text, fontSize, color) {
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            var bold = fontSize > 34;
            ctx.font = (bold ? '600 ' : '400 ') + fontSize + 'px -apple-system, system-ui, sans-serif';
            var tw = ctx.measureText(text).width;
            var pad = 10;
            canvas.width = tw + pad * 2;
            canvas.height = fontSize + pad * 2;
            // Transparent bg, colored text
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.font = (bold ? '600 ' : '400 ') + fontSize + 'px -apple-system, system-ui, sans-serif';
            ctx.fillStyle = color || '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, canvas.width / 2, canvas.height / 2);
            var tex = new THREE.CanvasTexture(canvas);
            var mat = new THREE.SpriteMaterial({ map: tex, depthTest: false, transparent: true });
            var sprite = new THREE.Sprite(mat);
            sprite.scale.set(canvas.width / 22, canvas.height / 22, 1);
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

        // Forces
        graph.d3Force('charge').strength(-600);
        graph.d3Force('link').distance(90);

        // Clustering force
        graph.d3Force('cluster', function(alpha) {
            var centroids = {};
            var counts = {};
            allNodes.forEach(function(n) {
                if (!n.x) return;
                var g = n.group || n.category || 'x';
                if (!centroids[g]) { centroids[g] = { x: 0, y: 0, z: 0 }; counts[g] = 0; }
                centroids[g].x += n.x; centroids[g].y += n.y; centroids[g].z += n.z;
                counts[g]++;
            });
            Object.keys(centroids).forEach(function(g) {
                centroids[g].x /= counts[g]; centroids[g].y /= counts[g]; centroids[g].z /= counts[g];
            });
            allNodes.forEach(function(n) {
                if (!n.x) return;
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
                var isLinked = node.val < 5;
                var m = node.metrics;
                var hasActivity = m && m.time_h > 0;

                var baseRadius = isCenter ? 7 : (isLinked ? 1.5 : 3);
                var radius = baseRadius + (m ? Math.min(m.items_total * 0.2, 3) : 0);
                node.__radius = radius;

                // Core sphere
                var sphere = new THREE.Mesh(
                    new THREE.SphereGeometry(radius, 24, 24),
                    new THREE.MeshPhongMaterial({
                        color: node.color,
                        emissive: node.color,
                        emissiveIntensity: isCenter ? 0.4 : 0.15,
                        shininess: 80,
                    })
                );
                group.add(sphere);

                // Glow aura
                if (!isLinked) {
                    var glowSize = radius * (isCenter ? 2.5 : 1.8);
                    var glowIntensity = isCenter ? 0.12 : (hasActivity ? 0.06 + Math.min(m.time_h / 80, 0.1) : 0.03);
                    group.add(new THREE.Mesh(
                        new THREE.SphereGeometry(glowSize, 12, 12),
                        new THREE.MeshBasicMaterial({ color: node.color, transparent: true, opacity: glowIntensity })
                    ));
                }

                // Label
                var labelText = node.type ? node.type + '  ' + node.name : node.name;
                var fontSize = isCenter ? 38 : (isLinked ? 20 : 28);
                var label = makeLabel(isLinked ? node.name : labelText, fontSize, isCenter ? '#ffffff' : node.color);
                label.position.y = -(radius + 2.5);
                label.visible = false;
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
            .linkWidth(function(l) { return (l.width || 1) * 0.6; })
            .linkOpacity(0.35)
            .linkDirectionalParticles(function(l) { return l.width > 1 ? 3 : 1; })
            .linkDirectionalParticleWidth(1.2)
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
            var dist = nbRadius * 1.8 + 20;
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
                actions += '<a href="/organization/entities/' + eid + '" class="px-2.5 py-1 bg-white/10 text-white rounded hover:bg-white/20 transition-colors">Details</a>';
                actions += '<a href="/organization/entities/' + eid + '/mindmap" class="px-2.5 py-1 bg-white/5 text-gray-400 rounded hover:bg-white/10 transition-colors">Hierhin fliegen</a>';
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

                var isLinked = node.val < 5;
                var isCenter = node.val > 15;
                var threshold = isCenter ? 9999 : (isLinked ? 50 : 140);

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
        });

        // ─── Sidebar + Legend ───
        buildSidebar();
        buildLegend();

        function buildLegend() {
            var el = document.getElementById('legend');
            var items = [];
            // Entity groups
            Object.keys(entityGroups).forEach(function(key) {
                items.push({ label: key, color: entityGroups[key].color });
            });
            // Linked types
            Object.keys(categories).forEach(function(key) {
                if (key !== 'entity') items.push({ label: categories[key].label, color: categories[key].color });
            });
            items.forEach(function(item) {
                var row = document.createElement('div');
                row.className = 'flex items-center gap-2';
                row.innerHTML = '<span class="w-2 h-2 rounded-full shrink-0" style="background:' + item.color + ';box-shadow:0 0 4px ' + item.color + '60"></span><span class="text-gray-400">' + item.label + '</span>';
                el.appendChild(row);
            });
        }

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
    </script>
</x-ui-page>
