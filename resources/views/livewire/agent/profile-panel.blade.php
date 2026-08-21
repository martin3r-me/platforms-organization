<div class="space-y-6 max-w-2xl">

    {{-- Runtime-Config --}}
    <div class="rounded-lg border border-[var(--ui-border)] p-5 space-y-4">
        <h3 class="text-sm font-semibold text-[var(--ui-primary)]">Runtime-Konfiguration</h3>

        <div>
            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Domäne</label>
            <select wire:model="domain" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
                <option value="">– keine –</option>
                @foreach (\Platform\Organization\Models\OrganizationAgentProfile::DOMAINS as $d)
                    <option value="{{ $d }}">{{ $d }}</option>
                @endforeach
            </select>
            <p class="text-[11px] text-[var(--ui-muted)] mt-1">operativ (S1): development/backoffice/helpdesk/assistant · analysis (S2–S4): signal-erzeugend.</p>
        </div>

        <div>
            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Stufen</label>
            <div class="flex flex-wrap gap-3">
                @foreach ($availableStages as $s)
                    <label class="inline-flex items-center gap-1.5 text-sm">
                        <input type="checkbox" wire:model="stages" value="{{ $s }}" class="rounded border-[var(--ui-border)]">
                        {{ $s }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">5h-Reserve %</label>
                <input type="number" min="0" max="100" wire:model="five_hour_reserve_pct" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">7d-Burn-Margin %</label>
                <input type="number" min="0" max="100" wire:model="seven_day_burn_margin_pct" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">GitHub-User (Referenz, kein Token)</label>
            <input type="text" wire:model="github_username" placeholder="z. B. bumblebee-bhgdigital" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
        </div>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="active" class="rounded border-[var(--ui-border)]">
            Aktiv (Daemon arbeitet)
        </label>

        <div class="flex items-center gap-3">
            <button wire:click="save" class="px-3 py-1.5 rounded-md bg-[var(--ui-primary)] text-white text-sm font-medium">Speichern</button>
            @if ($savedMsg)<span class="text-xs text-[var(--ui-muted)]">{{ $savedMsg }}</span>@endif
        </div>
    </div>

    {{-- Plattform-Zugang: Token inline minten --}}
    <div class="rounded-lg border border-[var(--ui-border)] p-5 space-y-3">
        <h3 class="text-sm font-semibold text-[var(--ui-primary)]">Plattform-Zugang</h3>
        @if ($mintedToken)
            <div class="rounded-md border border-amber-400/50 bg-amber-50/50 p-3 space-y-2">
                <p class="text-xs text-amber-800 font-medium">Nur JETZT sichtbar — kopiere ihn in die VM-ENV (WORKER_TOKEN). Danach nur noch der Hash.</p>
                <code class="block break-all text-xs bg-white/60 p-2 rounded select-all">{{ $mintedToken }}</code>
                <button wire:click="dismissToken" class="text-xs underline text-[var(--ui-muted)]">verstanden, ausblenden</button>
            </div>
        @else
            <p class="text-[11px] text-[var(--ui-muted)]">First-party — die Plattform stellt den API-Token für den Bot-User aus (kein Login-Flow).</p>
            <button wire:click="mintToken" class="px-3 py-1.5 rounded-md border border-[var(--ui-border)] text-sm">Plattform-Token generieren</button>
        @endif
    </div>

    {{-- Externe Konten + Status (Daemon-gemeldet, read-only) --}}
    <div class="rounded-lg border border-[var(--ui-border)] p-5 space-y-2 text-sm">
        <h3 class="text-sm font-semibold text-[var(--ui-primary)]">Status &amp; externe Konten</h3>
        @if ($profile)
            <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-[13px]">
                <dt class="text-[var(--ui-muted)]">Zustand</dt>
                <dd>{{ $profile->status ?? '—' }} @if($profile->isOnline())<span class="text-green-600">● online</span>@else<span class="text-[var(--ui-muted)]">○ offline</span>@endif</dd>
                <dt class="text-[var(--ui-muted)]">Claude-Abo</dt><dd>{{ $profile->claude_subscription ?? '—' }}</dd>
                <dt class="text-[var(--ui-muted)]">5h / 7d Usage</dt><dd>{{ $profile->five_hour_pct ?? '—' }}% / {{ $profile->seven_day_pct ?? '—' }}%</dd>
                <dt class="text-[var(--ui-muted)]">GitHub</dt><dd>{{ $profile->github_username ?? '—' }}</dd>
                <dt class="text-[var(--ui-muted)]">letztes Heartbeat</dt><dd>{{ optional($profile->last_heartbeat_at)->diffForHumans() ?? '—' }}</dd>
            </dl>
        @else
            <p class="text-[11px] text-[var(--ui-muted)]">Noch kein Status — der Daemon hat sich noch nicht gemeldet (erst speichern + Client starten).</p>
        @endif
        @unless ($linkedUser)
            <p class="text-[11px] text-amber-700">Kein Bot-User verknüpft — ordne der Agent-Entity zuerst einen eigenen User zu, dann lässt sich der Token minten.</p>
        @endunless
    </div>
</div>
