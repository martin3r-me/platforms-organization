@php
    $snap = $profile?->brain_snapshot;
    $cal = is_array($snap) ? ($snap['calibration'] ?? null) : null;
    $bud = is_array($snap) ? ($snap['budget'] ?? null) : null;
    $usage = is_array($snap) ? ($snap['usage'] ?? null) : null;

    $gap = $cal ? (float) ($cal['gap'] ?? 0) : 0.0;
    [$calLabel, $calColor] = match (true) {
        $gap > 0.05 => ['überkonfident', 'var(--nx-warning)'],
        $gap < -0.05 => ['zu vorsichtig', 'var(--nx-info)'],
        default => ['kalibriert', 'var(--nx-success)'],
    };

    $sym = ['claimed' => '▶', 'sync' => '⇅', 'read' => '✎', 'edit' => '✎', 'write' => '✎', 'shell' => '$', 'tool' => '∙', 'text' => '…', 'commit' => '⌥', 'push' => '⇡', 'done' => '✓', 'fail' => '✗', 'ask' => '❓', 'review' => '⊙', 'learn' => '✚'];
    $logColor = fn ($kind) => match ($kind) {
        'fail' => 'text-red-400',
        'done' => 'text-green-400',
        'ask' => 'text-amber-400',
        default => 'text-neutral-400',
    };
    $online = $profile && $profile->isOnline();
@endphp

