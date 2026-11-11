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
                                        <x-nx-button wire:click="edit">Bearbeiten</x-nx-button>
                                        @if ($doc->kind === 'playbook')
                                            <x-nx-button :variant="$confirmRetire ? 'danger' : 'ghost'" wire:click="retire">{{ $confirmRetire ? 'Wirklich stilllegen' : 'Stilllegen' }}</x-nx-button>
                                        @endif
                                    @endif
                                </div>
                            @endunless
                        </div>

                        @if ($flash)
                            <x-nx-callout variant="success" class="mb-3">{{ $flash }}</x-nx-callout>
                        @endif

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

    @if ($this->curations->isNotEmpty())
        <x-nx-panel title="Kuratier-Aufträge" subtitle="Was an das Engram ging und was daraus wurde" flush>
            <ul class="m-0 list-none divide-y divide-[color:var(--nx-line)] p-0">
                @foreach ($this->curations as $c)
                    @php [$v, $l] = $statusBadge[$c->status] ?? ['neutral', $c->status]; @endphp
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 text-xs">
                        <x-nx-badge :variant="$v">{{ $l }}</x-nx-badge>
                        <span class="font-medium text-[color:var(--nx-text)]">{{ $c->op === 'retire' ? 'stilllegen' : 'ändern' }}</span>
                        <span class="text-[color:var(--nx-muted)]">{{ $c->path }}</span>
                        @if ($c->note)<span class="text-[color:var(--nx-muted)]">— {{ $c->note }}</span>@endif
                        <span class="ml-auto text-[color:var(--nx-faint)]">{{ $c->created_at->diffForHumans() }}</span>
                        @if ($c->result && $c->status !== 'applied')<span class="basis-full text-[color:var(--nx-muted)]">{{ $c->result }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </x-nx-panel>
    @endif
</div>
