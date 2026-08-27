<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Agenten'],
        ]" />
    </x-slot>

    <div class="p-6" wire:poll.15s>
        <div class="mb-4 text-sm text-[var(--ui-secondary)]">
            Alle Agent-Mitglieder, die bei der Organisation einzahlen — host-agnostisch (sichtbar, wer Heartbeat meldet, egal wo gehostet).
        </div>

        @php($agents = $this->agents)

        @if (count($agents) === 0)
            <div class="text-[var(--ui-secondary)] text-sm">Noch keine Agent-Mitglieder.</div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($agents as $a)
                    <a href="{{ route('organization.entities.show', $a['id']) }}" wire:navigate
                       class="block rounded-xl border border-[var(--ui-border)] bg-[var(--ui-surface)] p-4 hover:border-[var(--ui-primary)] transition">
                        {{-- Kopf: Name + Liveness --}}
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-block w-2.5 h-2.5 rounded-full {{ $a['online'] ? 'bg-emerald-500' : 'bg-neutral-400' }}"></span>
                                <span class="font-bold">{{ $a['name'] }}</span>
                            </div>
                            @if (! $a['active'])
                                <span class="text-[11px] uppercase tracking-wide text-neutral-400">inaktiv</span>
                            @elseif ($a['status'])
                                <span class="text-[11px] uppercase tracking-wide text-[var(--ui-secondary)]">{{ $a['status'] }}</span>
                            @endif
                        </div>

                        {{-- Rolle/Domäne --}}
                        <div class="text-xs text-[var(--ui-secondary)] mb-3">
                            {{ $a['domain'] ?? '— keine Domäne' }}
                            @if ($a['subscription']) · {{ $a['subscription'] }} @endif
                        </div>

                        {{-- Kalibrierung --}}
                        <div class="text-sm mb-2">
                            @if ($a['calib_n'] > 0 && $a['calib_gap'] !== null)
                                @php
                                    $gap = (float) $a['calib_gap'];
                                    $label = $gap > 0.05 ? 'überkonfident' : ($gap < -0.05 ? 'zu vorsichtig' : 'kalibriert');
                                    $color = $gap > 0.05 ? 'text-amber-600' : ($gap < -0.05 ? 'text-sky-600' : 'text-emerald-600');
                                @endphp
                                <span class="text-[var(--ui-secondary)]">Kalibrierung:</span>
                                <span class="font-semibold {{ $color }}">Gap {{ sprintf('%+.2f', $gap) }} ({{ $label }})</span>
                                <span class="text-[var(--ui-secondary)]">· {{ number_format($a['calib_accuracy'] * 100, 0) }}% Treffer · {{ $a['calib_n'] }} Paare</span>
                            @else
                                <span class="text-[var(--ui-secondary)]">Kalibrierung: — (noch keine Confidence-Paare)</span>
                            @endif
                        </div>

                        {{-- Budget-Fenster --}}
                        <div class="flex items-center gap-4 text-xs text-[var(--ui-secondary)]">
                            <span>5h-Fenster: <span class="font-semibold text-[var(--ui-fg)]">{{ $a['five_hour_pct'] !== null ? number_format($a['five_hour_pct'], 0).'%' : '—' }}</span></span>
                            <span>7d: <span class="font-semibold text-[var(--ui-fg)]">{{ $a['seven_day_pct'] !== null ? number_format($a['seven_day_pct'], 0).'%' : '—' }}</span></span>
                        </div>

                        {{-- Zuletzt gesehen --}}
                        <div class="mt-3 text-[11px] text-neutral-400">
                            zuletzt gemeldet: {{ $a['last_heartbeat'] ? $a['last_heartbeat']->diffForHumans() : 'nie' }}
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-ui-page>