<div class="max-w-3xl space-y-4">

    {{-- Runtime-Konfiguration --}}
    <x-nx-panel title="Runtime-Konfiguration" subtitle="Was der Agent tut, kommt aus Job-Profil, Capabilities und Tokens — Rollen hier nur zur Info">
        <div class="space-y-4 p-4">
            <div>
                <div class="mb-1 text-xs font-medium text-[color:var(--nx-text)]">Rollen</div>
                @if (count($roles))
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($roles as $r)
                            <x-nx-badge>{{ $r }}</x-nx-badge>
                        @endforeach
                    </div>
                @else
                    <p class="m-0 text-xs text-[color:var(--nx-faint)]">Keine Rolle zugewiesen.</p>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <x-nx-input-number name="five_hour_reserve_pct" label="5h-Reserve %" wire:model="five_hour_reserve_pct" min="0" max="100" />
                <x-nx-input-number name="seven_day_burn_margin_pct" label="7d-Burn-Margin %" wire:model="seven_day_burn_margin_pct" min="0" max="100" />
                <x-nx-input-text name="claude_model" label="Claude-Modell" hint="optional" wire:model="claude_model" placeholder="leer = bestes verfügbares" />
            </div>

            <div class="flex flex-col items-start gap-2">
                <x-nx-input-checkbox wire:model="claim_unassigned">
                    <span>Herrenlose Pool-Issues ziehen <span class="text-xs text-[color:var(--nx-muted)]">— aus, wenn nur explizit zugewiesene</span></span>
                </x-nx-input-checkbox>
                <x-nx-input-checkbox wire:model="active">
                    <span>Aktiv <span class="text-xs text-[color:var(--nx-muted)]">— Daemon arbeitet</span></span>
                </x-nx-input-checkbox>
            </div>

            <div class="flex items-center gap-3 border-t border-[color:var(--nx-line)] pt-3">
                <x-nx-button variant="primary" size="sm" wire:click="save">Speichern</x-nx-button>
                @if ($savedMsg)
                    <span class="text-xs text-[color:var(--nx-muted)]">{{ $savedMsg }}</span>
                @endif
            </div>
        </div>
    </x-nx-panel>

    {{-- Gehirn: der gepushte Snapshot (host-agnostisch) --}}
    <x-nx-panel title="Gehirn" :subtitle="$profile?->brain_snapshot_at ? 'Snapshot ' . $profile->brain_snapshot_at->diffForHumans() : null">
        <div class="space-y-3 p-4">
            @if (! $snap)
                <p class="m-0 text-sm text-[color:var(--nx-muted)]">Noch kein Snapshot gemeldet (kommt beim nächsten Push des Daemons, ~10 min).</p>
            @else
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ([['episodes', 'Episoden'], ['facts', 'Fakten'], ['edges', 'Kanten'], ['skills', 'Skills']] as $c)
                        <div class="rounded-[6px] bg-[color:var(--nx-bg)] px-3 py-2">
                            <div class="text-xs text-[color:var(--nx-muted)]">{{ $c[1] }}</div>
                            <div class="text-lg font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $snap[$c[0]] ?? 0 }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="space-y-1 text-sm">
                    @if ($cal && ($cal['n'] ?? 0) > 0)
                        <div>
                            <span class="text-[color:var(--nx-muted)]">Kalibrierung:</span>
                            <span class="font-semibold" style="color: {{ $calColor }}">Gap {{ sprintf('%+.2f', $gap) }} ({{ $calLabel }})</span>
                            <span class="text-[color:var(--nx-muted)]">· ECE {{ number_format($cal['ece'] ?? 0, 2) }} · Brier {{ number_format($cal['brier'] ?? 0, 2) }} · Schärfe {{ number_format($cal['resolution'] ?? 0, 2) }} · {{ $cal['n'] }} Paare</span>
                        </div>
                        @if (! empty($cal['intervened']))
                            <div class="text-[color:var(--nx-muted)]">
                                Seit Selbst-Korrektur: Gap {{ sprintf('%+.2f', $cal['before_gap'] ?? 0) }} → {{ sprintf('%+.2f', $cal['after_gap'] ?? 0) }}
                                @if (abs($cal['after_gap'] ?? 0) < abs($cal['before_gap'] ?? 0) - 0.02)
                                    <span class="text-[color:var(--nx-success)]">✓ besser</span>
                                @endif
                            </div>
                        @endif
                        @if (! empty($cal['worst_themes']))
                            <div class="text-[color:var(--nx-muted)]">
                                Schwächste Themen:
                                @foreach ($cal['worst_themes'] as $w)
                                    <span class="mr-2">{{ $w['subject'] }} ({{ number_format(($w['accuracy'] ?? 0) * 100, 0) }} %)</span>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <span class="text-[color:var(--nx-muted)]">Kalibrierung: — (noch keine Confidence-Paare)</span>
                    @endif
                </div>

                @if ($usage && ! empty($usage['ok']))
                    <div class="text-xs text-[color:var(--nx-muted)]">
                        Abo-Nutzung: 5h <span class="font-semibold text-[color:var(--nx-text)]">{{ number_format($usage['five_hour_pct'] ?? 0, 0) }} %</span>
                        · 7d <span class="font-semibold text-[color:var(--nx-text)]">{{ number_format($usage['seven_day_pct'] ?? 0, 0) }} %</span>
                    </div>
                @endif
                @if ($bud)
                    <div class="text-xs text-[color:var(--nx-muted)]">
                        Token: <span class="font-semibold text-[color:var(--nx-text)]">{{ number_format($bud['tokens_today'] ?? 0) }}</span> heute ·
                        <span class="font-semibold text-[color:var(--nx-text)]">{{ number_format($bud['tokens_7d'] ?? 0) }}</span> 7d ·
                        Burn {{ number_format($bud['burn_per_hour'] ?? 0) }}/h ·
                        Gate {{ $snap['gate_total'] ?? 0 }} ({{ $snap['gate_asks'] ?? 0 }}× gefragt)
                    </div>
                @endif
            @endif
        </div>
    </x-nx-panel>

    {{-- Live-Log: was der Agent gerade tut (vom Daemon gemeldet). Bewusst dunkles Terminal. --}}
    <div wire:poll.4s>
        <x-nx-panel title="Live-Log" subtitle="Letzte Ereignisse des jüngsten Laufs">
            <x-slot name="actions">
                @if ($online)
                    <x-nx-badge variant="success" dot>live</x-nx-badge>
                @endif
            </x-slot>
            <div class="p-4">
                @if (count($events))
                    <div class="max-h-80 space-y-0.5 overflow-y-auto rounded-[6px] bg-black/90 p-3 font-mono text-[12px] leading-relaxed text-neutral-100">
                        @foreach ($events as $e)
                            <div class="flex gap-2">
                                <span class="shrink-0 text-neutral-500">{{ optional($e->created_at)->format('H:i:s') ?? '' }}</span>
                                <span class="shrink-0 {{ $logColor($e->kind) }}">{{ $sym[$e->kind] ?? '·' }}</span>
                                <span class="break-all {{ $e->kind === 'fail' ? 'text-red-300' : ($e->kind === 'ask' ? 'text-amber-300' : '') }}">{{ $e->text }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="m-0 text-xs text-[color:var(--nx-faint)]">Noch keine Aktivität gemeldet — sichtbar, sobald der Daemon einen Lauf startet.</p>
                @endif
            </div>
        </x-nx-panel>
    </div>

    {{-- Nächste Aufgaben: zugewiesene offene Dev-Issues / Planner-Tasks --}}
    <x-nx-panel title="Nächste Aufgaben" flush>
        @if (count($nextTasks))
            <ul class="m-0 list-none p-0">
                @foreach ($nextTasks as $t)
                    <li class="flex items-start gap-2 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm last:border-0">
                        <x-nx-badge :variant="($t['type'] ?? '') === 'bug' ? 'danger' : 'info'" class="shrink-0">{{ $t['board'] ?? '—' }}</x-nx-badge>
                        <span class="break-words text-[color:var(--nx-text)]">{{ $t['title'] }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <x-nx-empty icon="heroicon-o-queue-list">Keine offenen Aufgaben zugewiesen.</x-nx-empty>
        @endif
    </x-nx-panel>

    {{-- Plattform-Zugang: Token inline minten --}}
    <x-nx-panel title="Plattform-Zugang" subtitle="First-party — die Plattform stellt den API-Token für den Bot-User aus (kein Login-Flow)">
        <div class="space-y-3 p-4">
            @if ($mintedToken)
                <x-nx-callout variant="warning" title="Nur jetzt sichtbar">
                    Kopiere ihn in die VM-ENV (WORKER_TOKEN). Danach liegt nur noch der Hash in der Datenbank.
                </x-nx-callout>
                <code class="block select-all break-all rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-2 text-xs">{{ $mintedToken }}</code>
                <x-nx-button variant="ghost" size="sm" wire:click="dismissToken">Verstanden, ausblenden</x-nx-button>
            @else
                <x-nx-button size="sm" wire:click="mintToken">
                    @svg('heroicon-o-key', 'w-4 h-4')
                    <span>Plattform-Token generieren</span>
                </x-nx-button>
            @endif
        </div>
    </x-nx-panel>

    {{-- Status & externe Konten (Daemon-gemeldet, read-only) --}}
    <x-nx-panel title="Status & externe Konten">
        <div class="p-2">
            @if ($profile)
                <x-nx-property-row label="Zustand">
                    {{ $profile->status ?? '—' }}
                    <x-nx-badge :variant="$online ? 'success' : 'neutral'" dot class="ml-1">{{ $online ? 'online' : 'offline' }}</x-nx-badge>
                </x-nx-property-row>
                <x-nx-property-row label="Claude-Abo">{{ $profile->claude_subscription ?? '—' }}</x-nx-property-row>
                <x-nx-property-row label="5h / 7d Usage">{{ $profile->five_hour_pct ?? '—' }} % / {{ $profile->seven_day_pct ?? '—' }} %</x-nx-property-row>
                <x-nx-property-row label="GitHub">{{ $profile->github_username ?? '—' }}</x-nx-property-row>
                <x-nx-property-row label="Letzter Heartbeat">{{ optional($profile->last_heartbeat_at)->diffForHumans() ?? '—' }}</x-nx-property-row>
            @else
                <p class="m-0 px-2 py-1.5 text-xs text-[color:var(--nx-faint)]">Noch kein Status — der Daemon hat sich noch nicht gemeldet (erst speichern, dann Client starten).</p>
            @endif
            @unless ($linkedUser)
                <div class="px-2 pt-2">
                    <x-nx-callout variant="warning">Kein Bot-User verknüpft — ordne der Agent-Entity zuerst einen eigenen User zu, dann lässt sich der Token minten.</x-nx-callout>
                </div>
            @endunless
        </div>
    </x-nx-panel>
</div>
