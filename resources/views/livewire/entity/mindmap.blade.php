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

        {{-- Navigation hint --}}
        <div id="nav-hint" class="absolute bottom-4 right-4 bg-black/60 text-white text-xs px-3 py-2 rounded-lg pointer-events-none transition-opacity duration-500" style="opacity:1">
            Klick = Fokussieren &middot; Doppelklick = Details &middot; Scroll = Zoom
        </div>
    </div>

    <script id="mindmap-data" type="application/json">@json($this->graphData)</script>

    <script type="module">
        import * as THREE from 'https://esm.sh/three@0.179.0';
        import ForceGraph3D from 'https://esm.sh/3d-force-graph@1.80.0?deps=three@0.179.0';

        // --- Label sprite factory ---
        var labelCache = {};
        function makeLabel(text, size) {
            var key = text + '|' + size;
            if (labelCache[key]) return labelCache[key].clone();

            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            var fontSize = size;
            ctx.font = (size > 36 ? 'bold ' : '') + fontSize + 'px -apple-system, system-ui, sans-serif';
            var tw = ctx.measureText(text).width;
            var pad = 12;
            canvas.width = tw + pad * 2;
            canvas.height = fontSize + pad * 2;

            ctx.font = (size > 36 ? 'bold ' : '') + fontSize + 'px -apple-system, system-ui, sans-serif';
            ctx.fillStyle = 'rgba(15,23,42,0.8)';
            ctx.beginPath();
            ctx.roundRect(0, 0, canvas.width, canvas.height, 6);
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, canvas.width / 2, canvas.height / 2);

            var texture = new THREE.CanvasTexture(canvas);
            var material = new THREE.SpriteMaterial({ map: texture, depthTest: false });
            var sprite = new THREE.Sprite(material);
            sprite.scale.set(canvas.width / 20, canvas.height / 20, 1);
            labelCache[key] = sprite;
            return sprite.clone();
        }

        // --- Distance helper ---
        function cameraDist(cam, node) {
            return Math.sqrt(
                Math.pow(cam.x - (node.x || 0), 2) +
                Math.pow(cam.y - (node.y || 0), 2) +
                Math.pow(cam.z - (node.z || 0), 2)
            );
        }

        // --- Build adjacency for neighbor lookup ---
        var container = document.getElementById('3d-graph');
        var data = JSON.parse(document.getElementById('mindmap-data').textContent);

        var neighbors = {};
        data.links.forEach(function(l) {
            var s = typeof l.source === 'object' ? l.source.id : l.source;
            var t = typeof l.target === 'object' ? l.target.id : l.target;
            if (!neighbors[s]) neighbors[s] = new Set();
            if (!neighbors[t]) neighbors[t] = new Set();
            neighbors[s].add(t);
            neighbors[t].add(s);
        });

        // --- Graph setup ---
        var graph = ForceGraph3D()(container)
            .backgroundColor('#ffffff');

        // Stronger repulsion, more link distance for breathing room
        graph.d3Force('charge').strength(-500);
        graph.d3Force('link').distance(80);

        // Track camera for LOD
        var lastCamPos = { x: 0, y: 0, z: 500 };
        var focusedNodeId = null;

        graph.graphData(data)
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

                // Activity glow
                if (hasActivity && !isLinked) {
                    var intensity = Math.min(m.time_h / 50, 1);
                    var glow = new THREE.Mesh(
                        new THREE.SphereGeometry(radius * 1.8, 16, 16),
                        new THREE.MeshBasicMaterial({
                            color: '#EF4444',
                            transparent: true,
                            opacity: 0.08 + intensity * 0.15,
                        })
                    );
                    group.add(glow);
                }

                // Label - always create but control visibility in tick
                var labelText = node.type ? node.type + ': ' + node.name : node.name;
                var fontSize = isCenter ? 44 : (isLinked ? 24 : 32);
                var label = makeLabel(isLinked ? node.name : labelText, fontSize);
                label.position.y = -(radius + 3);
                label.visible = false; // start hidden, tick controls visibility
                label.name = 'label';
                group.add(label);

                return group;
            })
            .nodeLabel(function(node) {
                if (!node.metrics) return node.name;
                var m = node.metrics;
                var lines = ['<b>' + node.name + '</b>'];
                if (node.group) lines.push('<span style="color:#888">' + node.group + '</span>');
                if (m.items_total > 0) {
                    var pct = Math.round(m.items_done / m.items_total * 100);
                    lines.push('Items: ' + m.items_done + '/' + m.items_total + ' (' + pct + '%)');
                }
                if (m.time_h > 0) lines.push('Zeit: ' + m.time_h + 'h (abgerechnet: ' + m.time_billed_h + 'h)');
                if (m.links_count > 0) lines.push('Links: ' + m.links_count);
                return '<div style="text-align:left;line-height:1.5">' + lines.join('<br/>') + '</div>';
            })
            .linkColor('color')
            .linkWidth('width')
            .linkOpacity(0.5)
            .onNodeClick(function(node) {
                focusedNodeId = node.id;

                // Fly close to the node
                var dist = 60 + (node.__radius || 4) * 3;
                var distRatio = 1 + dist / Math.hypot(node.x, node.y, node.z);
                graph.cameraPosition(
                    { x: node.x * distRatio, y: node.y * distRatio, z: node.z * distRatio },
                    { x: node.x, y: node.y, z: node.z },
                    1000
                );
            })
            .onBackgroundClick(function() {
                focusedNodeId = null;
                // Zoom out to overview
                graph.cameraPosition({ x: 0, y: 0, z: 500 }, { x: 0, y: 0, z: 0 }, 1000);
            })
            .cameraPosition({ x: 0, y: 0, z: 500 });

        // --- Double-click: navigate to entity detail page ---
        var lastClickTime = 0;
        var lastClickNode = null;
        var origOnNodeClick = graph.onNodeClick();
        graph.onNodeClick(function(node) {
            var now = Date.now();
            if (lastClickNode === node && now - lastClickTime < 400) {
                // Double click - navigate
                if (node.id && node.id.startsWith('e')) {
                    var entityId = node.id.substring(1);
                    window.location.href = '/organization/entities/' + entityId;
                }
                return;
            }
            lastClickNode = node;
            lastClickTime = now;

            // Single click - fly to
            focusedNodeId = node.id;
            var dist = 60 + (node.__radius || 4) * 3;
            var distRatio = 1 + dist / Math.hypot(node.x, node.y, node.z);
            graph.cameraPosition(
                { x: node.x * distRatio, y: node.y * distRatio, z: node.z * distRatio },
                { x: node.x, y: node.y, z: node.z },
                1000
            );
        });

        // --- LOD: show/hide labels based on camera distance ---
        var tickCounter = 0;
        graph.onEngineTick(function() {
            tickCounter++;
            if (tickCounter % 10 !== 0) return; // Only check every 10 frames

            var cam = graph.camera().position;
            lastCamPos = { x: cam.x, y: cam.y, z: cam.z };

            data.nodes.forEach(function(node) {
                if (!node.__threeObj) return;
                var label = node.__threeObj.getObjectByName('label');
                if (!label) return;

                var dist = cameraDist(cam, node);
                var isLinked = node.val < 5;
                var isCenter = node.val > 15;

                // Thresholds: center always, entities < 200, linked items < 80
                var threshold = isCenter ? 9999 : (isLinked ? 80 : 200);

                // If focused, show focused node + its neighbors always
                if (focusedNodeId) {
                    var isNeighbor = neighbors[focusedNodeId] && neighbors[focusedNodeId].has(node.id);
                    if (node.id === focusedNodeId || isNeighbor) {
                        threshold = 9999;
                    }
                }

                label.visible = dist < threshold;
            });
        });

        // Fade out hint after 5s
        setTimeout(function() {
            var hint = document.getElementById('nav-hint');
            if (hint) hint.style.opacity = '0';
        }, 5000);
    </script>
</x-ui-page>
