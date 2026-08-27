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
                <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">Claude-Modell (optional)</label>
                <input type="text" wire:model="claude_model" placeholder="leer = bestes verfügbares" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
            </div>
        </div>

        {{-- Domänen-Felder: generisch aus der AgentSettingsRegistry (#810/#811) — ein Backoffice-Agent
             sieht hier keine Dev-Felder (github_username, max_story_points) und umgekehrt. --}}
        @if (count($settingsFields))
            <div class="grid grid-cols-2 gap-4">
                @foreach ($settingsFields as $f)
                    <div>
                        @if ($f['type'] === 'bool')
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="settingsValues.{{ $f['key'] }}" class="rounded border-[var(--ui-border)]">
                                {{ $f['label'] }}
                            </label>
                        @else
                            <label class="block text-xs font-medium text-[var(--ui-muted)] mb-1">{{ $f['label'] }}</label>
                            @if ($f['type'] === 'enum')
                                <select wire:model="settingsValues.{{ $f['key'] }}" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
                                    @foreach (($f['options'] ?? []) as $optValue => $optLabel)
                                        <option value="{{ $optValue }}">{{ $optLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif ($f['type'] === 'int')
                                <input type="number" wire:model="settingsValues.{{ $f['key'] }}" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
                            @else
                                <input type="text" wire:model="settingsValues.{{ $f['key'] }}" class="w-full rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
                            @endif
                        @endif
                        @if (!empty($f['help']))
                            <p class="text-[11px] text-[var(--ui-muted)] mt-1">{{ $f['help'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

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
    {{-- Gehirn: der gepushte Snapshot (host-agnostisch — egal wo der Agent läuft). --}}
    @php $snap = $profile?->brain_snapshot; @endphp
    <div class="rounded-lg border border-[var(--ui-border)] p-5 space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[var(--ui-primary)]">Gehirn</h3>
            @if ($profile?->brain_snapshot_at)
                <span class="text-[11px] text-[var(--ui-muted)]">Snapshot {{ $profile->brain_snapshot_at->diffForHumans() }}</span>
            @endif
        </div>
        @if (! $snap)
            <p class="text-sm text-[var(--ui-muted)]">Noch kein Snapshot gemeldet (kommt beim nächsten Push des Daemons, ~10 min).</p>
        @else
            <div class="grid grid-cols-4 gap-2 text-center">
                @foreach ([['episodes','Episoden'],['facts','Fakten'],['edges','Kanten'],['skills','Skills']] as $c)
                    <div class="rounded-md bg-[var(--ui-muted-5,#0001)] py-2">
                        <div class="text-lg font-bold">{{ $snap[$c[0]] ?? 0 }}</div>
                        <div class="text-[11px] text-[var(--ui-muted)]">{{ $c[1] }}</div>
                    </div>
                @endforeach
            </div>
            @php $cal = $snap['calibration'] ?? null; @endphp
            <div class="text-sm space-y-1">
                @if ($cal && ($cal['n'] ?? 0) > 0)
                    @php
                        $gap = (float) ($cal['gap'] ?? 0);
                        $lab = $gap > 0.05 ? 'überkonfident' : ($gap < -0.05 ? 'zu vorsichtig' : 'kalibriert');
                        $col = $gap > 0.05 ? 'text-amber-600' : ($gap < -0.05 ? 'text-sky-600' : 'text-emerald-600');
                    @endphp
                    <div>
                        <span class="text-[var(--ui-muted)]">Kalibrierung:</span>
                        <span class="font-semibold {{ $col }}">Gap {{ sprintf('%+.2f', $gap) }} ({{ $lab }})</span>
                        <span class="text-[var(--ui-muted)]">· ECE {{ number_format($cal['ece'] ?? 0, 2) }} · Brier {{ number_format($cal['brier'] ?? 0, 2) }} · Schärfe {{ number_format($cal['resolution'] ?? 0, 2) }} · {{ $cal['n'] }} Paare</span>
                    </div>
                    @if (! empty($cal['intervened']))
                        <div class="text-[var(--ui-muted)]">Seit Selbst-Korrektur: Gap {{ sprintf('%+.2f', $cal['before_gap'] ?? 0) }} → {{ sprintf('%+.2f', $cal['after_gap'] ?? 0) }}
                            @if (abs($cal['after_gap'] ?? 0) < abs($cal['before_gap'] ?? 0) - 0.02)<span class="text-emerald-600">✓ besser</span>@endif
                        </div>
                    @endif
                    @if (! empty($cal['worst_themes']))
                        <div class="text-[var(--ui-muted)]">Schwächste Themen:
                            @foreach ($cal['worst_themes'] as $w)
                                <span class="mr-2">{{ $w['subject'] }} ({{ number_format(($w['accuracy'] ?? 0) * 100, 0) }}%)</span>
                            @endforeach
                        </div>
                    @endif
                @else
                    <span class="text-[var(--ui-muted)]">Kalibrierung: — (noch keine Confidence-Paare)</span>
                @endif
            </div>
            @php $bud = $snap['budget'] ?? null; @endphp
            @if ($bud)
                <div class="text-xs text-[var(--ui-muted)]">
                    Token: <span class="font-semibold text-[var(--ui-text)]">{{ number_format($bud['tokens_today'] ?? 0) }}</span> heute ·
                    <span class="font-semibold text-[var(--ui-text)]">{{ number_format($bud['tokens_7d'] ?? 0) }}</span> 7d ·
                    Burn {{ number_format($bud['burn_per_hour'] ?? 0) }}/h ·
                    Gate {{ $snap['gate_total'] ?? 0 }} ({{ $snap['gate_asks'] ?? 0 }}× gefragt)
                </div>
            @endif
        @endif
    </div>

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

    {{-- Nächste Aufgaben: die dem Agenten zugewiesenen offenen Dev-Issues, in Board-Reihenfolge --}}
    <div class="rounded-lg border border-[var(--ui-border)] p-5 space-y-3" wire:poll.30s>
        <h3 class="text-sm font-semibold text-[var(--ui-primary)]">Nächste Aufgaben</h3>
        @if (count($nextTasks))
            <ul class="space-y-1.5">
                @foreach ($nextTasks as $t)
                    <li class="flex items-start gap-2 text-[13px]">
                        <span class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium {{ ($t['type'] ?? '') === 'bug' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">{{ $t['board'] ?? '—' }}</span>
                        <span class="break-words">{{ $t['title'] }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-[11px] text-[var(--ui-muted)]">Keine offenen Aufgaben zugewiesen — der Agent hat gerade nichts in seiner Queue.</p>
        @endif
    </div>

    {{-- Gelerntes: die Dev-Lektionen der Domäne (meistverstärkte zuerst) --}}
    <div class="rounded-lg border border-[var(--ui-border)] p-5 space-y-3">
        <h3 class="text-sm font-semibold text-[var(--ui-primary)]">Gelerntes</h3>

        {{-- Frage ans Gedächtnis: semantische Suche über die Lektionen der eigenen Domäne --}}
        <form wire:submit.prevent="askKnowledge" class="flex items-center gap-2">
            <input type="text" wire:model="knowledgeQuery" placeholder="Frage ans Gedächtnis stellen …" class="flex-1 rounded-md border border-[var(--ui-border)] bg-transparent px-2.5 py-1.5 text-sm">
            <button type="submit" class="px-3 py-1.5 rounded-md border border-[var(--ui-border)] text-sm shrink-0">Fragen</button>
        </form>

        @if ($knowledgeSearched)
            <div class="rounded-md bg-[var(--ui-muted-5,#0001)] p-3 space-y-2">
                @if (count($knowledgeResults))
                    <ul class="space-y-2">
                        @foreach ($knowledgeResults as $l)
                            <li class="text-[13px]">
                                <div class="flex items-center gap-2 mb-0.5">
                                    @if ($l['package'])<span class="rounded bg-[var(--ui-muted-5,#0001)] px-1.5 py-0.5 text-[10px] text-[var(--ui-muted)]">{{ $l['package'] }}</span>@endif
                                    @if (($l['reinforced'] ?? 0) > 0)<span class="text-[10px] text-[var(--ui-muted)]">×{{ $l['reinforced'] }}</span>@endif
                                </div>
                                <p class="break-words text-[var(--ui-fg)]">{{ $l['content'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-[11px] text-[var(--ui-muted)]">Keine passenden Lektionen gefunden.</p>
                @endif
            </div>
        @endif

        @if (count($learnings))
            <ul class="space-y-2">
                @foreach ($learnings as $l)
                    <li class="text-[13px]">
                        <div class="flex items-center gap-2 mb-0.5">
                            @if ($l['package'])<span class="rounded bg-[var(--ui-muted-5,#0001)] px-1.5 py-0.5 text-[10px] text-[var(--ui-muted)]">{{ $l['package'] }}</span>@endif
                            @if (($l['count'] ?? 0) > 0)<span class="text-[10px] text-[var(--ui-muted)]">×{{ $l['count'] }}</span>@endif
                        </div>
                        <p class="break-words text-[var(--ui-fg)]">{{ $l['content'] }}</p>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-[11px] text-[var(--ui-muted)]">Noch nichts gelernt — Lektionen erscheinen, sobald der Learn-Loop läuft.</p>
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
