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

            loadScript('https://unpkg.com/3d-force-graph@1').then(function() {
                var container = document.getElementById('3d-graph');
                if (!container || typeof ForceGraph3D === 'undefined') return;

                var data = JSON.parse(document.getElementById('mindmap-data').textContent);

                ForceGraph3D()(container)
                    .graphData(data)
                    .backgroundColor('#ffffff')
                    .nodeLabel('name')
                    .nodeColor('color')
                    .nodeVal('val')
                    .linkColor(function() { return 'rgba(156,163,175,0.4)'; })
                    .linkWidth(1)
                    .cameraPosition({ x: 0, y: 0, z: 300 });
            });
        })();
    </script>
</x-ui-page>
