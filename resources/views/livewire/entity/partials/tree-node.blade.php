{{-- Tree Node - Server-rendered first level, Alpine.js for deeper levels --}}
{{-- Variables: $node (array), $depth (int, default 0) --}}
@php $depth = $depth ?? 0; @endphp

<div
    x-data="{
        expanded: false,
        loading: false,
        childData: [],
        hasChildren: {{ $node['has_children'] ? 'true' : 'false' }},
        async toggle() {
            if (!this.hasChildren) return;
            if (this.expanded) {
                this.expanded = false;
                return;
            }
            if (this.childData.length === 0) {
                this.loading = true;
                try {
                    this.childData = await $wire.loadChildNodes({{ $node['id'] }});
                } finally {
                    this.loading = false;
                }
            }
            this.expanded = true;
        }
    }"
    class="{{ $depth > 0 ? 'ml-6 border-l-2 border-[var(--ui-border)]/30' : '' }}"
>
    {{-- Node Row --}}
    <div
        class="group flex items-center gap-2 py-2 px-3 rounded-lg transition-colors {{ $node['has_children'] ? 'cursor-pointer' : '' }} hover:bg-[var(--ui-muted-5)] {{ !$node['is_active'] ? 'opacity-50' : '' }}"
        @if($node['has_children']) @click="toggle()" @endif
    >
        {{-- Expand/Collapse --}}
        <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
            @if($node['has_children'])
                <template x-if="loading">
                    <svg class="w-4 h-4 animate-spin text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </template>
                <template x-if="!loading">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                        class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                        :class="{ 'rotate-90': expanded }">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </template>
            @endif
        </div>

        {{-- Type Icon --}}
        @if($node['type_icon'])
            @svg('heroicon-o-' . $node['type_icon'], 'w-4 h-4 text-[var(--ui-muted)] flex-shrink-0')
        @endif

        {{-- Name --}}
        <a
            href="{{ route('organization.entities.show', $node['id']) }}"
            class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline"
            @click.stop
        >
            {{ $node['name'] }}
        </a>

        {{-- Code --}}
        @if($node['code'])
            <span class="text-xs text-[var(--ui-muted)] flex-shrink-0">{{ $node['code'] }}</span>
        @endif

        {{-- Children count --}}
        @if($node['has_children'])
            <span class="text-xs text-[var(--ui-muted)]">({{ $node['children_count'] }})</span>
        @endif

        {{-- Link Pills + Time Badge --}}
        <div class="flex items-center gap-1 ml-auto flex-shrink-0">
            @foreach($node['link_counts'] as $type => $count)
                @php $config = $this->linkTypeConfig[$type] ?? null; @endphp
                @if($config && $count > 0)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-[var(--ui-muted-5)] text-[var(--ui-muted)]" title="{{ $config['label'] }}">
                        @svg('heroicon-o-' . $config['icon'], 'w-3 h-3')
                        {{ $count }}
                    </span>
                @endif
            @endforeach

            @if($node['time_summary']['total_minutes'] > 0)
                @php
                    $totalMin = $node['time_summary']['total_minutes'];
                    $billedMin = $node['time_summary']['billed_minutes'];
                    $openMin = $totalMin - $billedMin;
                    $hours = intdiv($totalMin, 60);
                    $mins = $totalMin % 60;
                @endphp
                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--ui-secondary)] ml-2">
                    <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $openMin > 0 ? 'bg-amber-400' : 'bg-green-500' }}"></span>
                    {{ $hours }}:{{ str_pad($mins, 2, '0', STR_PAD_LEFT) }}h
                </span>
            @endif
        </div>
    </div>

    {{-- Children (lazy-loaded via Alpine.js) --}}
    @if($node['has_children'])
        <div x-show="expanded" x-collapse x-cloak>
            <template x-for="child in childData" :key="child.id">
                <div
                    x-data="{
                        cExpanded: false,
                        cLoading: false,
                        cChildren: [],
                        async cToggle() {
                            if (!child.has_children) return;
                            if (this.cExpanded) { this.cExpanded = false; return; }
                            if (this.cChildren.length === 0) {
                                this.cLoading = true;
                                try { this.cChildren = await $wire.loadChildNodes(child.id); }
                                finally { this.cLoading = false; }
                            }
                            this.cExpanded = true;
                        }
                    }"
                    class="ml-6 border-l-2 border-[var(--ui-border)]/30"
                >
                    <div
                        class="group flex items-center gap-2 py-2 px-3 rounded-lg transition-colors hover:bg-[var(--ui-muted-5)]"
                        :class="{ 'opacity-50': !child.is_active, 'cursor-pointer': child.has_children }"
                        @click="cToggle()"
                    >
                        <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                            <template x-if="child.has_children && cLoading">
                                <svg class="w-4 h-4 animate-spin text-[var(--ui-muted)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </template>
                            <template x-if="child.has_children && !cLoading">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                    class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                                    :class="{ 'rotate-90': cExpanded }">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </template>
                        </div>

                        <a
                            :href="'/organization/entities/' + child.id"
                            class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline"
                            @click.stop
                            x-text="child.name"
                        ></a>

                        <template x-if="child.code">
                            <span class="text-xs text-[var(--ui-muted)] flex-shrink-0" x-text="child.code"></span>
                        </template>

                        <template x-if="child.has_children">
                            <span class="text-xs text-[var(--ui-muted)]" x-text="'(' + child.children_count + ')'"></span>
                        </template>

                        <div class="flex items-center gap-1 ml-auto flex-shrink-0">
                            <template x-for="(count, type) in child.link_counts" :key="type">
                                <template x-if="count > 0">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-[var(--ui-muted-5)] text-[var(--ui-muted)]">
                                        <span x-text="count"></span>
                                    </span>
                                </template>
                            </template>

                            <template x-if="child.time_summary && child.time_summary.total_minutes > 0">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--ui-secondary)] ml-2">
                                    <span
                                        class="w-2 h-2 rounded-full flex-shrink-0"
                                        :class="(child.time_summary.total_minutes - child.time_summary.billed_minutes) > 0 ? 'bg-amber-400' : 'bg-green-500'"
                                    ></span>
                                    <span x-text="Math.floor(child.time_summary.total_minutes / 60) + ':' + String(child.time_summary.total_minutes % 60).padStart(2, '0') + 'h'"></span>
                                </span>
                            </template>
                        </div>
                    </div>

                    {{-- Grandchildren --}}
                    <template x-if="child.has_children">
                        <div x-show="cExpanded" x-collapse x-cloak>
                            <template x-for="gc in cChildren" :key="gc.id">
                                <div class="ml-6 border-l-2 border-[var(--ui-border)]/30">
                                    <div
                                        class="group flex items-center gap-2 py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors"
                                        :class="{ 'opacity-50': !gc.is_active }"
                                    >
                                        <div class="w-5 h-5 flex-shrink-0">
                                            <template x-if="gc.has_children">
                                                <a :href="'/organization/entities/' + gc.id" class="flex items-center justify-center w-5 h-5">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[var(--ui-muted)]">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                    </svg>
                                                </a>
                                            </template>
                                        </div>
                                        <a
                                            :href="'/organization/entities/' + gc.id"
                                            class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline"
                                            x-text="gc.name"
                                        ></a>
                                        <template x-if="gc.code">
                                            <span class="text-xs text-[var(--ui-muted)]" x-text="gc.code"></span>
                                        </template>
                                        <template x-if="gc.has_children">
                                            <span class="text-xs text-[var(--ui-muted)]" x-text="'(' + gc.children_count + ')'"></span>
                                        </template>
                                        <div class="flex items-center gap-1 ml-auto flex-shrink-0">
                                            <template x-if="gc.time_summary && gc.time_summary.total_minutes > 0">
                                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-[var(--ui-secondary)] ml-2">
                                                    <span class="w-2 h-2 rounded-full flex-shrink-0" :class="(gc.time_summary.total_minutes - gc.time_summary.billed_minutes) > 0 ? 'bg-amber-400' : 'bg-green-500'"></span>
                                                    <span x-text="Math.floor(gc.time_summary.total_minutes / 60) + ':' + String(gc.time_summary.total_minutes % 60).padStart(2, '0') + 'h'"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    @endif
</div>
