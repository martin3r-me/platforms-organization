<div class="space-y-6 max-w-2xl">

    {{-- Runtime-Config --}}
    <div class="rounded-lg border border-[var(--ui-border)] p-5 space-y-4">
        <h3 class="text-sm font-semibold text-[var(--ui-primary)]">Runtime-Konfiguration</h3>

        <div>
            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Rollen (Domäne · Stufe)</label>
            @if (count($roles))
                <ul class="text-sm space-y-0.5">
                    @foreach ($roles as $r)
                        <li class="inline-flex items-center gap-1.5 mr-2 rounded bg-[var(--ui-muted-5,#0001)] px-2 py-0.5">{{ $r }}</li>
                    @endforeach
                </ul>
            @else
                <p class="text-[11px] text-amber-700">Noch keine Rolle zugewiesen — der Agent tut nichts, bis er in der Rollen-UI eine (agent-ausführbare) Rolle bekommt.</p>
            @endif
            <p class="text-[11px] text-[var(--ui-muted)] mt-1">Was der Agent TUT, kommt aus seinen Rollen — gepflegt in der Rollen-UI, wie bei jedem Mitglied. Hier nur zur Info.</p>
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
            <div>
                <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Max Story Points (Claim-Cap)</label>
                <input type="number" min="1" max="100" wire:model="max_story_points" placeholder="kein Limit" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Claude-Modell (optional)</label>
                <input type="text" wire:model="claude_model" placeholder="leer = bestes verfügbares" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">GitHub-User (Referenz, kein Token)</label>
            <input type="text" wire:model="github_username" placeholder="z. B. bumblebee-bhgdigital" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
        </div>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="claim_unassigned" class="rounded border-[var(--ui-border)]">
            Herrenlose Pool-Issues ziehen (aus, wenn nur explizit zugewiesene)
        </label>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="active" class="rounded border-[var(--ui-border)]">
            Aktiv (Daemon arbeitet)
        </label>

        <div class="flex items-center gap-3">
            <button wire:click="save" class="px-3 py-1.5 rounded-md bg-[var(--ui-primary)] text-white text-sm font-medium">Speichern</button>
            @if ($savedMsg)<span class="text-xs text-[var(--ui-muted)]">{{ $savedMsg }}</span>@endif
        </div>
    </div>

    {{-- Live-Log: was der Agent gerade tut (vom Daemon gemeldet, kein Voll-Token-Strom) --}}
    <div class="rounded-lg border border-[var(--ui-border)] p-5 space-y-3" wire:poll.4s>
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--ui-primary)]">Live-Log</h3>
            @if ($profile && $profile->isOnline())<span class="text-xs text-green-600">● live</span>@endif
        </div>
        @if (count($events))
            @php
                $sym = ['claimed'=>'▶','sync'=>'⇅','read'=>'✎','edit'=>'✎','write'=>'✎','shell'=>'$','tool'=>'∙','text'=>'…','commit'=>'⌥','push'=>'⇡','done'=>'✓','fail'=>'✗','ask'=>'❓','review'=>'⊙','learn'=>'✚'];
            @endphp
            <div class="rounded-md bg-black/90 text-gray-100 font-mono text-[12px] leading-relaxed p-3 max-h-80 overflow-y-auto space-y-0.5">
                @foreach ($events as $e)
                    <div class="flex gap-2">
                        <span class="text-gray-500 shrink-0">{{ optional($e->created_at)->format('H:i:s') ?? '' }}</span>
                        <span class="shrink-0 {{ $e->kind === 'fail' ? 'text-red-400' : ($e->kind === 'done' ? 'text-green-400' : ($e->kind === 'ask' ? 'text-amber-400' : 'text-gray-400')) }}">{{ $sym[$e->kind] ?? '·' }}</span>
                        <span class="break-all {{ $e->kind === 'fail' ? 'text-red-300' : ($e->kind === 'ask' ? 'text-amber-300' : '') }}">{{ $e->text }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-[11px] text-[var(--ui-muted)]">Noch keine Aktivität gemeldet — sichtbar, sobald der Daemon einen Lauf startet.</p>
        @endif
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
