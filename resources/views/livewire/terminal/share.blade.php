{{-- Freigabe-Reiter im Kontext-Panel: Knoten nach außen teilen (Kunden-Tor) --}}
@php
    $share = $this->share;
    $fieldClass = 'w-full px-2 py-1.5 text-[12px] rounded border border-[var(--t-border)]/60 bg-transparent text-[var(--t-text)] placeholder:text-[var(--t-text-muted)]/50 focus:outline-none focus:ring-1 focus:ring-[var(--t-accent)]';
    $labelClass = 'text-[10px] font-semibold uppercase tracking-wider text-[var(--t-text-muted)]';
    $ago = fn ($d) => $d ? $d->copy()->locale('de')->diffForHumans() : null;
@endphp
<div class="flex flex-col h-full text-[var(--t-text)]">
    <div class="px-3 py-2 border-b border-white/10 text-[11px] text-[var(--t-text-muted)]">
        Nach außen teilen – mit allem, was an
        <span class="text-[var(--t-text)] font-medium">{{ $contextSubject ?? 'diesem Knoten' }}</span> hängt.
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-4">
        @if($notice)
            <div class="px-2 py-1.5 rounded text-[11px] bg-[var(--t-accent)]/10 text-[var(--t-text)]">{{ $notice }}</div>
        @endif

        @if(! $share)
            <div class="space-y-2">
                <p class="text-[12px] leading-relaxed text-[var(--t-text-muted)]">
                    Eine Freigabe macht aus diesem Knoten eine Seite für Kunden: Fortschritt der Projekte und was sonst
                    daran hängt – immer aktuell, ohne Anmeldung. Wer sie sieht und was darauf steht, stellst du danach ein.
                </p>
                @if($this->canWrite)
                    <button type="button" wire:click="create" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded text-[12px] font-medium bg-[var(--t-accent)] text-white hover:opacity-90 transition">
                        @svg('heroicon-o-share', 'w-3.5 h-3.5')
                        Freigabe erstellen
                    </button>
                @else
                    <p class="text-[11px] text-[var(--t-text-muted)]">Freigeben kann, wer diesen Knoten bearbeiten darf.</p>
                @endif
            </div>
        @else
            @if($this->shares->count() > 1)
                <div class="flex flex-wrap gap-1">
                    @foreach($this->shares as $s)
                        <button type="button" wire:click="select({{ $s->id }})"
                                class="px-2 py-1 rounded text-[11px] transition {{ $s->id === $share->id ? 'bg-[var(--t-accent)]/10 font-medium' : 'bg-white/5 hover:bg-white/10' }}">
                            {{ $s->title ?: 'Freigabe ' . $loop->iteration }}
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Link --}}
            <section class="space-y-1.5" x-data="{ copied: false }">
                <div class="{{ $labelClass }}">Link</div>
                <div class="flex items-center gap-1.5">
                    <input type="text" readonly value="{{ $share->url() }}" x-ref="url" x-on:focus="$el.select()" class="{{ $fieldClass }} min-w-0 flex-1 font-mono">
                    <button type="button" title="Link kopieren"
                            x-on:click="navigator.clipboard.writeText($refs.url.value); copied = true; setTimeout(() => copied = false, 1500)"
                            class="shrink-0 p-1.5 rounded bg-white/5 hover:bg-white/10 transition">
                        <span x-show="!copied">@svg('heroicon-o-clipboard-document', 'w-3.5 h-3.5')</span>
                        <span x-show="copied" x-cloak>@svg('heroicon-o-check', 'w-3.5 h-3.5')</span>
                    </button>
                    <a href="{{ $share->url() }}" target="_blank" rel="noopener" title="Kundenseite ansehen"
                       class="shrink-0 p-1.5 rounded bg-white/5 hover:bg-white/10 transition">
                        @svg('heroicon-o-arrow-top-right-on-square', 'w-3.5 h-3.5')
                    </a>
                </div>
                <div class="text-[10px] text-[var(--t-text-muted)]">
                    @if($share->view_count)
                        {{ $share->view_count }}× angesehen · zuletzt {{ $ago($share->last_viewed_at) }}
                    @else
                        Noch nicht angesehen
                    @endif
                </div>
            </section>

            {{-- Zugang --}}
            <section class="space-y-1.5">
                <div class="{{ $labelClass }}">Wer sieht die Seite?</div>
                <div class="grid grid-cols-2 gap-1">
                    @foreach(['link' => 'Jeder mit dem Link', 'invite' => 'Nur Eingeladene'] as $value => $label)
                        <label class="flex items-center justify-center px-2 py-1.5 rounded text-[11px] cursor-pointer transition {{ $access === $value ? 'bg-[var(--t-accent)]/10 font-medium' : 'bg-white/5 hover:bg-white/10' }}">
                            <input type="radio" wire:model.live="access" value="{{ $value }}" class="sr-only" @disabled(! $this->canWrite)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="text-[10px] text-[var(--t-text-muted)]">
                    {{ $access === 'invite'
                        ? 'Der Link oben führt zu „Zugang anfordern". Rein kommt nur, wer eingeladen ist – mit seinem persönlichen Link.'
                        : 'Jeder mit dem Link sieht die Seite. Eingeladene bekommen zusätzlich einen persönlichen Link.' }}
                </p>
            </section>

            {{-- Eingeladene --}}
            <section class="space-y-1.5">
                <div class="{{ $labelClass }}">Eingeladen</div>
                @if($share->recipients->isNotEmpty())
                    <ul class="space-y-1">
                        @foreach($share->recipients as $r)
                            <li wire:key="share-r-{{ $r->id }}" class="group flex items-center gap-2 px-2 py-1.5 rounded bg-white/5 text-[12px]">
                                <span class="flex-1 min-w-0">
                                    <span class="block truncate">{{ $r->email }}</span>
                                    <span class="block text-[10px] text-[var(--t-text-muted)]">
                                        {{ $r->last_viewed_at ? 'zuletzt da ' . $ago($r->last_viewed_at) : ($r->invited_at ? 'eingeladen ' . $ago($r->invited_at) : 'noch nicht eingeladen') }}
                                    </span>
                                </span>
                                @if($this->canWrite)
                                    <button type="button" wire:click="resend({{ $r->id }})" title="Link erneut schicken"
                                            class="shrink-0 p-0.5 rounded text-[var(--t-text-muted)] opacity-0 group-hover:opacity-100 hover:text-[var(--t-text)] transition">
                                        @svg('heroicon-o-paper-airplane', 'w-3.5 h-3.5')
                                    </button>
                                    <button type="button" wire:click="removeRecipient({{ $r->id }})" title="Zugang entziehen"
                                            class="shrink-0 p-0.5 rounded text-[var(--t-text-muted)] opacity-0 group-hover:opacity-100 hover:text-[var(--t-text)] transition">
                                        @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if($this->canWrite)
                    <form wire:submit="invite" class="flex items-center gap-1.5">
                        <input type="text" wire:model="inviteEmails" placeholder="name@kunde.de, …" class="{{ $fieldClass }} min-w-0 flex-1">
                        <button type="submit" wire:loading.attr="disabled"
                                class="shrink-0 inline-flex items-center gap-1 px-2 py-1.5 rounded text-[11px] font-medium bg-white/5 hover:bg-white/10 transition">
                            @svg('heroicon-o-paper-airplane', 'w-3.5 h-3.5')
                            Einladen
                        </button>
                    </form>
                @endif
            </section>

            {{-- Was ist sichtbar --}}
            <section class="space-y-1.5">
                <div class="{{ $labelClass }}">Was ist zu sehen?</div>
                <select wire:model.live="depth" class="{{ $fieldClass }}" @disabled(! $this->canWrite)>
                    <option value="0">Nur dieser Knoten</option>
                    <option value="1">+ eine Ebene darunter</option>
                    <option value="2">+ zwei Ebenen darunter</option>
                    <option value="all">Alles darunter</option>
                </select>
                @if(count($this->availableTypes))
                    <div class="space-y-0.5">
                        @foreach($this->availableTypes as $alias => $t)
                            <label wire:key="share-t-{{ $alias }}" class="flex items-center gap-2 px-2 py-1 rounded text-[12px] cursor-pointer hover:bg-white/5">
                                <input type="checkbox" wire:model.live="types" value="{{ $alias }}" class="rounded border-[var(--t-border)]" @disabled(! $this->canWrite)>
                                @svg('heroicon-o-' . $t['icon'], 'w-3.5 h-3.5 text-[var(--t-text-muted)]')
                                <span class="flex-1 min-w-0 truncate">{{ $t['label'] }}</span>
                                @unless($t['has_view'])
                                    <span class="text-[10px] text-[var(--t-text-muted)]">nur Titel</span>
                                @endunless
                                <span class="text-[10px] text-[var(--t-text-muted)] tabular-nums">{{ $t['count'] }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-[11px] text-[var(--t-text-muted)]">Hier hängt noch nichts – die Seite zeigt dann eine freundliche „Hier entsteht gerade etwas"-Ansicht.</p>
                @endif
                @foreach($this->shareableItems as $alias => $group)
                    <div class="pt-1" wire:key="share-items-{{ $alias }}">
                        <div class="flex items-center justify-between px-2 text-[10px] text-[var(--t-text-muted)]">
                            <span>Sichtbare {{ $group['label'] }}</span>
                            <span class="tabular-nums">{{ collect($visible)->filter(fn ($k) => str_starts_with($k, $alias . ':'))->count() }} / {{ count($group['items']) }}</span>
                        </div>
                        <div class="mt-0.5 max-h-48 overflow-y-auto space-y-0.5">
                            @foreach($group['items'] as $id => $itemTitle)
                                <label wire:key="share-i-{{ $alias }}-{{ $id }}" class="flex items-center gap-2 px-2 py-1 rounded text-[12px] cursor-pointer hover:bg-white/5">
                                    <input type="checkbox" wire:model.live="visible" value="{{ $alias }}:{{ $id }}" class="rounded border-[var(--t-border)]" @disabled(! $this->canWrite)>
                                    <span class="flex-1 min-w-0 truncate">{{ $itemTitle }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <label class="flex items-center gap-2 px-2 py-1 rounded text-[12px] cursor-pointer hover:bg-white/5">
                    <input type="checkbox" wire:model.live="details" class="rounded border-[var(--t-border)]" @disabled(! $this->canWrite)>
                    <span>Einzelne Schritte zeigen <span class="text-[10px] text-[var(--t-text-muted)]">(zuletzt erledigt / als Nächstes)</span></span>
                </label>
            </section>

            {{-- Auf der Seite --}}
            <section class="space-y-1.5">
                <div class="{{ $labelClass }}">Auf der Seite</div>
                <input type="text" wire:model.blur="title" placeholder="{{ $this->entity?->name }}" maxlength="190" class="{{ $fieldClass }}" @disabled(! $this->canWrite)>
                <textarea wire:model.blur="message" rows="3" placeholder="Begrüßung, z. B. „Hier sehen Sie jederzeit den aktuellen Stand Ihrer Projekte.“" class="{{ $fieldClass }} resize-none" @disabled(! $this->canWrite)></textarea>
                <div class="flex items-center gap-2 text-[11px] text-[var(--t-text-muted)]">
                    <span class="shrink-0">Gültig bis</span>
                    <input type="date" wire:model.live="expires" class="{{ $fieldClass }} min-w-0 flex-1" @disabled(! $this->canWrite)>
                </div>
            </section>

            @if($this->canWrite)
                <section class="flex flex-wrap gap-1.5 pt-1 border-t border-white/10">
                    <button type="button" wire:click="renewToken" wire:confirm="Neuen Link erzeugen? Der bisherige Link funktioniert dann nicht mehr (persönliche Links bleiben)."
                            class="inline-flex items-center gap-1 px-2 py-1.5 rounded text-[11px] bg-white/5 hover:bg-white/10 transition">
                        @svg('heroicon-o-arrow-path', 'w-3.5 h-3.5')
                        Neuer Link
                    </button>
                    <button type="button" wire:click="revoke" wire:confirm="Freigabe beenden? Alle Links – auch die persönlichen – sind dann sofort ungültig."
                            class="inline-flex items-center gap-1 px-2 py-1.5 rounded text-[11px] bg-white/5 hover:bg-white/10 text-red-500 transition">
                        @svg('heroicon-o-no-symbol', 'w-3.5 h-3.5')
                        Freigabe beenden
                    </button>
                    <button type="button" wire:click="create"
                            class="ml-auto inline-flex items-center gap-1 px-2 py-1.5 rounded text-[11px] bg-white/5 hover:bg-white/10 transition"
                            title="Weitere Freigabe, z. B. mit anderem Ausschnitt für andere Personen">
                        @svg('heroicon-o-plus', 'w-3.5 h-3.5')
                        Weitere
                    </button>
                </section>
            @endif
        @endif
    </div>
</div>
