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

    <script>
        (function() {
            function loadScript(src) {
                return new Promise(function(resolve, reject) {
                    if (document.querySelector('script[src="' + src + '"]')) {
                        resolve();
                        return;
                    }
                    var s = document.createElement('script');
                    s.src = src;
                    s.onload = resolve;
                    s.onerror = reject;
                    document.head.appendChild(s);
                });
            }

            loadScript('https://unpkg.com/3d-force-graph@1.77.7/dist/3d-force-graph.min.js').then(function() {
                var container = document.getElementById('3d-graph');
                if (!container || typeof ForceGraph3D === 'undefined') return;

                ForceGraph3D()(container)
                    .graphData({
                        nodes: [{ id: 'center', name: '{{ $entity->name }}' }],
                        links: [],
                    })
                    .backgroundColor('#0f172a')
                    .nodeLabel('name')
                    .nodeColor(function() { return '#3B82F6'; })
                    .nodeRelSize(8)
                    .cameraPosition({ x: 0, y: 0, z: 120 });
            });
        })();
    </script>
</x-ui-page>
