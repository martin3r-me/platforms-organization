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
    </div>

    <script id="mindmap-data" type="application/json">@json($this->graphData)</script>

    <script type="module">
        import * as THREE from 'https://esm.sh/three@0.179.0';
        import ForceGraph3D from 'https://esm.sh/3d-force-graph@1.80.0?deps=three@0.179.0';

        function makeLabel(text, isCenter) {
            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            var fontSize = isCenter ? 48 : 32;
            ctx.font = (isCenter ? 'bold ' : '') + fontSize + 'px -apple-system, system-ui, sans-serif';
            var tw = ctx.measureText(text).width;
            var pad = 14;
            canvas.width = tw + pad * 2;
            canvas.height = fontSize + pad * 2;

            ctx.font = (isCenter ? 'bold ' : '') + fontSize + 'px -apple-system, system-ui, sans-serif';
            ctx.fillStyle = 'rgba(15,23,42,0.75)';
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
            var scale = isCenter ? 0.7 : 0.5;
            sprite.scale.set(canvas.width * scale / 10, canvas.height * scale / 10, 1);
            return sprite;
        }

        var container = document.getElementById('3d-graph');
        var data = JSON.parse(document.getElementById('mindmap-data').textContent);

        var graph = ForceGraph3D()(container)
            .backgroundColor('#ffffff');

        graph.d3Force('charge').strength(-300);
        graph.d3Force('link').distance(60);

        graph.graphData(data)
            .nodeThreeObject(function(node) {
                var group = new THREE.Group();
                var isCenter = node.val > 15;
                var isLinked = node.val < 5;
                var m = node.metrics;
                var hasActivity = m && m.time_h > 0;

                // Radius: bigger if more items linked
                var baseRadius = isCenter ? 8 : (isLinked ? 2 : 4);
                var radius = baseRadius + (m ? Math.min(m.items_total * 0.3, 4) : 0);

                var sphere = new THREE.Mesh(
                    new THREE.SphereGeometry(radius, 20, 20),
                    new THREE.MeshLambertMaterial({ color: node.color })
                );
                group.add(sphere);

                // Activity glow ring for entities with time booked
                if (hasActivity && !isLinked) {
                    var glowRadius = radius * 1.8;
                    var intensity = Math.min(m.time_h / 50, 1);
                    var glow = new THREE.Mesh(
                        new THREE.SphereGeometry(glowRadius, 16, 16),
                        new THREE.MeshBasicMaterial({
                            color: '#EF4444',
                            transparent: true,
                            opacity: 0.08 + intensity * 0.15,
                        })
                    );
                    group.add(glow);
                }

                var labelText = node.type ? node.type + ': ' + node.name : node.name;
                var label = makeLabel(isLinked ? node.name : labelText, isCenter);
                label.position.y = -(radius + 3);
                group.add(label);

                return group;
            })
            .nodeLabel(function(node) {
                if (!node.metrics) return node.name;
                var m = node.metrics;
                var lines = ['<b>' + node.name + '</b>'];
                if (node.group) lines.push(node.group);
                if (m.items_total > 0) lines.push('Items: ' + m.items_done + '/' + m.items_total);
                if (m.time_h > 0) lines.push('Zeit: ' + m.time_h + 'h (davon ' + m.time_billed_h + 'h abgerechnet)');
                if (m.links_count > 0) lines.push('Links: ' + m.links_count);
                return lines.join('<br/>');
            })
            .linkColor('color')
            .linkWidth('width')
            .linkOpacity(0.6)
            .onNodeClick(function(node) {
                var distance = 120;
                var distRatio = 1 + distance / Math.hypot(node.x, node.y, node.z);
                graph.cameraPosition(
                    { x: node.x * distRatio, y: node.y * distRatio, z: node.z * distRatio },
                    { x: node.x, y: node.y, z: node.z },
                    1500
                );
            })
            .cameraPosition({ x: 0, y: 0, z: 500 });
    </script>
</x-ui-page>
