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
        <div id="sidebar" class="absolute top-3 left-3 z-20 w-56 bg-white/95 backdrop-blur border border-gray-200 rounded-xl shadow-xl text-xs overflow-hidden">
            <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 font-bold text-gray-700 text-sm flex items-center gap-2">
                @svg('heroicon-o-funnel', 'w-4 h-4') Filter
            </div>
            <div id="filter-list" class="p-2 space-y-1 max-h-[60vh] overflow-y-auto"></div>
        </div>

        {{-- Info panel --}}
        <div id="info-panel" class="absolute bottom-3 left-3 z-20 w-72 bg-white/95 backdrop-blur border border-gray-200 rounded-xl shadow-xl text-xs hidden">
            <div class="p-3">
                <div class="flex items-center gap-2 mb-2">
                    <div id="info-dot" class="w-3 h-3 rounded-full shrink-0"></div>
                    <span id="info-name" class="font-bold text-sm text-gray-900 truncate"></span>
                </div>
                <div id="info-meta" class="text-gray-500 space-y-0.5"></div>
                <div id="info-actions" class="mt-2 flex gap-2"></div>
            </div>
        </div>

        {{-- Nav hint --}}
        <div id="nav-hint" class="absolute bottom-3 right-3 bg-black/60 text-white text-xs px-3 py-2 rounded-lg pointer-events-none transition-opacity duration-1000">
            Klick = Fokus &middot; Doppelklick = Details &middot; Hintergrund = Zurück
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

            // Categories
            Object.keys(categories).forEach(function(key) {
                var cat = categories[key];
                var row = document.createElement('label');
                row.className = 'flex items-center gap-2 px-2 py-1.5 rounded hover:bg-gray-50 cursor-pointer select-none';
                row.innerHTML =
                    '<input type="checkbox" ' + (filters[key] ? 'checked' : '') + ' class="rounded border-gray-300 text-blue-500 w-3.5 h-3.5" data-cat="' + key + '">' +
                    '<span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:' + cat.color + '"></span>' +
                    '<span class="flex-1 text-gray-700">' + cat.label + '</span>' +
                    '<span class="text-gray-400 tabular-nums">' + cat.count + '</span>';
                el.appendChild(row);
            });

            // Separator + entity sub-groups
            if (Object.keys(entityGroups).length > 0) {
                var sep = document.createElement('div');
                sep.className = 'border-t border-gray-100 my-1.5 mx-2';
                el.appendChild(sep);

                var subHead = document.createElement('div');
                subHead.className = 'px-2 py-1 text-gray-400 font-medium uppercase tracking-wider';
                subHead.style.fontSize = '10px';
                subHead.textContent = 'Entity-Gruppen';
                el.appendChild(subHead);

                Object.keys(entityGroups).forEach(function(key) {
                    var g = entityGroups[key];
                    var row = document.createElement('label');
                    row.className = 'flex items-center gap-2 px-2 py-1 rounded hover:bg-gray-50 cursor-pointer select-none ml-2';
                    row.innerHTML =
                        '<input type="checkbox" ' + (groupFilters[key] ? 'checked' : '') + ' class="rounded border-gray-300 text-blue-500 w-3 h-3" data-group="' + key + '">' +
                        '<span class="w-2 h-2 rounded-full shrink-0" style="background:' + g.color + '"></span>' +
                        '<span class="flex-1 text-gray-600">' + key + '</span>' +
                        '<span class="text-gray-400 tabular-nums">' + g.count + '</span>';
                    el.appendChild(row);
                });
            }

            // Bind events
            el.querySelectorAll('[data-cat]').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    filters[cb.dataset.cat] = cb.checked;
                    applyFilters();
                });
            });
            el.querySelectorAll('[data-group]').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    groupFilters[cb.dataset.group] = cb.checked;
                    applyFilters();
                });
            });
        }

        function applyFilters() {
            graph.graphData(getFilteredData());
        }

        // ─── Label factory ───
        function makeLabel(text, fontSize) {
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            var bold = fontSize > 36;
            ctx.font = (bold ? 'bold ' : '') + fontSize + 'px -apple-system, system-ui, sans-serif';
            var tw = ctx.measureText(text).width;
            var pad = 10;
            canvas.width = tw + pad * 2;
            canvas.height = fontSize + pad * 2;
            ctx.font = (bold ? 'bold ' : '') + fontSize + 'px -apple-system, system-ui, sans-serif';
            ctx.fillStyle = 'rgba(15,23,42,0.8)';
            ctx.beginPath();
            ctx.roundRect(0, 0, canvas.width, canvas.height, 5);
            ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, canvas.width / 2, canvas.height / 2);
            var tex = new THREE.CanvasTexture(canvas);
            var mat = new THREE.SpriteMaterial({ map: tex, depthTest: false });
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
            .backgroundColor('#ffffff');

        graph.d3Force('charge').strength(-400);
        graph.d3Force('link').distance(70);

        // Add clustering force: same-group nodes attract slightly
        graph.d3Force('cluster', function(alpha) {
            var centroids = {};
            var counts = {};
            allNodes.forEach(function(n) {
                if (!n.x) return;
                var g = n.group || n.category || 'x';
                if (!centroids[g]) { centroids[g] = { x: 0, y: 0, z: 0 }; counts[g] = 0; }
                centroids[g].x += n.x;
                centroids[g].y += n.y;
                centroids[g].z += n.z;
                counts[g]++;
            });
            Object.keys(centroids).forEach(function(g) {
                centroids[g].x /= counts[g];
                centroids[g].y /= counts[g];
                centroids[g].z /= counts[g];
            });
            var strength = alpha * 0.3;
            allNodes.forEach(function(n) {
                if (!n.x) return;
                var g = n.group || n.category || 'x';
                var c = centroids[g];
                if (!c) return;
                n.vx += (c.x - n.x) * strength;
                n.vy += (c.y - n.y) * strength;
                n.vz += (c.z - n.z) * strength;
            });
        });

        graph.graphData(getFilteredData())
            .nodeThreeObject(function(node) {
                var group = new THREE.Group();
                var isCenter = node.val > 15;
                var isLinked = node.val < 5;
                var m = node.metrics;
                var hasActivity = m && m.time_h > 0;

                var baseRadius = isCenter ? 8 : (isLinked ? 2 : 4);
                var radius = baseRadius + (m ? Math.min(m.items_total * 0.3, 4) : 0);
                node.__radius = radius;

                var sphere = new THREE.Mesh(
                    new THREE.SphereGeometry(radius, 20, 20),
                    new THREE.MeshLambertMaterial({ color: node.color })
                );
                group.add(sphere);

                if (hasActivity && !isLinked) {
                    var intensity = Math.min(m.time_h / 50, 1);
                    group.add(new THREE.Mesh(
                        new THREE.SphereGeometry(radius * 1.8, 12, 12),
                        new THREE.MeshBasicMaterial({ color: '#EF4444', transparent: true, opacity: 0.08 + intensity * 0.15 })
                    ));
                }

                var labelText = node.type ? node.type + ': ' + node.name : node.name;
                var fontSize = isCenter ? 40 : (isLinked ? 22 : 30);
                var label = makeLabel(isLinked ? node.name : labelText, fontSize);
                label.position.y = -(radius + 3);
                label.visible = false;
                label.name = 'label';
                group.add(label);

                return group;
            })
            .nodeLabel(function(node) {
                if (!node.metrics) return '<b>' + node.name + '</b>' + (node.type ? '<br/><span style="color:#888">' + node.type + '</span>' : '');
                var m = node.metrics;
                var lines = ['<b>' + node.name + '</b>'];
                if (node.group) lines.push('<span style="color:#888">' + node.group + '</span>');
                if (m.items_total > 0) lines.push('Items: ' + m.items_done + '/' + m.items_total + ' (' + Math.round(m.items_done / m.items_total * 100) + '%)');
                if (m.time_h > 0) lines.push('Zeit: ' + m.time_h + 'h');
                return '<div style="text-align:left;line-height:1.5">' + lines.join('<br/>') + '</div>';
            })
            .linkColor('color')
            .linkWidth('width')
            .linkOpacity(0.4)
            .warmupTicks(60)
            .cooldownTicks(100);

        // ─── Click: fly to node ───
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

            focusedNodeId = node.id;
            showInfoPanel(node);

            var dist = 40 + (node.__radius || 4) * 4;
            var distRatio = 1 + dist / Math.hypot(node.x, node.y, node.z);
            graph.cameraPosition(
                { x: node.x * distRatio, y: node.y * distRatio, z: node.z * distRatio },
                { x: node.x, y: node.y, z: node.z },
                800
            );
        });

        graph.onBackgroundClick(function() {
            focusedNodeId = null;
            hideInfoPanel();
            graph.cameraPosition({ x: 0, y: 0, z: 400 }, { x: 0, y: 0, z: 0 }, 800);
        });

        graph.cameraPosition({ x: 0, y: 0, z: 400 });

        // ─── Info panel ───
        function showInfoPanel(node) {
            var panel = document.getElementById('info-panel');
            document.getElementById('info-dot').style.background = node.color;
            document.getElementById('info-name').textContent = node.name;

            var meta = '';
            if (node.group) meta += '<div>' + node.group + '</div>';
            if (node.type) meta += '<div>' + node.type + '</div>';
            var m = node.metrics;
            if (m) {
                if (m.items_total > 0) meta += '<div>Items: ' + m.items_done + ' / ' + m.items_total + '</div>';
                if (m.time_h > 0) meta += '<div>Zeit: ' + m.time_h + 'h (abger. ' + m.time_billed_h + 'h)</div>';
            }
            document.getElementById('info-meta').innerHTML = meta;

            var actions = '';
            if (node.id && node.id.startsWith('e')) {
                actions += '<a href="/organization/entities/' + node.id.substring(1) + '" class="px-2 py-1 bg-gray-900 text-white rounded text-xs hover:bg-gray-700">Details</a>';
                actions += '<a href="/organization/entities/' + node.id.substring(1) + '/mindmap" class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs hover:bg-gray-200">Mindmap ab hier</a>';
            }
            document.getElementById('info-actions').innerHTML = actions;
            panel.classList.remove('hidden');
        }

        function hideInfoPanel() {
            document.getElementById('info-panel').classList.add('hidden');
        }

        // ─── LOD: label visibility ───
        var tick = 0;
        graph.onEngineTick(function() {
            if (++tick % 8 !== 0) return;
            var cam = graph.camera().position;
            var currentData = graph.graphData();
            currentData.nodes.forEach(function(node) {
                if (!node.__threeObj) return;
                var label = node.__threeObj.getObjectByName('label');
                if (!label) return;

                var dx = cam.x - (node.x || 0);
                var dy = cam.y - (node.y || 0);
                var dz = cam.z - (node.z || 0);
                var dist = Math.sqrt(dx * dx + dy * dy + dz * dz);

                var isLinked = node.val < 5;
                var isCenter = node.val > 15;
                var threshold = isCenter ? 9999 : (isLinked ? 60 : 160);

                if (focusedNodeId) {
                    var nb = neighbors[focusedNodeId];
                    if (node.id === focusedNodeId || (nb && nb.has(node.id))) threshold = 9999;
                }

                label.visible = dist < threshold;

                // Fade sphere opacity based on distance (far = more transparent)
                var sphere = node.__threeObj.children[0];
                if (sphere && sphere.material) {
                    sphere.material.transparent = true;
                    sphere.material.opacity = dist < 200 ? 1.0 : Math.max(0.3, 1.0 - (dist - 200) / 400);
                }
            });
        });

        // ─── Init sidebar ───
        buildSidebar();

        // Fade hint
        setTimeout(function() {
            var h = document.getElementById('nav-hint');
            if (h) h.style.opacity = '0';
        }, 5000);
    </script>
</x-ui-page>
