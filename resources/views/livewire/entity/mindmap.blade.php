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

    <div class="relative w-full" style="height: calc(100vh - 100px);" id="mindmap-wrapper">
        <div id="3d-graph" class="w-full h-full"></div>
    </div>

    @script
    <script>
        const scripts = [
            'https://unpkg.com/three@0.175.0/build/three.min.js',
            'https://unpkg.com/3d-force-graph@1',
            'https://unpkg.com/three-spritetext@1',
        ];

        function loadScript(src) {
            return new Promise((resolve) => {
                if (document.querySelector('script[src="' + src + '"]')) {
                    resolve();
                    return;
                }
                const s = document.createElement('script');
                s.src = src;
                s.onload = resolve;
                document.head.appendChild(s);
            });
        }

        async function boot() {
            for (const src of scripts) {
                await loadScript(src);
            }

            const container = document.getElementById('3d-graph');
            if (!container || typeof ForceGraph3D === 'undefined') return;

            const data = {
                nodes: [{ id: 'center', label: '{{ $entity->name }}', color: '#3B82F6' }],
                links: [],
            };

            ForceGraph3D()(container)
                .graphData(data)
                .backgroundColor('rgba(0,0,0,0)')
                .nodeThreeObject(node => {
                    const group = new THREE.Group();
                    const sphere = new THREE.Mesh(
                        new THREE.SphereGeometry(8),
                        new THREE.MeshLambertMaterial({ color: node.color })
                    );
                    group.add(sphere);

                    const sprite = new SpriteText(node.label);
                    sprite.color = '#FFFFFF';
                    sprite.textHeight = 4;
                    sprite.position.y = -12;
                    sprite.backgroundColor = 'rgba(0,0,0,0.5)';
                    sprite.padding = 2;
                    sprite.borderRadius = 3;
                    group.add(sprite);

                    return group;
                })
                .cameraPosition({ x: 0, y: 0, z: 120 });
        }

        boot();
    </script>
    @endscript
</x-ui-page>
