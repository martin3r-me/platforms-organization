<div>
    @if(!$entity->linked_user_id)
        <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                @svg('heroicon-o-user', 'w-8 h-8 text-[var(--ui-muted)]')
            </div>
            <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Kein User verknüpft</p>
            <p class="text-xs text-[var(--ui-muted)]">Verknüpfe einen User im Tab "Daten", um Personen-Aktivitäten zu sehen.</p>
        </div>
    @else
        @php
            $linkedUser = $entity->linkedUser;
            $vitalSigns = $this->vitalSigns;
            $responsibilities = $this->responsibilities;
            $sectionConfigs = $this->sectionConfigs;
        @endphp

        {{-- User Info Header --}}
        <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-[var(--ui-muted-5)] flex items-center justify-center border border-[var(--ui-border)]/40">
                    @svg('heroicon-o-user', 'w-6 h-6 text-[var(--ui-muted)]')
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)]">{{ $linkedUser->name ?? '—' }}</h3>
                    @if($linkedUser?->email)
                        <p class="text-sm text-[var(--ui-muted)]">{{ $linkedUser->email }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Vital Signs --}}
        @if(!empty($vitalSigns))
            @foreach($vitalSigns as $sectionKey => $signs)
                @php $config = $sectionConfigs[$sectionKey] ?? ['label' => $sectionKey, 'icon' => 'chart-bar']; @endphp
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        @svg('heroicon-o-' . $config['icon'], 'w-4 h-4 text-[var(--ui-muted)]')
                        <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider">{{ $config['label'] }}</h3>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        @foreach($signs as $sign)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40 text-center">
                                <div class="text-2xl font-bold
                                    @if($sign['variant'] === 'danger') text-red-600
                                    @elseif($sign['variant'] === 'warning') text-amber-600
                                    @elseif($sign['variant'] === 'success') text-green-600
                                    @else text-[var(--ui-secondary)]
                                    @endif
                                ">{{ $sign['value'] }}</div>
                                <div class="text-xs text-[var(--ui-muted)] mt-1">{{ $sign['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Responsibilities --}}
        @if(!empty($responsibilities))
            @foreach($responsibilities as $sectionKey => $groups)
                @php $config = $sectionConfigs[$sectionKey] ?? ['label' => $sectionKey, 'icon' => 'chart-bar']; @endphp
                <div class="bg-white rounded-lg border border-[var(--ui-border)] mb-4">
                    @foreach($groups as $group)
                        <div x-data="{ open: true }">
                            {{-- Group Header --}}
                            <div class="flex items-center justify-between px-6 py-4 cursor-pointer hover:bg-[var(--ui-muted-5)] transition-colors"
                                 :class="{ 'border-b border-[var(--ui-border)]/40': open }"
                                 @click="open = !open">
                                <div class="flex items-center gap-2">
                                    @svg('heroicon-o-' . $group['icon'], 'w-4 h-4 text-[var(--ui-muted)]')
                                    <span class="text-sm font-semibold text-[var(--ui-secondary)]">{{ $group['label'] }}</span>
                                    <span class="text-xs text-[var(--ui-muted)]">({{ $group['total_count'] }})</span>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                     class="w-4 h-4 text-[var(--ui-muted)] transition-transform duration-200"
                                     :class="{ 'rotate-180': open }">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </div>

                            {{-- Group Items --}}
                            <div x-show="open" x-collapse x-cloak>
                                <div class="divide-y divide-[var(--ui-border)]/40">
                                    @foreach($group['items'] as $item)
                                        <div class="px-6 py-3 flex items-center justify-between hover:bg-[var(--ui-muted-5)] transition-colors">
                                            <div class="flex items-center gap-2 min-w-0">
                                                @if($item['url'])
                                                    <a href="{{ $item['url'] }}" class="text-sm font-medium text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] hover:underline truncate">
                                                        {{ $item['name'] }}
                                                    </a>
                                                @else
                                                    <span class="text-sm font-medium text-[var(--ui-secondary)] truncate">{{ $item['name'] }}</span>
                                                @endif
                                            </div>
                                            @if($item['meta'] ?? null)
                                                <span class="text-xs text-[var(--ui-muted)] flex-shrink-0 ml-2">{{ $item['meta'] }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if($group['total_count'] > count($group['items']))
                                        <div class="px-6 py-3 text-center">
                                            <span class="text-xs text-[var(--ui-muted)]">
                                                +{{ $group['total_count'] - count($group['items']) }} weitere
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif

        @if(empty($vitalSigns) && empty($responsibilities))
            <div class="p-8 text-center rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-[var(--ui-surface)] flex items-center justify-center">
                    @svg('heroicon-o-inbox', 'w-8 h-8 text-[var(--ui-muted)]')
                </div>
                <p class="text-sm font-medium text-[var(--ui-secondary)] mb-1">Keine Aktivitäten</p>
                <p class="text-xs text-[var(--ui-muted)]">Für diese Person wurden noch keine Aktivitäten erfasst.</p>
            </div>
        @endif
    @endif
</div>
