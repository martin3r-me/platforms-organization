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
        ]">
            <x-ui-button variant="ghost" size="sm" id="btn-fullscreen">
                @svg('heroicon-o-arrows-pointing-out', 'w-4 h-4')
                <span>Fullscreen</span>
            </x-ui-button>
            <x-ui-button variant="ghost" size="sm" id="btn-reset-camera">
                @svg('heroicon-o-viewfinder-circle', 'w-4 h-4')
                <span>Reset</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <div class="relative w-full" style="height: calc(100vh - 100px);" id="mindmap-wrapper"
         x-data="{
            graph: null,
            graphData: @js($this->graphData),
            selectedNode: null,
            centerNodeId: 'entity-{{ $entity->id }}',
            depth: 3,
            filters: { entity: true, linked: true, relation: true },
            scriptsLoaded: false,

            loadScripts() {
                const scripts = [
                    'https://unpkg.com/three@0.160.0/build/three.min.js',
                    'https://unpkg.com/three-spritetext@1',
                    'https://unpkg.com/3d-force-graph@1',
                ];
                let loaded = 0;
                const total = scripts.length;
                const loadNext = () => {
                    if (loaded >= total) {
                        this.scriptsLoaded = true;
                        this.buildGraph();
                        return;
                    }
                    const s = document.createElement('script');
                    s.src = scripts[loaded];
                    s.onload = () => { loaded++; loadNext(); };
                    document.head.appendChild(s);
                };
                loadNext();
            },

            init() {
                this.loadScripts();

                document.getElementById('btn-fullscreen')?.addEventListener('click', () => {
                    const el = document.getElementById('mindmap-wrapper');
                    if (el.requestFullscreen) el.requestFullscreen();
                });

                document.getElementById('btn-reset-camera')?.addEventListener('click', () => {
                    this.resetCamera();
                });
            },

            buildGraph() {
                const container = document.getElementById('3d-graph');
                if (!container || typeof ForceGraph3D === 'undefined') return;

                const filtered = this.getFilteredData();

                this.graph = ForceGraph3D()(container)
                    .graphData(filtered)
                    .backgroundColor('rgba(0,0,0,0)')
                    .nodeLabel(n => `<div style=&quot;background:rgba(0,0,0,0.85);color:#fff;padding:8px 12px;border-radius:8px;font-size:12px;max-width:200px;&quot;>
                        <strong>${n.label}</strong><br/>
                        <span style=&quot;color:${n.color};font-size:10px;&quot;>${n.typeName}</span>
                        ${n.group ? `<br/><span style=&quot;opacity:0.6;font-size:10px;&quot;>${n.group}</span>` : ''}
                    </div>`)
                    .nodeThreeObject(node => {
                        const group = new THREE.Group();

                        const geometry = new THREE.SphereGeometry(node.size || 5);
                        const material = new THREE.MeshLambertMaterial({
                            color: node.color,
                            transparent: !node.isCenter,
                            opacity: node.isCenter ? 1.0 : 0.85,
                        });
                        const sphere = new THREE.Mesh(geometry, material);

                        if (node.isCenter) {
                            const glowGeo = new THREE.SphereGeometry((node.size || 5) * 1.6);
                            const glowMat = new THREE.MeshBasicMaterial({
                                color: node.color,
                                transparent: true,
                                opacity: 0.15,
                            });
                            group.add(new THREE.Mesh(glowGeo, glowMat));
                        }

                        group.add(sphere);

                        const sprite = new SpriteText(node.label);
                        sprite.color = node.isCenter ? '#FFFFFF' : 'rgba(255,255,255,0.8)';
                        sprite.textHeight = node.isCenter ? 4 : 2.5;
                        sprite.position.y = -(node.size || 5) - 3;
                        sprite.backgroundColor = 'rgba(0,0,0,0.4)';
                        sprite.padding = 1.5;
                        sprite.borderRadius = 3;
                        group.add(sprite);

                        return group;
                    })
                    .linkColor(l => l.color)
                    .linkWidth(l => l.width || 1)
                    .linkOpacity(0.6)
                    .linkDirectionalParticles(l => l.type === 'relation' ? 2 : 0)
                    .linkDirectionalParticleWidth(1.5)
                    .linkDirectionalParticleSpeed(0.005)
                    .linkDirectionalParticleColor(l => l.color)
                    .onNodeClick((node) => {
                        this.selectedNode = { ...node };
                        this.animateCameraToNode(node);
                    })
                    .onBackgroundClick(() => {
                        this.selectedNode = null;
                    })
                    .warmupTicks(80)
                    .cooldownTicks(120)
                    .d3AlphaDecay(0.02)
                    .d3VelocityDecay(0.3);

                setTimeout(() => this.resetCamera(), 500);
            },

            getFilteredData() {
                let nodes = [...this.graphData.nodes];
                let links = [...this.graphData.links];

                if (!this.filters.linked) {
                    const linkedIds = new Set(nodes.filter(n => n.type === 'linked').map(n => n.id));
                    nodes = nodes.filter(n => n.type !== 'linked');
                    links = links.filter(l => !linkedIds.has(l.source?.id || l.source) && !linkedIds.has(l.target?.id || l.target));
                }

                if (!this.filters.relation) {
                    links = links.filter(l => l.type !== 'relation');
                }

                if (!this.filters.entity) {
                    const entityIds = new Set(nodes.filter(n => n.type === 'entity' && !n.isCenter).map(n => n.id));
                    nodes = nodes.filter(n => n.type !== 'entity' || n.isCenter);
                    links = links.filter(l => !entityIds.has(l.source?.id || l.source) && !entityIds.has(l.target?.id || l.target));
                }

                return { nodes, links };
            },

            toggleFilter(key) {
                this.filters[key] = !this.filters[key];
                if (this.graph) {
                    this.graph.graphData(this.getFilteredData());
                }
            },

            setDepth(d) {
                this.depth = d;
                if (this.graph) {
                    this.graph.graphData(this.getFilteredData());
                }
            },

            animateCameraToNode(node) {
                if (!this.graph) return;
                const distance = 120;
                const distRatio = 1 + distance / Math.hypot(node.x, node.y, node.z);
                this.graph.cameraPosition(
                    { x: node.x * distRatio, y: node.y * distRatio, z: node.z * distRatio },
                    { x: node.x, y: node.y, z: node.z },
                    1500
                );
            },

            focusNode(node) {
                if (node) this.animateCameraToNode(node);
            },

            resetCamera() {
                if (!this.graph) return;
                const centerNode = this.graphData.nodes.find(n => n.id === this.centerNodeId);
                if (centerNode && centerNode.x !== undefined) {
                    this.animateCameraToNode(centerNode);
                } else {
                    this.graph.cameraPosition({ x: 0, y: 0, z: 400 }, { x: 0, y: 0, z: 0 }, 1500);
                }
            },
         }">

        {{-- Toolbar --}}
        <div class="absolute top-4 left-4 z-20 flex items-center gap-2">
            {{-- Depth Control --}}
            <div class="bg-[var(--ui-bg)] border border-[var(--ui-border)] rounded-lg shadow-lg px-3 py-2 flex items-center gap-2">
                <span class="text-xs text-[var(--ui-secondary)] font-medium">Tiefe</span>
                <template x-for="d in [1, 2, 3]" :key="d">
                    <button
                        @click="setDepth(d)"
                        :class="depth === d
                            ? 'bg-[var(--ui-primary)] text-white'
                            : 'bg-[var(--ui-bg-muted)] text-[var(--ui-secondary)] hover:bg-[var(--ui-bg-hover)]'"
                        class="w-7 h-7 rounded text-xs font-bold transition-colors"
                        x-text="d"
                    ></button>
                </template>
            </div>

            {{-- Filter Toggles --}}
            <div class="bg-[var(--ui-bg)] border border-[var(--ui-border)] rounded-lg shadow-lg px-3 py-2 flex items-center gap-2">
                <button @click="toggleFilter('entity')"
                        :class="filters.entity ? 'bg-blue-500/20 text-blue-400 ring-1 ring-blue-500/30' : 'bg-[var(--ui-bg-muted)] text-[var(--ui-secondary)]'"
                        class="px-2 py-1 rounded text-xs font-medium transition-colors">
                    Entities
                </button>
                <button @click="toggleFilter('linked')"
                        :class="filters.linked ? 'bg-green-500/20 text-green-400 ring-1 ring-green-500/30' : 'bg-[var(--ui-bg-muted)] text-[var(--ui-secondary)]'"
                        class="px-2 py-1 rounded text-xs font-medium transition-colors">
                    Links
                </button>
                <button @click="toggleFilter('relation')"
                        :class="filters.relation ? 'bg-orange-500/20 text-orange-400 ring-1 ring-orange-500/30' : 'bg-[var(--ui-bg-muted)] text-[var(--ui-secondary)]'"
                        class="px-2 py-1 rounded text-xs font-medium transition-colors">
                    Relations
                </button>
            </div>
        </div>

        {{-- Info Panel (selected node) --}}
        <div x-show="selectedNode" x-transition
             class="absolute bottom-4 left-4 z-20 bg-[var(--ui-bg)] border border-[var(--ui-border)] rounded-lg shadow-xl p-4 w-72">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-4 h-4 rounded-full" :style="'background:' + (selectedNode?.color || '#999')"></div>
                <h3 class="font-bold text-sm text-[var(--ui-primary-text)]" x-text="selectedNode?.label"></h3>
            </div>
            <div class="space-y-1 text-xs text-[var(--ui-secondary)]">
                <div><span class="font-medium">Typ:</span> <span x-text="selectedNode?.typeName"></span></div>
                <div><span class="font-medium">Gruppe:</span> <span x-text="selectedNode?.group"></span></div>
                <div x-show="selectedNode?.code"><span class="font-medium">Code:</span> <span x-text="selectedNode?.code"></span></div>
            </div>
            <div class="mt-3 flex gap-2" x-show="selectedNode?.entityId">
                <a :href="'/organization/entities/' + selectedNode?.entityId"
                   class="text-xs px-2 py-1 bg-[var(--ui-primary)] text-white rounded hover:opacity-80 transition-opacity">
                    Details
                </a>
                <button @click="focusNode(selectedNode)"
                        class="text-xs px-2 py-1 bg-[var(--ui-bg-muted)] text-[var(--ui-secondary)] rounded hover:bg-[var(--ui-bg-hover)] transition-colors">
                    Fokussieren
                </button>
            </div>
        </div>

        {{-- Legend --}}
        <div class="absolute bottom-4 right-4 z-20 bg-[var(--ui-bg)] border border-[var(--ui-border)] rounded-lg shadow-lg p-3">
            <h4 class="text-xs font-bold text-[var(--ui-secondary)] mb-2 uppercase tracking-wider">Legende</h4>
            <div class="space-y-1">
                @foreach([
                    ['Organisationseinheiten', '#3B82F6'],
                    ['Personen', '#8B5CF6'],
                    ['Externe', '#EF4444'],
                    ['Ventures', '#F97316'],
                    ['Technische Systeme', '#6366F1'],
                    ['Projekte', '#10B981'],
                    ['Canvas', '#EC4899'],
                ] as [$label, $color])
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full" style="background: {{ $color }}"></div>
                        <span class="text-xs text-[var(--ui-secondary)]">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- 3D Graph Container --}}
        <div id="3d-graph" class="w-full h-full rounded-lg overflow-hidden"></div>
    </div>
</x-ui-page>
