<div class="h-full overflow-y-auto p-6">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                @svg('heroicon-o-building-office', 'w-6 h-6 text-gray-700')
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Organization Dashboard</h1>
                    <p class="text-gray-600 mt-1">Übersicht über alle Organisationseinheiten</p>
                </div>
            </div>
        </div>

        {{-- Kennzahlen --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <x-ui-dashboard-tile
                title="Alle Entitäten"
                :count="$this->totalEntities"
                icon="rectangle-stack"
                variant="primary"
                size="lg"
            />
            <x-ui-dashboard-tile
                title="Aktive Entitäten"
                :count="$this->activeEntities"
                icon="check-circle"
                variant="success"
                size="lg"
            />
            <x-ui-dashboard-tile
                title="Root-Entitäten"
                :count="$this->rootEntities"
                icon="arrow-up-right"
                variant="secondary"
                size="lg"
            />
            <x-ui-dashboard-tile
                title="Leaf-Entitäten"
                :count="$this->leafEntities"
                icon="arrow-down-right"
                variant="warning"
                size="lg"
            />
        </div>

        {{-- Verteilung nach Typen + Neueste Entitäten --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Left: Verteilung nach Typen --}}
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="p-6 border-b border-gray-200 d-flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Verteilung nach Entitätstyp</h2>
                </div>
                <div class="p-6">
                    @php($byType = $this->entitiesByType)
                    @if($byType && $byType->count() > 0)
                        <div class="space-y-3">
                            @foreach($byType as $row)
                                <div class="d-flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="d-flex items-center gap-3">
                                        <div class="w-8 h-8 bg-gray-100 rounded-lg d-flex items-center justify-center">
                                            @svg('heroicon-o-rectangle-stack', 'w-4 h-4 text-gray-600')
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $row->name }}</div>
                                            <div class="text-xs text-gray-600">Typ-ID: {{ $row->id }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-gray-900">{{ $row->count }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                @svg('heroicon-o-rectangle-stack', 'w-8 h-8 text-gray-400')
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Keine Entitäten</h3>
                            <p class="text-gray-500">Es wurden noch keine Entitäten angelegt.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right: Neueste Entitäten --}}
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="p-6 border-b border-gray-200 d-flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Neueste Entitäten</h2>
                </div>
                <div class="p-6">
                    @php($recent = $this->recentEntities)
                    @if($recent && $recent->count() > 0)
                        <div class="space-y-3">
                            @foreach($recent as $entity)
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full {{ $entity->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $entity->name }}</div>
                                            <div class="text-xs text-gray-600">
                                                {{ $entity->type?->name ?? 'Typ' }}
                                                @if($entity->vsmSystem)
                                                    • {{ $entity->vsmSystem->name }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-ui-badge variant="secondary" size="sm">{{ $entity->created_at?->format('d.m.Y') }}</x-ui-badge>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                @svg('heroicon-o-rectangle-stack', 'w-8 h-8 text-gray-400')
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Noch keine Entitäten</h3>
                            <p class="text-gray-500">Lege die erste Entität an, um loszulegen.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>