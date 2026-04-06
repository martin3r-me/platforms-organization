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

    <script>
        (function() {
            function loadScript(src) {
                return new Promise(function(resolve, reject) {
                    if (document.querySelector('script[src="' + src + '"]')) { resolve(); return; }
                    var s = document.createElement('script');
                    s.src = src;
                    s.onload = resolve;
                    s.onerror = reject;
                    document.head.appendChild(s);
                });
            }

            function makeTextSprite(text, color, isCenter) {
                var canvas = document.createElement('canvas');
                var ctx = canvas.getContext('2d');
                var fontSize = isCenter ? 28 : 18;
                ctx.font = (isCenter ? 'bold ' : '') + fontSize + 'px -apple-system, system-ui, sans-serif';
                var textWidth = ctx.measureText(text).width;
                var padding = 12;
                canvas.width = textWidth + padding * 2;
                canvas.height = fontSize + padding * 2;

                ctx.font = (isCenter ? 'bold ' : '') + fontSize + 'px -apple-system, system-ui, sans-serif';
                ctx.fillStyle = 'rgba(0,0,0,0.6)';
                ctx.roundRect(0, 0, canvas.width, canvas.height, 6);
                ctx.fill();
                ctx.fillStyle = '#ffffff';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(text, canvas.width / 2, canvas.height / 2);

                var THREE = window.THREE;
                var texture = new THREE.CanvasTexture(canvas);
                var material = new THREE.SpriteMaterial({ map: texture, depthTest: false });
                var sprite = new THREE.Sprite(material);
                var scale = isCenter ? 0.6 : 0.4;
                sprite.scale.set(canvas.width * scale / 10, canvas.height * scale / 10, 1);

                return sprite;
            }

            loadScript('https://unpkg.com/3d-force-graph@1').then(function() {
                var container = document.getElementById('3d-graph');
                if (!container || typeof ForceGraph3D === 'undefined') return;

                var data = JSON.parse(document.getElementById('mindmap-data').textContent);
                var THREE = window.THREE;

                var graph = ForceGraph3D()(container)
                    .graphData(data)
                    .backgroundColor('#ffffff')
                    .nodeThreeObject(function(node) {
                        var group = new THREE.Group();

                        var radius = node.val > 8 ? 6 : 3;
                        var sphere = new THREE.Mesh(
                            new THREE.SphereGeometry(radius, 16, 16),
                            new THREE.MeshLambertMaterial({ color: node.color })
                        );
                        group.add(sphere);

                        var label = makeTextSprite(node.name, node.color, node.val > 8);
                        label.position.y = -(radius + 4);
                        group.add(label);

                        return group;
                    })
                    .linkColor('color')
                    .linkWidth('width')
                    .linkOpacity(0.7)
                    .onNodeClick(function(node) {
                        var distance = 120;
                        var distRatio = 1 + distance / Math.hypot(node.x, node.y, node.z);
                        graph.cameraPosition(
                            { x: node.x * distRatio, y: node.y * distRatio, z: node.z * distRatio },
                            { x: node.x, y: node.y, z: node.z },
                            1500
                        );
                    })
                    .cameraPosition({ x: 0, y: 0, z: 300 });
            });
        })();
    </script>
</x-ui-page>
