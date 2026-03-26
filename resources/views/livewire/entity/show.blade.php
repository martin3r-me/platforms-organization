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
                <div x-show="tab === 'hierarchy'" x-cloak x-data="{
                    linkConfig: {{ Js::from(collect($this->linkTypeConfig)->map(fn($c) => ['label' => $c['label'], 'icon' => $c['icon']])) }},
                    linkIconSvgs: {{ Js::from($this->linkTypeIconSvgs) }}
                }">
                    <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                        @if(count($this->rootEntityLinks) > 0 || count($this->treeNodes) > 0)
                            {{-- Expand/Collapse All Button --}}
                            <div class="flex justify-end mb-4">
                                <button
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-[var(--ui-border)] text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors"
                                    x-data
                                    @click="$store.tree.allExpanded ? $store.tree.collapseAll() : $store.tree.expandAll($wire)"
                                    :disabled="$store.tree.loading"
                                >
                                    <template x-if="$store.tree.loading">
                                        <svg class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </template>
                                    <template x-if="!$store.tree.loading">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                        </svg>
                                    </template>
                                    <span x-text="$store.tree.allExpanded ? 'Alle einklappen' : 'Alle aufklappen'"></span>
                                </button>
                            </div>
                            @php
                                $rootCascaded = $this->cascadedTimeSummary;
                                $rootTotalMin = $rootCascaded['total_minutes'];
                                $rootBilledMin = $rootCascaded['billed_minutes'];
                                $rootOpenMin = $rootTotalMin - $rootBilledMin;
                                $rootHours = intdiv($rootTotalMin, 60);
                                $rootMins = $rootTotalMin % 60;
                                $rootOpenH = intdiv(abs($rootOpenMin), 60);
                                $rootOpenM = abs($rootOpenMin) % 60;
                                $rootTypeIcon = null;
                                if ($entity->type->icon) {
                                    $icon = str_replace('heroicons.', '', $entity->type->icon);
                                    $iconMap = ['user-check' => 'user', 'folder-kanban' => 'folder', 'briefcase-globe' => 'briefcase', 'server-cog' => 'server', 'package-check' => 'archive-box', 'badge-check' => 'check-badge'];
                                    $rootTypeIcon = $iconMap[$icon] ?? $icon;
                                }
                            @endphp

                            <div x-data="{ rootExpanded: true }" class="space-y-1">
                                {{-- Root Entity Node --}}
                                <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">
                                    <div class="flex items-center gap-2 cursor-pointer" @click="rootExpanded = !rootExpanded">
                                        {{-- Chevron --}}
                                        <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                                                :class="{ 'rotate-90': rootExpanded }">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                            </svg>
                                        </div>

                                        {{-- Type Icon --}}
                                        @if($rootTypeIcon)
                                            @svg('heroicon-o-' . $rootTypeIcon, 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                                        @endif

                                        {{-- Name --}}
                                        <span class="text-sm font-semibold text-[var(--ui-secondary)] truncate">{{ $entity->name }}</span>

                                        {{-- Code --}}
                                        @if($entity->code)
                                            <span class="text-xs text-[var(--ui-muted)] font-mono flex-shrink-0">{{ $entity->code }}</span>
                                        @endif

                                        {{-- Type Badge --}}
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0">
                                            {{ $entity->type->name }}
                                        </span>

                                        {{-- Time Summary --}}
                                        @if($rootTotalMin > 0)
                                            <div class="flex items-center gap-2 ml-auto flex-shrink-0">
                                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--ui-secondary)]">
                                                    @if($rootOpenMin > 0)
                                                        <span class="w-2 h-2 rounded-full flex-shrink-0 bg-amber-400"></span>
                                                    @else
                                                        <span class="w-2 h-2 rounded-full flex-shrink-0 bg-green-500"></span>
                                                    @endif
                                                    {{ $rootHours }}:{{ str_pad($rootMins, 2, '0', STR_PAD_LEFT) }}h
                                                </span>
                                                @if($rootOpenMin > 0)
                                                    <span class="text-[10px] text-amber-600 font-medium">
                                                        {{ $rootOpenH }}:{{ str_pad($rootOpenM, 2, '0', STR_PAD_LEFT) }}h offen
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Root Expanded Content --}}
                                <div x-show="rootExpanded" x-collapse>
                                    {{-- Root Typ-Gruppen (grouped links) --}}
                                    @foreach($this->rootEntityLinks as $group)
                                        <div x-data="{ groupOpen: false, init() { this.$watch('$store.tree.allExpanded', v => this.groupOpen = v); } }" class="ml-6 border-l-2 border-[var(--ui-border)]/20">
                                            {{-- Group Header --}}
                                            <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3 cursor-pointer"
                                                @click="groupOpen = !groupOpen">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                                            class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                                                            :class="{ 'rotate-90': groupOpen }">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                        </svg>
                                                    </div>
                                                    @svg('heroicon-o-' . $group['icon'], 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
                                                    <span class="text-sm font-medium text-[var(--ui-secondary)]">{{ $group['label'] }}</span>
                                                    <span class="text-xs text-[var(--ui-muted)]">({{ count($group['items']) }})</span>
                                                    @if(($group['group_logged_minutes'] ?? 0) > 0)
                                                        <span class="text-xs text-[var(--ui-muted)] ml-auto flex-shrink-0">
                                                            {{ intdiv($group['group_logged_minutes'], 60) }}:{{ str_pad($group['group_logged_minutes'] % 60, 2, '0', STR_PAD_LEFT) }}h
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Group Items (Link Leaves) --}}
                                            <div x-show="groupOpen" x-collapse x-cloak>
                                                @foreach($group['items'] as $link)
                                                    @include('organization::livewire.entity.partials.link-item', ['link' => $link, 'group' => $group])
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach

                                    {{-- Child Entities (expandable tree nodes) --}}
                                    @foreach($this->treeNodes as $node)
                                        @include('organization::livewire.entity.partials.tree-node', ['node' => $node, 'depth' => 1])
                                    @endforeach
                                </div>
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

    {{-- Alpine treeNode component for recursive tree rendering --}}
    <script>
    document.addEventListener('alpine:init', () => {
        // Store linkConfig and linkIconSvgs globally for tree nodes
        Alpine.store('treeConfig', {
            linkConfig: @js(collect($this->linkTypeConfig)->map(fn($c) => ['label' => $c['label'], 'icon' => $c['icon']])),
            linkIconSvgs: @js($this->linkTypeIconSvgs),
        });
        const escHtml = (s) => s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : '';

        const chevronSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[var(--ui-muted)]"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>`;
        const spinnerSvg = `<svg class="w-4 h-4 animate-spin text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;
        const externalSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-[var(--ui-muted)]"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>`;

        function formatTime(totalMin) {
            const h = Math.floor(totalMin / 60);
            const m = String(totalMin % 60).padStart(2, '0');
            return h + ':' + m + 'h';
        }

        function formatOpenTime(totalMin, billedMin) {
            const open = totalMin - billedMin;
            if (open <= 0) return '';
            return Math.floor(open / 60) + ':' + String(open % 60).padStart(2, '0') + 'h offen';
        }

        // Render rich metadata for a link item
        function renderLinkMeta(link, type) {
            let parts = [];
            if (type === 'project') {
                if (link.task_count > 0) parts.push(`${link.done_task_count || 0}/${link.task_count} Tasks`);
                if (link.logged_minutes > 0) parts.push(formatTime(link.logged_minutes));
                if (link.done) parts.push('<span class="text-green-600">erledigt</span>');
            } else if (type === 'planner_task') {
                if (link.priority) parts.push(escHtml(link.priority));
                if (link.logged_minutes > 0) parts.push(formatTime(link.logged_minutes));
                if (link.due_date) parts.push(escHtml(link.due_date));
                if (link.is_done) parts.push('<span class="text-green-600">erledigt</span>');
            } else if (type === 'helpdesk_ticket') {
                if (link.priority) parts.push(escHtml(link.priority));
                if (link.escalation_level) parts.push('<span class="text-red-600">' + escHtml(link.escalation_level) + '</span>');
                if (link.story_points) parts.push(escHtml(link.story_points) + ' SP');
                if (link.due_date) parts.push(escHtml(link.due_date));
                if (link.escalation_count > 0) parts.push(link.escalation_count + ' Eskalation' + (link.escalation_count > 1 ? 'en' : ''));
                if (link.is_done) parts.push('<span class="text-green-600">erledigt</span>');
            } else if (type === 'helpdesk_board') {
                if (link.ticket_count > 0) parts.push(link.ticket_count + ' Tickets');
            } else if (type === 'canvas' || type === 'bmc_canvas' || type === 'pc_canvas') {
                if (link.status) parts.push(escHtml(link.status));
                if (link.block_count > 0) parts.push(link.block_count + ' Blocks');
            } else if (type === 'okr') {
                if (link.objective_count > 0) parts.push(link.objective_count + ' Objectives');
                if (link.cycle_count > 0) parts.push(link.cycle_count + ' Zyklen');
                if (link.performance_score != null) parts.push(link.performance_score + '%');
            } else if (type === 'notes_note') {
                if (link.is_pinned) parts.push('angepinnt');
                if (link.is_done) parts.push('<span class="text-green-600">erledigt</span>');
            } else if (type === 'slides_presentation') {
                if (link.slide_count > 0) parts.push(link.slide_count + ' Folien');
                if (link.is_published) parts.push('<span class="text-green-600">veröffentlicht</span>');
            } else if (type === 'sheets_spreadsheet') {
                if (link.worksheet_count > 0) parts.push(link.worksheet_count + ' Blätter');
            } else if (type === 'rec_applicant') {
                if (link.applied_at) parts.push('beworben ' + escHtml(link.applied_at));
                if (link.posting_count > 0) parts.push(link.posting_count + ' Stellen');
                if (link.progress > 0) parts.push(link.progress + '% Fortschritt');
                if (link.is_active === false) parts.push('<span class="text-amber-600">inaktiv</span>');
            } else if (type === 'rec_position') {
                if (link.posting_count > 0) parts.push(link.posting_count + ' Ausschreibungen');
                if (link.is_active === false) parts.push('<span class="text-amber-600">inaktiv</span>');
            } else if (type === 'hcm_employee') {
                if (link.employee_number) parts.push('#' + escHtml(link.employee_number));
                if (link.is_active === false) parts.push('<span class="text-amber-600">inaktiv</span>');
            }
            if (parts.length === 0) return '';
            return `<span class="inline-flex items-center gap-1.5 text-[10px] text-[var(--ui-muted)] flex-shrink-0">${parts.join(' · ')}</span>`;
        }

        // Shared render function for a node card (used at all Alpine levels)
        window._treeRenderNode = function(node, linkConfig, linkIconSvgs) {
            const isExpandable = node.has_children || (node.own_links_grouped && node.own_links_grouped.length > 0);
            const totalMin = node.cascaded_time ? node.cascaded_time.total_minutes : 0;
            const billedMin = node.cascaded_time ? node.cascaded_time.billed_minutes : 0;
            const openMin = totalMin - billedMin;

            let html = `<div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3 ${!node.is_active ? 'opacity-50' : ''}">`;
            html += `<div class="flex items-center gap-2 ${isExpandable ? 'cursor-pointer' : ''}" @click="nodeToggle()">`;

            // Chevron/spinner
            html += `<div class="w-5 h-5 flex items-center justify-center flex-shrink-0">`;
            if (isExpandable) {
                html += `<template x-if="nodeLoading">${spinnerSvg}</template>`;
                html += `<template x-if="!nodeLoading"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200" :class="{ 'rotate-90': nodeExpanded }"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg></template>`;
            }
            html += `</div>`;

            // Type icon (pre-rendered SVG)
            if (node.type_icon_svg) {
                html += node.type_icon_svg;
            }

            // Name link
            html += `<a href="/organization/entities/${node.id}" class="text-sm font-semibold text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate" @click.stop>${escHtml(node.name)}</a>`;

            // Code
            if (node.code) {
                html += `<span class="text-xs text-[var(--ui-muted)] font-mono flex-shrink-0">${escHtml(node.code)}</span>`;
            }

            // Type badge
            html += `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0">${escHtml(node.type_name)}</span>`;

            // Time
            if (totalMin > 0) {
                const dotClass = openMin > 0 ? 'bg-amber-400' : 'bg-green-500';
                html += `<div class="flex items-center gap-2 ml-auto flex-shrink-0">`;
                html += `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--ui-secondary)]">`;
                html += `<span class="w-2 h-2 rounded-full flex-shrink-0 ${dotClass}"></span>`;
                html += formatTime(totalMin);
                html += `</span>`;
                if (openMin > 0) {
                    html += `<span class="text-[10px] text-amber-600 font-medium">${formatOpenTime(totalMin, billedMin)}</span>`;
                }
                html += `</div>`;
            }

            html += `</div>`; // end row 1

            // Row 2: Link pills
            if (node.total_links > 0 || node.descendant_count > 0) {
                html += `<div class="flex items-center gap-1.5 mt-1.5 ml-7 flex-wrap">`;
                if (node.cascaded_link_counts) {
                    for (const [type, count] of Object.entries(node.cascaded_link_counts)) {
                        if (count > 0 && linkConfig[type]) {
                            const iconSvg = linkIconSvgs[type] ? linkIconSvgs[type].replace('w-4 h-4', 'w-3 h-3') : '';
                            html += `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/20">`;
                            html += iconSvg;
                            html += `${escHtml(linkConfig[type].label)} <span class="font-semibold text-[var(--ui-secondary)]">${count}</span>`;
                            html += `</span>`;
                        }
                    }
                }
                if (node.descendant_count > 0) {
                    html += `<span class="text-[10px] text-[var(--ui-muted)] ml-1">inkl. ${node.descendant_count} ${node.descendant_count === 1 ? 'Untereinheit' : 'Untereinheiten'}</span>`;
                }
                html += `</div>`;
            }

            html += `</div>`; // end card
            return html;
        };

        // Shared render function for link groups (used at all Alpine levels)
        window._treeRenderLinkGroups = function(node, linkConfig, linkIconSvgs) {
            if (!node.own_links_grouped || node.own_links_grouped.length === 0) return '';

            let html = '';
            for (const group of node.own_links_grouped) {
                const iconSvg = linkIconSvgs[group.type] || '';
                html += `<div x-data="{ gOpen: $store.tree.allExpanded, init() { this.$watch('$store.tree.allExpanded', v => this.gOpen = v); } }" class="ml-6 border-l-2 border-[var(--ui-border)]/20">`;
                // Group header
                html += `<div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3 cursor-pointer" @click.stop="gOpen = !gOpen">`;
                html += `<div class="flex items-center gap-2">`;
                html += `<div class="w-5 h-5 flex items-center justify-center flex-shrink-0"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200" :class="{ 'rotate-90': gOpen }"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg></div>`;
                html += iconSvg;
                html += `<span class="text-sm font-medium text-[var(--ui-secondary)]">${escHtml(group.label)}</span>`;
                html += `<span class="text-xs text-[var(--ui-muted)]">(${group.items.length})</span>`;
                if (group.group_logged_minutes > 0) {
                    html += `<span class="text-xs text-[var(--ui-muted)] ml-auto flex-shrink-0">${formatTime(group.group_logged_minutes)}</span>`;
                }
                html += `</div></div>`;

                // Group items
                html += `<div x-show="gOpen" x-collapse x-cloak>`;
                for (const link of group.items) {
                    const hasTasks = link.has_tasks && link.task_items && link.task_items.length > 0;
                    const linkId = 'link_' + link.id;

                    html += `<div class="ml-6 border-l-2 border-[var(--ui-border)]/20"${hasTasks ? ` x-data="{ ${linkId}: $store.tree.allExpanded, init() { this.$watch('$store.tree.allExpanded', v => this.${linkId} = v); } }"` : ''}>`;
                    html += `<div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">`;
                    html += `<div class="flex items-center gap-2${hasTasks ? ' cursor-pointer' : ''}"${hasTasks ? ` @click="${linkId} = !${linkId}"` : ''}>`;

                    // Chevron for expandable projects
                    html += `<div class="w-5 h-5 flex items-center justify-center flex-shrink-0">`;
                    if (hasTasks) {
                        html += `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200" :class="{ 'rotate-90': ${linkId} }"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>`;
                    }
                    html += `</div>`;

                    html += iconSvg;
                    if (link.url) {
                        html += `<a href="${escHtml(link.url)}" class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate" @click.stop>${escHtml(link.name)}</a>`;
                    } else {
                        html += `<span class="text-sm font-medium text-[var(--ui-secondary)] truncate">${escHtml(link.name)}</span>`;
                    }
                    html += renderLinkMeta(link, group.type);
                    if (link.status) {
                        html += `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0">${escHtml(link.status)}</span>`;
                    }
                    if (link.url) {
                        html += `<div class="ml-auto flex-shrink-0">${externalSvg}</div>`;
                    }
                    html += `</div></div>`;

                    // Task children for projects
                    if (hasTasks) {
                        const taskIconSvg = linkIconSvgs['planner_task'] || '';
                        html += `<div x-show="${linkId}" x-collapse x-cloak>`;
                        for (const task of link.task_items) {
                            const doneClass = task.is_done ? 'line-through opacity-60' : '';
                            html += `<div class="ml-6 border-l-2 border-[var(--ui-border)]/20">`;
                            html += `<div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3">`;
                            html += `<div class="flex items-center gap-2">`;
                            html += `<div class="w-5 h-5 flex-shrink-0"></div>`;
                            html += taskIconSvg;
                            html += `<span class="text-sm font-medium text-[var(--ui-secondary)] truncate ${doneClass}">${escHtml(task.name)}</span>`;
                            if (task.priority) html += `<span class="text-[10px] text-[var(--ui-muted)]">${escHtml(task.priority)}</span>`;
                            if (task.logged_minutes > 0) html += `<span class="text-[10px] text-[var(--ui-muted)]">${formatTime(task.logged_minutes)}</span>`;
                            if (task.due_date) html += `<span class="text-[10px] text-[var(--ui-muted)]">${escHtml(task.due_date)}</span>`;
                            if (task.is_done) html += `<span class="text-[10px] text-green-600">erledigt</span>`;
                            html += `</div></div></div>`;
                        }
                        html += `</div>`;
                    }

                    html += `</div>`;
                }
                html += `</div></div>`;
            }
            return html;
        };

        Alpine.store('tree', {
            allExpanded: false,
            preloadedNodes: {},
            loading: false,
            async expandAll(wire) {
                this.loading = true;
                try {
                    this.preloadedNodes = await wire.loadEntireTree();
                    this.allExpanded = true;
                } finally {
                    this.loading = false;
                }
            },
            collapseAll() {
                this.allExpanded = false;
                this.preloadedNodes = {};
            },
        });

        Alpine.data('treeNode', (node) => ({
            nodeExpanded: false,
            nodeLoading: false,
            nodeChildren: [],
            get nodeIsExpandable() { return node.has_children || (node.own_links_grouped && node.own_links_grouped.length > 0); },
            init() {
                this.$watch('$store.tree.allExpanded', (val) => {
                    if (val && this.nodeIsExpandable) {
                        this.nodeExpand();
                    } else if (!val) {
                        this.nodeExpanded = false;
                        this.nodeChildren = [];
                    }
                });
            },
            async nodeExpand() {
                if (node.has_children && this.nodeChildren.length === 0) {
                    const store = Alpine.store('tree');
                    const preloaded = store?.preloadedNodes?.[node.id];
                    if (preloaded) {
                        this.nodeChildren = preloaded;
                    }
                }
                this.nodeExpanded = true;
            },
            async nodeToggle() {
                if (!this.nodeIsExpandable) return;
                if (this.nodeExpanded) { this.nodeExpanded = false; return; }
                if (node.has_children && this.nodeChildren.length === 0) {
                    this.nodeLoading = true;
                    try {
                        const store = Alpine.store('tree');
                        const preloaded = store?.preloadedNodes?.[node.id];
                        this.nodeChildren = preloaded || await this.$wire.loadChildNodes(node.id);
                    } finally {
                        this.nodeLoading = false;
                    }
                }
                this.nodeExpanded = true;
            },
            renderNode(n) {
                const cfg = Alpine.store('treeConfig');
                return window._treeRenderNode(n, cfg.linkConfig, cfg.linkIconSvgs);
            },
            renderLinkGroups(n) {
                const cfg = Alpine.store('treeConfig');
                return window._treeRenderLinkGroups(n, cfg.linkConfig, cfg.linkIconSvgs);
            },
        }));
    });
    </script>

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
