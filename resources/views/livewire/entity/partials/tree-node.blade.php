{{-- Tree Node - Server-rendered first level, Alpine.js for deeper levels --}}
{{-- Variables: $node (array), $depth (int, default 0) --}}
@php $depth = $depth ?? 0; @endphp

@php
    // Prepare cascaded time formatting
    $cTotalMin = $node['cascaded_time']['total_minutes'];
    $cBilledMin = $node['cascaded_time']['billed_minutes'];
    $cOpenMin = $cTotalMin - $cBilledMin;
    $cHours = intdiv($cTotalMin, 60);
    $cMins = $cTotalMin % 60;
    $cOpenH = intdiv(abs($cOpenMin), 60);
    $cOpenM = abs($cOpenMin) % 60;
    $hasCascadedData = $node['descendant_count'] > 0;
@endphp

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
    class="{{ $depth > 0 ? 'ml-6 border-l-2 border-[var(--ui-border)]/20' : '' }}"
>
    {{-- Node Card --}}
    <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] {{ !$node['is_active'] ? 'opacity-50' : '' }} py-2 px-3">
        {{-- Row 1: Name + Type --}}
        <div class="flex items-center gap-2 {{ $node['has_children'] ? 'cursor-pointer' : '' }}"
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
                class="text-sm font-semibold text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate"
                @click.stop
            >
                {{ $node['name'] }}
            </a>

            {{-- Code --}}
            @if($node['code'])
                <span class="text-xs text-[var(--ui-muted)] font-mono flex-shrink-0">{{ $node['code'] }}</span>
            @endif

            {{-- Type Badge --}}
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0">
                {{ $node['type_name'] }}
            </span>

            {{-- Time Summary (right-aligned) --}}
            @if($cTotalMin > 0)
                <div class="flex items-center gap-2 ml-auto flex-shrink-0">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--ui-secondary)]">
                        @if($cOpenMin > 0)
                            <span class="w-2 h-2 rounded-full flex-shrink-0 bg-amber-400"></span>
                        @else
                            <span class="w-2 h-2 rounded-full flex-shrink-0 bg-green-500"></span>
                        @endif
                        {{ $cHours }}:{{ str_pad($cMins, 2, '0', STR_PAD_LEFT) }}h
                    </span>
                    @if($cOpenMin > 0)
                        <span class="text-[10px] text-amber-600 font-medium">
                            {{ $cOpenH }}:{{ str_pad($cOpenM, 2, '0', STR_PAD_LEFT) }}h offen
                        </span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Row 2: Link Pills + Descendants info --}}
        @if(!empty($node['cascaded_link_counts']) || $hasCascadedData)
            <div class="flex items-center gap-1.5 mt-1.5 ml-7 flex-wrap">
                @foreach($node['cascaded_link_counts'] as $type => $count)
                    @php $config = $this->linkTypeConfig[$type] ?? null; @endphp
                    @if($config && $count > 0)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/20">
                            @svg('heroicon-o-' . $config['icon'], 'w-3 h-3')
                            {{ $config['label'] }}
                            <span class="font-semibold text-[var(--ui-secondary)]">{{ $count }}</span>
                        </span>
                    @endif
                @endforeach

                @if($hasCascadedData)
                    <span class="text-[10px] text-[var(--ui-muted)] ml-1">
                        inkl. {{ $node['descendant_count'] }} {{ $node['descendant_count'] === 1 ? 'Untereinheit' : 'Untereinheiten' }}
                    </span>
                @endif
            </div>
        @endif
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
                    class="ml-6 border-l-2 border-[var(--ui-border)]/20"
                >
                    {{-- Dynamic Node Card --}}
                    <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3"
                        :class="{ 'opacity-50': !child.is_active }">
                        {{-- Row 1: Name + Type + Time --}}
                        <div class="flex items-center gap-2"
                            :class="{ 'cursor-pointer': child.has_children }"
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
                                class="text-sm font-semibold text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate"
                                @click.stop
                                x-text="child.name"
                            ></a>

                            <template x-if="child.code">
                                <span class="text-xs text-[var(--ui-muted)] font-mono flex-shrink-0" x-text="child.code"></span>
                            </template>

                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0" x-text="child.type_name"></span>

                            {{-- Time Summary --}}
                            <template x-if="child.cascaded_time && child.cascaded_time.total_minutes > 0">
                                <div class="flex items-center gap-2 ml-auto flex-shrink-0">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--ui-secondary)]">
                                        <span
                                            class="w-2 h-2 rounded-full flex-shrink-0"
                                            :class="(child.cascaded_time.total_minutes - child.cascaded_time.billed_minutes) > 0 ? 'bg-amber-400' : 'bg-green-500'"
                                        ></span>
                                        <span x-text="Math.floor(child.cascaded_time.total_minutes / 60) + ':' + String(child.cascaded_time.total_minutes % 60).padStart(2, '0') + 'h'"></span>
                                    </span>
                                    <template x-if="(child.cascaded_time.total_minutes - child.cascaded_time.billed_minutes) > 0">
                                        <span class="text-[10px] text-amber-600 font-medium"
                                            x-text="Math.floor((child.cascaded_time.total_minutes - child.cascaded_time.billed_minutes) / 60) + ':' + String((child.cascaded_time.total_minutes - child.cascaded_time.billed_minutes) % 60).padStart(2, '0') + 'h offen'"
                                        ></span>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Row 2: Link Pills + Descendants --}}
                        <template x-if="child.total_links > 0 || child.descendant_count > 0">
                            <div class="flex items-center gap-1.5 mt-1.5 ml-7 flex-wrap">
                                <template x-for="(count, type) in child.cascaded_link_counts" :key="type">
                                    <template x-if="count > 0">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/20">
                                            <span x-text="linkConfig[type] ? linkConfig[type].label : type"></span>
                                            <span class="font-semibold text-[var(--ui-secondary)]" x-text="count"></span>
                                        </span>
                                    </template>
                                </template>
                                <template x-if="child.descendant_count > 0">
                                    <span class="text-[10px] text-[var(--ui-muted)] ml-1"
                                        x-text="'inkl. ' + child.descendant_count + (child.descendant_count === 1 ? ' Untereinheit' : ' Untereinheiten')"
                                    ></span>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Grandchildren --}}
                    <template x-if="child.has_children">
                        <div x-show="cExpanded" x-collapse x-cloak>
                            <template x-for="gc in cChildren" :key="gc.id">
                                <div class="ml-6 border-l-2 border-[var(--ui-border)]/20">
                                    <div class="group rounded-lg transition-colors hover:bg-[var(--ui-muted-5)] py-2 px-3"
                                        :class="{ 'opacity-50': !gc.is_active }">
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 flex items-center justify-center flex-shrink-0">
                                                <template x-if="gc.has_children">
                                                    <a :href="'/organization/entities/' + gc.id" class="flex items-center justify-center w-5 h-5" @click.stop>
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-[var(--ui-muted)]">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                        </svg>
                                                    </a>
                                                </template>
                                            </div>
                                            <a
                                                :href="'/organization/entities/' + gc.id"
                                                class="text-sm font-semibold text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate"
                                                x-text="gc.name"
                                            ></a>
                                            <template x-if="gc.code">
                                                <span class="text-xs text-[var(--ui-muted)] font-mono" x-text="gc.code"></span>
                                            </template>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-[var(--ui-muted-5)] text-[var(--ui-muted)] flex-shrink-0" x-text="gc.type_name"></span>

                                            <template x-if="gc.cascaded_time && gc.cascaded_time.total_minutes > 0">
                                                <div class="flex items-center gap-2 ml-auto flex-shrink-0">
                                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--ui-secondary)]">
                                                        <span class="w-2 h-2 rounded-full flex-shrink-0" :class="(gc.cascaded_time.total_minutes - gc.cascaded_time.billed_minutes) > 0 ? 'bg-amber-400' : 'bg-green-500'"></span>
                                                        <span x-text="Math.floor(gc.cascaded_time.total_minutes / 60) + ':' + String(gc.cascaded_time.total_minutes % 60).padStart(2, '0') + 'h'"></span>
                                                    </span>
                                                    <template x-if="(gc.cascaded_time.total_minutes - gc.cascaded_time.billed_minutes) > 0">
                                                        <span class="text-[10px] text-amber-600 font-medium"
                                                            x-text="Math.floor((gc.cascaded_time.total_minutes - gc.cascaded_time.billed_minutes) / 60) + ':' + String((gc.cascaded_time.total_minutes - gc.cascaded_time.billed_minutes) % 60).padStart(2, '0') + 'h offen'"
                                                        ></span>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>

                                        <template x-if="gc.total_links > 0 || gc.descendant_count > 0">
                                            <div class="flex items-center gap-1.5 mt-1.5 ml-7 flex-wrap">
                                                <template x-for="(count, type) in gc.cascaded_link_counts" :key="type">
                                                    <template x-if="count > 0">
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] bg-[var(--ui-muted-5)] text-[var(--ui-muted)] border border-[var(--ui-border)]/20">
                                                            <span class="font-semibold text-[var(--ui-secondary)]" x-text="count"></span>
                                                        </span>
                                                    </template>
                                                </template>
                                                <template x-if="gc.descendant_count > 0">
                                                    <span class="text-[10px] text-[var(--ui-muted)] ml-1"
                                                        x-text="'inkl. ' + gc.descendant_count + (gc.descendant_count === 1 ? ' Untereinheit' : ' Untereinheiten')"
                                                    ></span>
                                                </template>
                                            </div>
                                        </template>
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
