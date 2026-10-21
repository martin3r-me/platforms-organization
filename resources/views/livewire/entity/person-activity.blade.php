{{-- Tab „Aktivität" einer Person (eingebettet in entity/show). --}}
<div class="space-y-5">
    @if (! $entity->linked_user_id)
        <x-nx-card flush>
            <x-nx-empty icon="heroicon-o-user">
                Kein User verknüpft. Verknüpfe im Tab „Daten" einen User, um Aktivitäten zu sehen.
            </x-nx-empty>
        </x-nx-card>
    @else
        @php
            $linkedUser = $entity->linkedUser;
            $vitalSigns = $this->vitalSigns;
            $responsibilities = $this->responsibilities;
            $sectionConfigs = $this->sectionConfigs;
            $icon = fn ($i) => 'heroicon-o-' . (app('safe-svg')->resolve($i ?? null, 'heroicon-o-') ?? 'cube');
            $tone = fn ($v) => match ($v) {
                'danger' => 'var(--nx-danger)',
                'warning' => 'var(--nx-warning)',
                'success' => 'var(--nx-success)',
                default => 'var(--nx-text)',
            };
        @endphp

        <div class="flex items-center gap-3">
            <x-nx-avatar :name="$linkedUser->name ?? '?'" :src="$linkedUser && $linkedUser->avatar ? $linkedUser->avatarUrl() : null" size="lg" />
            <div class="min-w-0">
                <div class="truncate text-base font-semibold text-[color:var(--nx-text)]">{{ $linkedUser->name ?? '–' }}</div>
                @if ($linkedUser?->email)
                    <div class="truncate text-sm text-[color:var(--nx-muted)]">{{ $linkedUser->email }}</div>
                @endif
            </div>
        </div>

        @foreach ($vitalSigns as $sectionKey => $signs)
            @php
                $config = $sectionConfigs[$sectionKey] ?? ['label' => $sectionKey, 'icon' => 'chart-bar'];
            @endphp
            <section class="space-y-2">
                <div class="flex items-center gap-2">
                    @svg($icon($config['icon'] ?? null), 'w-4 h-4 text-[color:var(--nx-faint)]')
                    <h3 class="m-0 text-sm font-semibold text-[color:var(--nx-text)]">{{ $config['label'] }}</h3>
                </div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    @foreach ($signs as $sign)
                        <div class="rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-4">
                            <div class="text-xs font-medium text-[color:var(--nx-muted)]">{{ $sign['label'] }}</div>
                            <div class="mt-2 text-2xl font-semibold tabular-nums" style="color: {{ $tone($sign['variant'] ?? null) }}">{{ $sign['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        @foreach ($responsibilities as $sectionKey => $groups)
            <x-nx-card flush>
                @foreach ($groups as $group)
                    <div x-data="{ open: true }" class="border-b border-[color:var(--nx-line)] last:border-0">
                        <button type="button" x-on:click="open = !open"
                            class="flex w-full items-center gap-2 px-4 py-3 text-left hover:bg-[color:var(--nx-hover)]">
                            @svg($icon($group['icon'] ?? null), 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                            <span class="text-sm font-semibold text-[color:var(--nx-text)]">{{ $group['label'] }}</span>
                            <span class="text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $group['total_count'] }}</span>
                            <svg class="ml-auto h-4 w-4 text-[color:var(--nx-muted)] transition-transform" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M6 8l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <div x-show="open" x-collapse x-cloak>
                            @foreach ($group['items'] as $item)
                                <div class="flex items-center justify-between gap-3 border-t border-[color:var(--nx-line)] px-4 py-2 hover:bg-[color:var(--nx-hover)]">
                                    @if ($item['url'])
                                        <a href="{{ $item['url'] }}" class="truncate text-sm text-[color:var(--nx-text)] hover:underline">{{ $item['name'] }}</a>
                                    @else
                                        <span class="truncate text-sm text-[color:var(--nx-text)]">{{ $item['name'] }}</span>
                                    @endif
                                    @if ($item['meta'] ?? null)
                                        <span class="shrink-0 text-xs text-[color:var(--nx-muted)]">{{ $item['meta'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                            @if ($group['total_count'] > count($group['items']))
                                <div class="border-t border-[color:var(--nx-line)] px-4 py-2 text-xs text-[color:var(--nx-faint)]">
                                    +{{ $group['total_count'] - count($group['items']) }} weitere
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </x-nx-card>
        @endforeach

        @if (empty($vitalSigns) && empty($responsibilities))
            <x-nx-card flush>
                <x-nx-empty icon="heroicon-o-inbox">Für diese Person wurden noch keine Aktivitäten erfasst.</x-nx-empty>
            </x-nx-card>
        @endif
    @endif
</div>
