@php
    $statusBadge = [
        'pending' => ['warning', 'offen'],
        'applied' => ['success', 'übernommen'],
        'stale' => ['info', 'veraltet'],
        'rejected' => ['danger', 'abgelehnt'],
        'cancelled' => ['neutral', 'zurückgezogen'],
    ];
    $doc = $this->current;
    $pending = $doc ? $this->pendingFor($doc->path) : null;
@endphp

<div class="space-y-4">
    @php
        $counts = $this->ruleCounts();
        $meta = $this->rulesMeta;
        $ruleStatus = [
            'active' => ['success', 'bewährt'],
            'probation' => ['info', 'auf Probe'],
            'proposed' => ['warning', 'Vorschlag'],
            'superseded' => ['neutral', 'abgelöst'],
            'retired' => ['neutral', 'stillgelegt'],
        ];
        $pendingRuleOrders = $this->hasPendingRuleOrders();
    @endphp

    <div class="flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-[8px] border border-[color:var(--nx-line)] p-0.5 text-xs">
            <button type="button" wire:click="setView('rules')" class="rounded-[6px] px-3 py-1 {{ $view === 'rules' ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">Arbeitsweise</button>
            <button type="button" wire:click="setView('files')" class="rounded-[6px] px-3 py-1 {{ $view === 'files' ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">Notizen &amp; Dateien</button>
        </div>
        @if ($flash)
            <span class="text-xs text-[color:var(--nx-muted)]">{{ $flash }}</span>
        @endif
    </div>

    @if ($view === 'rules')
        <x-nx-panel title="Arbeitsweise" subtitle="Die Regeln, nach denen das Engram arbeitet — mit Anlass, Herkunft und Bewährung. Der Kern gilt in jedem Takt, Anlass-Regeln nur, wenn die Lage passt.">
            <x-slot name="actions">
                <span class="text-xs text-[color:var(--nx-muted)]">
                    {{ ($meta['mode'] ?? 'routine') === 'rules' ? 'Regeln führen' : 'routine.md führt noch' }}
                </span>
            </x-slot>

            <div class="space-y-3 p-4">
                @if ($counts['proposed'] > 0 && ($meta['mode'] ?? 'routine') !== 'rules')
                    <x-nx-callout variant="warning" title="Umzug-Vorschlag: {{ $counts['proposed'] }} Regeln aus routine.md">
                        <p class="m-0">Das Engram hat seine gewachsene routine.md in einzelne Regeln zerlegt. Prüfe den Vorschlag — einzelne Regeln kannst du ändern oder streichen. Erst mit „Übernehmen“ führen die Regeln; bis dahin gilt die bisherige Datei.</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            @if ($confirmImport)
                                <x-nx-input-text name="rule_note" wire:model="ruleNote" placeholder="Anmerkung (optional)" />
                                <x-nx-button :variant="$confirmImport === 'approve' ? 'primary' : 'danger'" wire:click="import('{{ $confirmImport }}')">{{ $confirmImport === 'approve' ? 'Wirklich übernehmen' : 'Wirklich verwerfen' }}</x-nx-button>
                                <x-nx-button variant="ghost" wire:click="cancelRule">Abbrechen</x-nx-button>
                            @else
                                <x-nx-button variant="primary" wire:click="import('approve')" :disabled="$pendingRuleOrders">Übernehmen</x-nx-button>
                                <x-nx-button variant="ghost" wire:click="import('reject')" :disabled="$pendingRuleOrders">Verwerfen</x-nx-button>
                                @if ($pendingRuleOrders)<span class="text-xs">Erst die offenen Aufträge abwarten.</span>@endif
                            @endif
                        </div>
                    </x-nx-callout>
                @endif

                <div class="flex flex-wrap items-center gap-2">
                    @foreach (['live' => 'Gilt', 'proposed' => 'Vorschlag', 'history' => 'Verlauf'] as $k => $l)
                        <button type="button" wire:click="setRuleFilter('{{ $k }}')" class="rounded-[6px] px-2 py-1 text-xs {{ $ruleFilter === $k ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">{{ $l }} <span class="text-[color:var(--nx-faint)]">{{ $counts[$k] }}</span></button>
                    @endforeach
                    <div class="ml-auto w-full sm:w-64">
                        <x-nx-input-text name="rule_search" wire:model.live.debounce.300ms="search" placeholder="Regeln durchsuchen" />
                    </div>
                </div>

                @forelse ($this->ruleGroups as $group => $items)
                    <div>
                        <div class="mb-1 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $group }}</div>
                        <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] rounded-[8px] border border-[color:var(--nx-line)] p-0">
                            @foreach ($items as $r)
                                @php [$bv, $bl] = $ruleStatus[$r->status] ?? ['neutral', $r->status]; if ($r->status === 'active' && $r->confirmed === 0) { $bl = 'gilt'; } $open = $this->pendingForRule($r->rule_id); @endphp
                                <li class="px-3 py-2 text-xs" wire:key="rule-{{ $r->rule_id }}">
                                    <div class="flex items-start gap-2">
                                        <span class="mt-0.5 shrink-0 font-mono text-[color:var(--nx-faint)]">r{{ $r->rule_id }}</span>
                                        <div class="min-w-0 flex-1">
                                            @if ($editingRule === $r->rule_id)
                                                <textarea wire:model="ruleDraft" rows="3" class="w-full rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-2 text-xs text-[color:var(--nx-text)] focus:outline-none"></textarea>
                                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                                    <div class="w-full sm:w-72"><x-nx-input-text name="rule_note" wire:model="ruleNote" placeholder="Warum? (das Engram sieht den Grund)" /></div>
                                                    <x-nx-button variant="primary" wire:click="submitRule">Korrektur senden</x-nx-button>
                                                    <x-nx-button variant="ghost" wire:click="cancelRule">Abbrechen</x-nx-button>
                                                </div>
                                            @else
                                                <div class="text-[color:var(--nx-text)]">@if ($r->kind === 'avoid')<span class="font-medium text-[color:var(--nx-danger)]">Nicht: </span>@endif{{ $r->text }}</div>
                                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[color:var(--nx-muted)]">
                                                    <x-nx-badge :variant="$bv">{{ $bl }}</x-nx-badge>
                                                    @if ($r->trigger !== '')<span>wenn {{ $r->trigger }}</span>@endif
                                                    @if ($r->confirmed || $r->refuted)<span title="getragen / in die Irre geführt">{{ $r->confirmed }}✓ {{ $r->refuted }}✗</span>@endif
                                                    <span>{{ ['learned' => 'selbst gelernt', 'import' => 'aus routine.md', 'curation' => 'kuratiert'][$r->source] ?? $r->source }}</span>
                                                    @if ($r->supersedes)<span>löst r{{ $r->supersedes }} ab</span>@endif
                                                    @if ($r->why)<span class="basis-full text-[color:var(--nx-faint)]">{{ $r->why }}</span>@endif
                                                </div>
                                                @if ($confirmRetireRule === $r->rule_id)
                                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                                        <div class="w-full sm:w-72"><x-nx-input-text name="rule_note" wire:model="ruleNote" placeholder="Warum stilllegen?" /></div>
                                                        <x-nx-button variant="danger" wire:click="retireRule({{ $r->rule_id }})">Wirklich stilllegen</x-nx-button>
                                                        <x-nx-button variant="ghost" wire:click="cancelRule">Abbrechen</x-nx-button>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                        @if ($editingRule !== $r->rule_id && $confirmRetireRule !== $r->rule_id && in_array($r->status, ['active', 'probation', 'proposed'], true))
                                            <div class="flex shrink-0 items-center gap-1">
                                                @if ($open)
                                                    <x-nx-badge variant="warning" dot>Auftrag</x-nx-badge>
                                                    <x-nx-button variant="ghost" wire:click="withdraw({{ $open->id }})">Zurückziehen</x-nx-button>
                                                @else
                                                    @if ($r->status === 'probation')<x-nx-button variant="ghost" wire:click="approveRule({{ $r->rule_id }})" title="als bewährt freigeben">Freigeben</x-nx-button>@endif
                                                    <x-nx-button variant="ghost" wire:click="editRule({{ $r->rule_id }})">Ändern</x-nx-button>
                                                    <x-nx-button variant="ghost" wire:click="retireRule({{ $r->rule_id }})">Stilllegen</x-nx-button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="m-0 py-4 text-center text-xs text-[color:var(--nx-muted)]">
                        @if ($this->agentRules->isEmpty())
                            Noch keine Regeln. Das Engram zerlegt seine routine.md beim nächsten Start einmal in Regeln — das Ergebnis erscheint hier als Vorschlag.
                        @else
                            Keine Regeln in dieser Ansicht.
                        @endif
                    </p>
                @endforelse
            </div>
        </x-nx-panel>
    @endif

    @if ($view === 'files')
    <x-nx-panel title="Gedächtnis" subtitle="Was das Engram über seine Arbeit gelernt hat — Arbeitsweise, Notizen, offene Fragen. Änderungen gehen als Auftrag an das Engram, nie direkt ins Vault.">
        <x-slot name="actions">
            <span class="text-xs text-[color:var(--nx-muted)]">
                @if ($mirroredAt)
                    gespiegelt {{ $mirroredAt->diffForHumans() }}
                @else
                    noch kein Spiegel
                @endif
            </span>
        </x-slot>

        @if ($this->docs->isEmpty())
            <div class="p-6 text-center text-xs text-[color:var(--nx-muted)]">
                Noch nichts gespiegelt. Der Daemon schickt sein Gedächtnis mit dem nächsten Gehirn-Push (~10 min), sobald er die Kuratier-Version läuft.
            </div>
        @else
            <div class="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)]">
                {{-- Liste --}}
                <div class="border-b border-[color:var(--nx-line)] p-3 lg:border-b-0 lg:border-r">
                    <x-nx-input-text name="memory_search" wire:model.live.debounce.300ms="search" placeholder="Suchen, z. B. terminal." />
                    <div class="mt-3 max-h-[60vh] space-y-3 overflow-y-auto">
                        @forelse ($this->groups as $label => $items)
                            <div>
                                <div class="mb-1 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $label }}</div>
                                @foreach ($items as $it)
                                    @php $d = $it['doc']; $open = $this->pendingFor($d->path); @endphp
                                    <button type="button" wire:click="select('{{ $d->path }}')"
                                        class="flex w-full items-center justify-between gap-2 rounded-[6px] px-2 py-1 text-left text-xs {{ $selected === $d->path ? 'bg-[color:var(--nx-active)] font-medium text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:bg-[color:var(--nx-hover)]' }}">
                                        <span class="truncate">{{ $d->title ?: $d->path }}</span>
                                        <span class="flex shrink-0 items-center gap-1">
                                            @if ($it['hits'] > 0)<x-nx-badge variant="accent">{{ $it['hits'] }}</x-nx-badge>@endif
                                            @if ($open)<x-nx-badge variant="warning" dot>Auftrag</x-nx-badge>@endif
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @empty
                            <p class="m-0 text-xs text-[color:var(--nx-faint)]">Kein Treffer.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Inhalt --}}
                <div class="min-w-0 p-4">
                    @if ($doc)
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-[color:var(--nx-text)]">{{ $doc->title ?: $doc->path }}</div>
                                <div class="text-xs text-[color:var(--nx-muted)]">{{ $doc->path }} · {{ substr_count($doc->content, "\n") + 1 }} Zeilen
                                    @if ($doc->file_modified_at) · zuletzt vom Engram geändert {{ $doc->file_modified_at->diffForHumans() }}@endif
                                </div>
                            </div>
                            @unless ($editing)
                                <div class="flex items-center gap-2">
                                    @if ($pending)
                                        <x-nx-badge variant="warning" dot>Auftrag offen</x-nx-badge>
                                        <x-nx-button variant="ghost" wire:click="withdraw({{ $pending->id }})">Zurückziehen</x-nx-button>
                                    @else
                                        @if ($doc->kind === 'routine' && ($meta['mode'] ?? 'routine') === 'rules')
                                            <span class="text-xs text-[color:var(--nx-muted)]">erzeugt aus den Regeln — dort ändern</span>
                                        @else
                                            <x-nx-button wire:click="edit">Bearbeiten</x-nx-button>
                                        @endif
                                        @if ($doc->kind === 'playbook')
                                            <x-nx-button :variant="$confirmRetire ? 'danger' : 'ghost'" wire:click="retire">{{ $confirmRetire ? 'Wirklich stilllegen' : 'Stilllegen' }}</x-nx-button>
                                        @endif
                                    @endif
                                </div>
                            @endunless
                        </div>


                        @if ($confirmRetire && ! $editing)
                            <div class="mb-3 space-y-2">
                                <p class="m-0 text-xs text-[color:var(--nx-muted)]">Die Notiz wird im Vault archiviert (nicht gelöscht) und aus dem Index genommen. Warum? Das Engram sieht den Grund.</p>
                                <x-nx-input-text name="memory_note" wire:model="note" placeholder="z. B. Plattform-Chat abgeschaltet" />
                                <x-nx-button variant="ghost" wire:click="cancelEdit">Abbrechen</x-nx-button>
                            </div>
                        @endif

                        @if ($editing)
                            <div class="space-y-2">
                                <textarea wire:model="draft" rows="24" spellcheck="false"
                                    class="w-full rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-3 font-mono text-xs leading-relaxed text-[color:var(--nx-text)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent,#2563eb)]"></textarea>
                                <x-nx-input-text name="memory_note" wire:model="note" placeholder="Warum? (das Engram sieht den Grund)" />
                                <div class="flex items-center gap-2">
                                    <x-nx-button variant="primary" wire:click="submitEdit">Als Auftrag senden</x-nx-button>
                                    <x-nx-button variant="ghost" wire:click="cancelEdit">Abbrechen</x-nx-button>
                                    <span class="text-xs text-[color:var(--nx-faint)]">Greift nur, wenn das Engram die Datei bis dahin nicht selbst geändert hat.</span>
                                </div>
                            </div>
                        @else
                            <pre class="max-h-[60vh] overflow-auto whitespace-pre-wrap break-words rounded-[6px] bg-[color:var(--nx-hover)] p-3 font-mono text-xs leading-relaxed text-[color:var(--nx-text)]">{{ $doc->content }}</pre>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </x-nx-panel>
    @endif

    @if ($this->curations->isNotEmpty())
        <x-nx-panel title="Kuratier-Aufträge" subtitle="Was an das Engram ging und was daraus wurde" flush>
            <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                @foreach ($this->curations as $c)
                    @php [$v, $l] = $statusBadge[$c->status] ?? ['neutral', $c->status]; @endphp
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 text-xs">
                        <x-nx-badge :variant="$v">{{ $l }}</x-nx-badge>
                        <span class="font-medium text-[color:var(--nx-text)]">{{ ['retire' => 'stilllegen', 'replace' => 'ändern', 'revise' => 'korrigieren', 'approve' => 'freigeben', 'approve_import' => 'Umzug übernehmen', 'reject_import' => 'Umzug verwerfen'][$c->op] ?? $c->op }}</span>
                        <span class="text-[color:var(--nx-muted)]">{{ str_starts_with($c->path, 'rules/') ? 'r' . substr($c->path, 6) : ($c->path === 'rules' ? 'Arbeitsweise' : $c->path) }}</span>
                        @if ($c->note)<span class="text-[color:var(--nx-muted)]">— {{ $c->note }}</span>@endif
                        <span class="ml-auto text-[color:var(--nx-faint)]">{{ $c->created_at->diffForHumans() }}</span>
                        @if ($c->result && $c->status !== 'applied')<span class="basis-full text-[color:var(--nx-muted)]">{{ $c->result }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </x-nx-panel>
    @endif
</div>
