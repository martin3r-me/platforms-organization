@php
    $agents = $this->agents;
    $online = collect($agents)->where('online', true)->count();
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Agenten" icon="heroicon-o-cpu-chip" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Agenten'],
        ]" />
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5" wire:poll.15s>
            <div>
                <div class="flex flex-wrap items-baseline gap-2">
                    <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">Agenten</h1>
                    <span class="text-sm text-[color:var(--nx-muted)]">{{ $online }} von {{ count($agents) }} online</span>
                </div>
                <p class="mt-1 text-sm text-[color:var(--nx-muted)]">
                    Alle Agent-Mitglieder, die bei der Organisation einzahlen — sichtbar ist, wer Heartbeat meldet, egal wo gehostet.
                </p>
            </div>

            @if (count($agents) === 0)
                <x-nx-card flush>
                    <x-nx-empty icon="heroicon-o-cpu-chip">Noch keine Agent-Mitglieder.</x-nx-empty>
                </x-nx-card>
            @else
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($agents as $a)
                        @php
                            $gap = $a['calib_gap'] !== null ? (float) $a['calib_gap'] : null;
                            [$calLabel, $calColor] = match (true) {
                                $gap === null || $a['calib_n'] === 0 => [null, null],
                                $gap > 0.05 => ['überkonfident', 'var(--nx-warning)'],
                                $gap < -0.05 => ['zu vorsichtig', 'var(--nx-info)'],
                                default => ['kalibriert', 'var(--nx-success)'],
                            };
                        @endphp
                        <a wire:key="ag-{{ $a['id'] }}" href="{{ route('organization.entities.show', $a['id']) }}" wire:navigate
                           class="flex flex-col gap-3 rounded-[8px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-4 transition-colors hover:bg-[color:var(--nx-hover)]">
                            {{-- Kopf: Name + Liveness + Status --}}
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $a['online'] ? 'var(--nx-success)' : 'var(--nx-faint)' }}"
                                              title="{{ $a['online'] ? 'online' : 'offline' }}"></span>
                                        <span class="truncate font-semibold text-[color:var(--nx-text)]">{{ $a['name'] }}</span>
                                    </div>
                                    <div class="mt-0.5 truncate text-xs text-[color:var(--nx-muted)]">
                                        {{ $a['domain'] ?? 'keine Rolle' }}@if ($a['subscription']) · {{ $a['subscription'] }}@endif
                                    </div>
                                </div>
                                @if (! $a['active'])
                                    <x-nx-badge>inaktiv</x-nx-badge>
                                @elseif ($a['status'])
                                    <x-nx-badge :variant="$a['online'] ? 'success' : 'neutral'">{{ $a['status'] }}</x-nx-badge>
                                @endif
                            </div>

                            {{-- Woran arbeitet er gerade (dev+planner-Lock) --}}
                            <div class="rounded-[6px] bg-[color:var(--nx-bg)] px-3 py-2 text-sm">
                                @if ($a['work'])
                                    <div class="flex items-center gap-1.5 font-medium text-[color:var(--nx-text)]">
                                        @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 shrink-0 text-[color:var(--nx-accent)]')
                                        <span class="truncate">{{ $a['work']['title'] }}</span>
                                    </div>
                                    <div class="mt-0.5 text-xs text-[color:var(--nx-muted)]">
                                        {{ $a['work']['where'] }}@if ($a['work']['since_min'] !== null) · seit {{ $a['work']['since_min'] }} min @endif
                                    </div>
                                @elseif ($a['last_event'])
                                    <span class="text-[color:var(--nx-muted)]">{{ $a['last_event']['label'] }}@if ($a['last_event']['at']) · {{ $a['last_event']['at']->diffForHumans() }}@endif</span>
                                @else
                                    <span class="text-[color:var(--nx-faint)]">nichts in Arbeit</span>
                                @endif
                                @if ($a['tracked_today_min'] > 0)
                                    <div class="mt-0.5 text-xs text-[color:var(--nx-faint)]">heute {{ $a['tracked_today_min'] }} min gebucht</div>
                                @endif
                            </div>

                            {{-- Kennzahlen --}}
                            <div class="grid grid-cols-3 gap-2 text-xs">
                                <div>
                                    <div class="text-[color:var(--nx-faint)]">5h-Fenster</div>
                                    <div class="font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $a['five_hour_pct'] !== null ? number_format($a['five_hour_pct'], 0) . ' %' : '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-[color:var(--nx-faint)]">7 Tage</div>
                                    <div class="font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $a['seven_day_pct'] !== null ? number_format($a['seven_day_pct'], 0) . ' %' : '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-[color:var(--nx-faint)]">Kalibrierung</div>
                                    @if ($calLabel)
                                        <div class="font-semibold tabular-nums" style="color: {{ $calColor }}" title="{{ number_format(($a['calib_accuracy'] ?? 0) * 100, 0) }} % Treffer · {{ $a['calib_n'] }} Paare">
                                            {{ sprintf('%+.2f', $gap) }} · {{ $calLabel }}
                                        </div>
                                    @else
                                        <div class="text-[color:var(--nx-faint)]">—</div>
                                    @endif
                                </div>
                            </div>

                            <div class="border-t border-[color:var(--nx-line)] pt-2 text-[11px] text-[color:var(--nx-faint)]">
                                zuletzt gemeldet: {{ $a['last_heartbeat'] ? $a['last_heartbeat']->diffForHumans() : 'nie' }}@if ($a['snapshot_at']) · Snapshot {{ $a['snapshot_at']->diffForHumans() }}@endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
