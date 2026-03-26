<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Einheiten', 'href' => route('organization.entities.index')],
            ['label' => $entity->name ?? 'Details'],
        ]">
            @if($this->isDirty())
                <x-ui-button variant="secondary-ghost" size="sm" wire:click="loadForm">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Abbrechen</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Speichern</span>
                </x-ui-button>
            @else
                <x-ui-button variant="ghost" size="sm" wire:click="edit">
                    @svg('heroicon-o-pencil', 'w-4 h-4')
                    <span>Bearbeiten</span>
                </x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="openCreateTeamModal">
                    @svg('heroicon-o-user-group', 'w-4 h-4')
                    <span>Team erstellen</span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-3">
                        @if($entity->is_active)
                            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                        @else
                            <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        @if($entity->code)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Code</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->code }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Typ</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->type->name }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">{{ $entity->type->group->name }}</div>
                        </div>
                        @if($entity->vsmSystem)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">VSM System</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->vsmSystem->name }}</div>
                            </div>
                        @endif
                        @if($entity->costCenter)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Kostenstelle</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->costCenter->name }}</div>
                            </div>
                        @endif
                        @if($entity->parent)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Übergeordnet</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->parent->name }}</div>
                                <div class="text-xs text-[var(--ui-muted)]">{{ $entity->parent->type->name }}</div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Aktualisiert</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->updated_at->format('d.m.Y H:i') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 text-sm text-[var(--ui-muted)]">Keine Aktivitäten verfügbar</div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                {{ session('message') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                {{ session('error') }}
            </div>
        @endif

        <div class="space-y-6">
            {{-- Hero Card --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $entity->name }}</h1>
                        <div class="flex items-center gap-3 mt-2">
                            <x-ui-badge variant="secondary" size="sm">{{ $entity->type->name }}</x-ui-badge>
                            @if($entity->is_active)
                                <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                            @else
                                <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
                            @endif
                            @if($entity->code)
                                <span class="text-sm text-[var(--ui-muted)]">{{ $entity->code }}</span>
                            @endif
                        </div>
                        @if($entity->description)
                            <p class="mt-3 text-sm text-[var(--ui-muted)] max-w-2xl">{{ $entity->description }}</p>
                        @endif
                    </div>
                </div>

                {{-- Stats Grid --}}
                @php
                    $childCount = $entity->children->count();
                    $descendantCount = $this->totalDescendantCount;
                    $linkCount = $this->totalLinkCount;
                    $cascaded = $this->cascadedTimeSummary;
                    $totalHours = intdiv($cascaded['total_minutes'], 60);
                    $totalMins = $cascaded['total_minutes'] % 60;
                    $openMinutes = $cascaded['total_minutes'] - $cascaded['billed_minutes'];
                    $openHours = intdiv($openMinutes, 60);
                    $openMins = abs($openMinutes % 60);
                @endphp
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $childCount }}</div>
                        <div class="text-xs text-[var(--ui-muted)] mt-1">
                            Einheiten
                            @if($descendantCount > $childCount)
                                <span class="text-[var(--ui-muted)]">({{ $descendantCount }} gesamt)</span>
                            @endif
                        </div>
                    </div>
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $linkCount }}</div>
                        <div class="text-xs text-[var(--ui-muted)] mt-1">Verknüpfungen gesamt</div>
                    </div>
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                        <div class="text-2xl font-bold text-[var(--ui-secondary)]">{{ $totalHours }}:{{ str_pad($totalMins, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="text-xs text-[var(--ui-muted)] mt-1">Gesamt-Stunden</div>
                    </div>
                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                        <div class="text-2xl font-bold {{ $openMinutes > 0 ? 'text-amber-600' : 'text-green-600' }}">{{ $openHours }}:{{ str_pad($openMins, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="text-xs text-[var(--ui-muted)] mt-1">Offene Stunden</div>
                    </div>
                </div>

                {{-- Monthly Time Chart --}}
                @php
                    $monthlyData = $this->monthlyTimeData;
                    $chartMonths = $monthlyData['months'] ?? [];
                    $maxMin = $monthlyData['max_minutes'] ?? 0;
                @endphp
                @if($maxMin > 0)
                    <div class="mt-6 pt-6 border-t border-[var(--ui-border)]/40"
                         x-data="{ tooltip: null }">
                        {{-- Header with legend --}}
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-medium text-[var(--ui-muted)]">Zeitverlauf (12 Monate)</span>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                    <span class="text-[10px] text-[var(--ui-muted)]">abgerechnet</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                    <span class="text-[10px] text-[var(--ui-muted)]">offen</span>
                                </div>
                            </div>
                        </div>

                        {{-- Bars --}}
                        <div class="flex items-end gap-1.5" style="height: 136px;">
                            @foreach($chartMonths as $idx => $m)
                                <div class="flex-1 flex flex-col items-center h-full relative"
                                     @mouseenter="tooltip = {{ $idx }}"
                                     @mouseleave="tooltip = null">
                                    {{-- Tooltip --}}
                                    <div x-show="tooltip === {{ $idx }}" x-cloak
                                         class="absolute bottom-full mb-2 px-2.5 py-1.5 rounded-lg bg-[var(--ui-secondary)] text-white text-[10px] whitespace-nowrap z-10 shadow-lg pointer-events-none"
                                         x-transition.opacity>
                                        {{ $m['label'] }} {{ $m['year'] }}:
                                        {{ intdiv($m['total_minutes'], 60) }}:{{ str_pad($m['total_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h
                                        ({{ intdiv($m['billed_minutes'], 60) }}:{{ str_pad($m['billed_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h abgerechnet,
                                        {{ intdiv($m['open_minutes'], 60) }}:{{ str_pad($m['open_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h offen)
                                    </div>

                                    {{-- Bar stack --}}
                                    <div class="w-full flex flex-col justify-end flex-1 rounded-t overflow-hidden">
                                        @if($m['total_minutes'] > 0)
                                            @php
                                                $barHeight = round(($m['total_minutes'] / $maxMin) * 120);
                                                $billedHeight = $m['billed_minutes'] > 0 ? max(2, round(($m['billed_minutes'] / $maxMin) * 120)) : 0;
                                                $openHeight = $m['open_minutes'] > 0 ? max(2, $barHeight - $billedHeight) : 0;
                                                if ($billedHeight + $openHeight > $barHeight && $barHeight > 4) {
                                                    // Adjust if min-heights pushed total over
                                                    $billedHeight = $barHeight - $openHeight;
                                                }
                                            @endphp
                                            <div class="w-full flex flex-col justify-end mt-auto">
                                                @if($m['open_minutes'] > 0)
                                                    <div class="w-full bg-amber-400 rounded-t" style="height: {{ $openHeight }}px;"></div>
                                                @endif
                                                @if($m['billed_minutes'] > 0)
                                                    <div class="w-full bg-green-500 {{ $m['open_minutes'] <= 0 ? 'rounded-t' : '' }}" style="height: {{ $billedHeight }}px;"></div>
                                                @endif
                                            </div>
                                        @else
                                            {{-- Empty month: thin track --}}
                                            <div class="w-full mt-auto">
                                                <div class="w-full bg-[var(--ui-border)]/20 rounded-t" style="height: 1px;"></div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Month label --}}
                                    <div class="text-[10px] text-[var(--ui-muted)] mt-1 leading-none">{{ $m['label'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Tab Navigation --}}
            <div x-data="{ tab: @entangle('activeTab') }">
                <div class="border-b border-[var(--ui-border)] mb-6">
                    <nav class="flex gap-1 -mb-px">
                        <button
                            @click="tab = 'hierarchy'"
                            :class="tab === 'hierarchy'
                                ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                            class="px-4 py-2.5 text-sm transition-colors"
                        >
                            @svg('heroicon-o-rectangle-group', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                            Hierarchie
                        </button>
                        <button
                            @click="tab = 'data'"
                            :class="tab === 'data'
                                ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                            class="px-4 py-2.5 text-sm transition-colors"
                        >
                            @svg('heroicon-o-document-text', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                            Daten
                        </button>
                        <button
                            @click="tab = 'relations'"
                            :class="tab === 'relations'
                                ? 'border-b-2 border-[var(--ui-primary)] text-[var(--ui-primary)] font-semibold'
                                : 'border-b-2 border-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:border-[var(--ui-border)]'"
                            class="px-4 py-2.5 text-sm transition-colors"
                        >
                            @svg('heroicon-o-link', 'w-4 h-4 inline-block mr-1.5 -mt-0.5')
                            Relations
                        </button>
                    </nav>
                </div>

                {{-- Tab: Hierarchie --}}
                <div x-show="tab === 'hierarchy'" x-cloak x-data="{ linkConfig: {{ Js::from(collect($this->linkTypeConfig)->map(fn($c) => ['label' => $c['label'], 'icon' => $c['icon']])) }} }">
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                        @if(count($this->rootEntityLinks) > 0 || count($this->treeNodes) > 0)
                            <div class="space-y-1">
                                {{-- Root Entity Links (leaf nodes) --}}
                                @foreach($this->rootEntityLinks as $link)
                                    <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 flex-shrink-0"></div>
                                            @svg('heroicon-o-' . $link['icon'], 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                                            @if($link['url'])
                                                <a href="{{ $link['url'] }}" class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate">
                                                    {{ $link['name'] }}
                                                </a>
                                            @else
                                                <span class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $link['name'] }}</span>
                                            @endif
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0">
                                                {{ $link['label'] }}
                                            </span>
                                            @if($link['status'])
                                                <x-ui-badge variant="secondary" size="xs">{{ $link['status'] }}</x-ui-badge>
                                            @endif
                                            @if($link['url'])
                                                <div class="ml-auto flex-shrink-0">
                                                    @svg('heroicon-o-arrow-top-right-on-square', 'w-3.5 h-3.5 text-[var(--ui-muted)]')
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Child Entities (expandable tree nodes) --}}
                                @foreach($this->treeNodes as $node)
                                    @include('organization::livewire.entity.partials.tree-node', ['node' => $node, 'depth' => 0])
                                @endforeach
                            </div>
                        @else
                            <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                                    @svg('heroicon-o-rectangle-group', 'w-8 h-8 text-[var(--ui-muted)]')
                                </div>
                                <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine Verknüpfungen oder Untereinheiten</p>
                                <p class="text-xs text-[var(--ui-muted)]">Diese Einheit hat weder Verknüpfungen noch Kinder-Entities</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Tab: Daten --}}
                <div x-show="tab === 'data'" x-cloak>
                    <div class="space-y-6">
                        {{-- Grunddaten --}}
                        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                            <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Grunddaten</h2>
                            <div class="space-y-4">
                                <x-ui-input-text name="name" label="Name" wire:model.live="form.name" required />
                                <x-ui-input-text name="code" label="Code" wire:model.live="form.code" placeholder="Optional: Code oder Nummer" />
                                <x-ui-input-textarea name="description" label="Beschreibung" wire:model.live="form.description" />
                                <x-ui-input-select
                                    name="entity_type_id"
                                    label="Typ"
                                    :options="$this->entityTypes->flatten()"
                                    optionValue="id"
                                    optionLabel="name"
                                    :nullable="false"
                                    wire:model.live="form.entity_type_id"
                                    required
                                />
                                <x-ui-input-select
                                    name="vsm_system_id"
                                    label="VSM System (optional)"
                                    :options="$this->vsmSystems"
                                    optionValue="id"
                                    optionLabel="name"
                                    :nullable="true"
                                    nullLabel="Kein VSM System"
                                    wire:model.live="form.vsm_system_id"
                                />
                                <x-ui-input-select
                                    name="cost_center_id"
                                    label="Kostenstelle (optional)"
                                    :options="$this->costCenters"
                                    optionValue="id"
                                    optionLabel="name"
                                    :nullable="true"
                                    nullLabel="Keine Kostenstelle"
                                    wire:model.live="form.cost_center_id"
                                />
                                <x-ui-input-select
                                    name="parent_entity_id"
                                    label="Übergeordnete Einheit (optional)"
                                    :options="$this->parentEntities"
                                    optionValue="id"
                                    optionLabel="name"
                                    :nullable="true"
                                    nullLabel="Keine übergeordnete Einheit"
                                    wire:model.live="form.parent_entity_id"
                                />
                                <div class="flex items-center">
                                    <input type="checkbox" wire:model.live="form.is_active" id="is_active" class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50" />
                                    <label for="is_active" class="ml-2 text-sm text-[var(--ui-secondary)]">Aktiv</label>
                                </div>
                            </div>
                        </div>

                        {{-- Dimensionen --}}
                        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                            <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Dimensionen</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Kostenstellen</h3>
                                    <livewire:organization.dimension-linker
                                        dimension="cost-centers"
                                        :contextType="$entity::class"
                                        :contextId="$entity->id"
                                        :key="'dim-cost-centers-'.$entity->id"
                                    />
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">VSM Funktionen</h3>
                                    <div class="space-y-2">
                                        @if($this->availableVsmFunctions->count() > 0)
                                            @foreach($this->availableVsmFunctions as $vsmFunction)
                                                <div class="flex items-center justify-between py-2 px-3 bg-[var(--ui-muted-5)] rounded">
                                                    <div>
                                                        <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $vsmFunction->name }}</div>
                                                        <div class="text-xs text-[var(--ui-muted)]">{{ $vsmFunction->code }}</div>
                                                    </div>
                                                    @if($vsmFunction->isGlobal())
                                                        <x-ui-badge variant="secondary" size="sm">Global</x-ui-badge>
                                                    @else
                                                        <x-ui-badge variant="info" size="sm">Entitätsspezifisch</x-ui-badge>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @else
                                            <div class="text-sm text-[var(--ui-muted)] py-2">Keine VSM Funktionen verfügbar</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tab: Relations --}}
                <div x-show="tab === 'relations'" x-cloak>
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-semibold text-[var(--ui-secondary)]">Relations</h2>
                            <x-ui-button
                                variant="primary-outline"
                                size="sm"
                                wire:click="$dispatch('open-relations-modal', { entityId: {{ $entity->id }} })"
                            >
                                @svg('heroicon-o-plus', 'w-4 h-4 mr-2')
                                Relation hinzufügen
                            </x-ui-button>
                        </div>
                        <div class="space-y-4">
                            @php
                                $relationsFrom = $entity->relationsFrom->whereNull('deleted_at');
                                $relationsTo = $entity->relationsTo->whereNull('deleted_at');
                            @endphp

                            @if($relationsFrom->count() > 0 || $relationsTo->count() > 0)
                                @if($relationsFrom->count() > 0)
                                    <div>
                                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-2 flex items-center gap-2">
                                            @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                            Von dieser Entity ({{ $relationsFrom->count() }})
                                        </h3>
                                        <div class="space-y-2">
                                            @foreach($relationsFrom as $relation)
                                                <div class="flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->name }}</span>
                                                        <span class="text-sm text-[var(--ui-muted)]">{{ $relation->relationType->name }}</span>
                                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $relation->toEntity->name }}</span>
                                                        <x-ui-badge variant="secondary" size="xs">{{ $relation->toEntity->type->name }}</x-ui-badge>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if($relationsTo->count() > 0)
                                    <div>
                                        <h3 class="text-sm font-semibold text-[var(--ui-secondary)] mb-2 flex items-center gap-2">
                                            @svg('heroicon-o-arrow-left', 'w-4 h-4')
                                            Zu dieser Entity ({{ $relationsTo->count() }})
                                        </h3>
                                        <div class="space-y-2">
                                            @foreach($relationsTo as $relation)
                                                <div class="flex items-center justify-between p-3 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $relation->fromEntity->name }}</span>
                                                        <span class="text-sm text-[var(--ui-muted)]">{{ $relation->relationType->name }}</span>
                                                        <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $entity->name }}</span>
                                                        <x-ui-badge variant="secondary" size="xs">{{ $relation->fromEntity->type->name }}</x-ui-badge>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                                        @svg('heroicon-o-link', 'w-8 h-8 text-[var(--ui-muted)]')
                                    </div>
                                    <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine Relations vorhanden</p>
                                    <p class="text-xs text-[var(--ui-muted)]">Erstellen Sie eine Relation zu einer anderen Entity</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-ui-page-container>

    <!-- Relations Modal -->
    <livewire:organization.entity.modal-relations/>

    <!-- Create Team Modal -->
    <x-ui-modal
        wire:model="showCreateTeamModal"
        size="md"
    >
        <x-slot name="header">
            Team aus Entität erstellen
        </x-slot>

        <div class="space-y-4">
            <div class="space-y-4">
                <x-ui-input-text
                    name="team_name"
                    label="Team-Name"
                    wire:model.live="newTeam.name"
                    required
                    placeholder="Name des Teams"
                />

                <x-ui-input-select
                    name="parent_team_id"
                    label="Eltern-Team (optional)"
                    :options="$this->availableTeams"
                    optionValue="id"
                    optionLabel="name"
                    :nullable="true"
                    nullLabel="Kein Eltern-Team"
                    wire:model.live="newTeam.parent_team_id"
                />
            </div>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button
                    type="button"
                    variant="secondary-outline"
                    wire:click="closeCreateTeamModal"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="createTeam">
                    @svg('heroicon-o-user-group', 'w-4 h-4 mr-2')
                    Team erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
