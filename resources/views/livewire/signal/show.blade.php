@php
    $hasActions = $signal->actions->isNotEmpty();
    $offen = in_array($signal->status, ['open', 'acknowledged'], true);

    $sevVariant = fn ($s) => match ($s) { 'critical', 'algedonic' => 'danger', 'warning' => 'warning', default => 'info' };
    $sevLabel = fn ($s) => match ($s) { 'critical' => 'Kritisch', 'algedonic' => 'Algedonisch', 'warning' => 'Warnung', 'info' => 'Info', default => ucfirst((string) $s) };
    $statusVariant = fn ($s) => match ($s) { 'open' => 'warning', 'acknowledged' => 'info', 'resolved' => 'success', default => 'neutral' };
    $statusLabel = fn ($s) => match ($s) { 'open' => 'Offen', 'acknowledged' => 'Bestätigt', 'resolved' => 'Gelöst', 'dismissed' => 'Verworfen', default => ucfirst((string) $s) };
    $patternLabel = fn ($p) => match ($p) { 'threshold' => 'Schwellenwert', 'trend' => 'Trend', 'cross_dimension' => 'Kreuz-Dimension', 'ratio' => 'Verhältnis', default => $p };

    // Ein sichtbarer Button rechts: der nächste Schritt im Status, sofern keine Handlungsoptionen entscheiden.
    $primaer = match (true) {
        $hasActions => null,
        $signal->status === 'open' => ['acknowledge', 'Bestätigen', 'heroicon-o-check'],
        $signal->status === 'acknowledged' => ['resolve', 'Lösen', 'heroicon-o-check-circle'],
        default => null,
    };

    $autor = fn ($c) => match ($c->author_context) { 'system' => 'System', 'inference' => 'Inference', default => $c->user?->name ?? 'Unbekannt' };
    $autorIcon = fn ($c) => match ($c->author_context) { 'system' => 'heroicon-s-cog-6-tooth', 'inference' => 'heroicon-s-cpu-chip', default => null };
    $textarea = 'block w-full resize-none rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-3 py-2 text-sm text-[color:var(--nx-text)] placeholder:text-[color:var(--nx-faint)] focus:border-[color:var(--nx-accent)] focus:outline-none focus:ring-1 focus:ring-[color:var(--nx-accent)]';
@endphp

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$signal->definition?->name ?? 'Signal'" icon="heroicon-o-bell-alert" />
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Signal" width="w-64" :defaultOpen="true" side="left">
            <div class="flex flex-col gap-0.5 p-2">
                <x-nx-property-row icon="heroicon-o-signal" label="Status">
                    <x-nx-badge :variant="$statusVariant($signal->status)" dot>{{ $statusLabel($signal->status) }}</x-nx-badge>
                </x-nx-property-row>
                @if ($signal->snooze_until && $signal->snooze_until->isFuture())
                    <x-nx-property-row icon="heroicon-o-clock" label="Snooze">
                        <span class="inline-flex items-center gap-1">
                            bis {{ $signal->snooze_until->format('d.m.Y') }}
                            @if ($offen)
                                <button wire:click="cancelSnoozeActive" title="Snooze aufheben" class="text-[color:var(--nx-faint)] hover:text-[color:var(--nx-danger)]">
                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                </button>
                            @endif
                        </span>
                    </x-nx-property-row>
                @endif
                <x-nx-property-row icon="heroicon-o-exclamation-triangle" label="Schweregrad">
                    <x-nx-badge :variant="$sevVariant($signal->severity)">{{ $sevLabel($signal->severity) }}</x-nx-badge>
                </x-nx-property-row>
                @if ($signal->definition?->pattern_type)
                    <x-nx-property-row icon="heroicon-o-chart-bar" label="Muster">{{ $patternLabel($signal->definition->pattern_type) }}</x-nx-property-row>
                @endif
                @if ($signal->entity)
                    <x-nx-property-row icon="heroicon-o-cube" label="Einheit">
                        <a href="{{ route('organization.entities.show', $signal->entity) }}" wire:navigate class="hover:underline">{{ $signal->entity->name }}</a>
                        @if ($signal->entity->type)
                            <div class="text-xs text-[color:var(--nx-faint)]">{{ $signal->entity->type->name }}</div>
                        @endif
                    </x-nx-property-row>
                @endif
                @if ($signal->assignee)
                    <x-nx-property-row icon="heroicon-o-user" label="Zugewiesen">
                        <a href="{{ route('organization.entities.show', $signal->assignee) }}" wire:navigate class="hover:underline">{{ $signal->assignee->name }}</a>
                    </x-nx-property-row>
                @endif
                @if ($signal->definition)
                    <x-nx-property-row icon="heroicon-o-adjustments-horizontal" label="Definition">
                        <a href="{{ route('organization.settings.signal-definitions.show', $signal->definition) }}" wire:navigate class="hover:underline">{{ $signal->definition->name }}</a>
                    </x-nx-property-row>
                @endif
                <x-nx-property-row icon="heroicon-o-calendar" label="Erstellt">{{ $signal->created_at->format('d.m.Y H:i') }}</x-nx-property-row>
                @if ($signal->resolved_at)
                    <x-nx-property-row icon="heroicon-o-check-circle" label="Gelöst">{{ $signal->resolved_at->format('d.m.Y H:i') }}</x-nx-property-row>
                @endif
                @if ($signal->resolvedByUser)
                    <x-nx-property-row icon="heroicon-o-user-circle" label="Gelöst von">{{ $signal->resolvedByUser->name }}</x-nx-property-row>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organisation', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => 'Signale', 'href' => route('organization.signals.index')],
            ['label' => $signal->definition?->name ?? 'Signal'],
        ]">
            <div class="flex items-center gap-2">
                @if ($primaer)
                    <x-nx-button variant="primary" size="sm" wire:click="startAction('{{ $primaer[0] }}')">
                        @svg($primaer[2], 'w-4 h-4')
                        <span>{{ $primaer[1] }}</span>
                    </x-nx-button>
                @endif
                <x-nx-dropdown>
                    <x-nx-dropdown-item wire:click="toggleFocus">
                        @svg($this->isFocused ? 'heroicon-s-star' : 'heroicon-o-star', 'w-4 h-4')
                        {{ $this->isFocused ? 'Aus Fokus entfernen' : 'In Fokus aufnehmen' }}
                    </x-nx-dropdown-item>
                    @if ($offen)
                        <x-nx-dropdown-item wire:click="openSnooze">
                            @svg('heroicon-o-clock', 'w-4 h-4')
                            Snoozen…
                        </x-nx-dropdown-item>
                        @unless ($hasActions)
                            <x-nx-dropdown-divider />
                            <x-nx-dropdown-item variant="danger" wire:click="startAction('dismiss')">
                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                                Verwerfen…
                            </x-nx-dropdown-item>
                        @endunless
                    @endif
                </x-nx-dropdown>
            </div>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container width="contained">
        <div class="space-y-5">
            {{-- Kopf --}}
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <button wire:click="toggleFocus" type="button" title="{{ $this->isFocused ? 'Aus Fokus entfernen' : 'In Fokus aufnehmen' }}"
                        class="transition-colors {{ $this->isFocused ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-faint)] hover:text-[color:var(--nx-warning)]' }}">
                        @svg($this->isFocused ? 'heroicon-s-star' : 'heroicon-o-star', 'w-5 h-5')
                    </button>
                    <h1 class="m-0 text-2xl font-semibold tracking-tight text-[color:var(--nx-text)]">{{ $signal->definition?->name ?? 'Signal' }}</h1>
                    <x-nx-badge :variant="$sevVariant($signal->severity)">{{ $sevLabel($signal->severity) }}</x-nx-badge>
                    <x-nx-badge :variant="$statusVariant($signal->status)" dot>{{ $statusLabel($signal->status) }}</x-nx-badge>
                    <span class="ml-auto text-xs text-[color:var(--nx-faint)]" title="{{ $signal->created_at->format('d.m.Y H:i') }}">{{ $signal->created_at->diffForHumans() }}</span>
                </div>
                <p class="m-0 text-[15px] leading-relaxed text-[color:var(--nx-text)]">{{ $signal->message }}</p>
            </div>

            @if ($signal->status === 'resolved' && $signal->resolution_summary)
                <x-nx-callout variant="success" title="Was wurde getan">
                    <span class="whitespace-pre-line">{{ $signal->resolution_summary }}</span>
                    @if ($signal->resolvedByUser || $signal->resolved_at)
                        <div class="mt-1 text-xs text-[color:var(--nx-faint)]">
                            {{ $signal->resolvedByUser?->name }}@if ($signal->resolvedByUser && $signal->resolved_at) · @endif{{ $signal->resolved_at?->diffForHumans() }}
                        </div>
                    @endif
                </x-nx-callout>
            @endif

            @if ($signal->status === 'dismissed' && $signal->dismissed_reason)
                <x-nx-callout variant="neutral" icon="heroicon-o-x-circle" title="Verworfen — Begründung">
                    <span class="whitespace-pre-line">{{ $signal->dismissed_reason }}</span>
                </x-nx-callout>
            @endif

            @if ($hasActions)
                <x-nx-panel flush title="Handlungsoptionen" subtitle="Jede Option einzeln umsetzen oder verwerfen">
                    <div>
                        @foreach ($signal->actions as $action)
                            @php
                                $isActive = $activeActionId === $action->id;
                                $isPending = $action->status === 'pending';
                            @endphp
                            <div wire:key="a-{{ $action->id }}"
                                class="border-b border-[color:var(--nx-line)] px-4 py-3 last:border-0 {{ $isActive ? 'bg-[color:var(--nx-hover)]' : '' }} {{ $action->status === 'dismissed' ? 'opacity-70' : '' }}">
                                <div class="flex items-start gap-2.5">
                                    @if ($action->status === 'applied')
                                        @svg('heroicon-o-check-circle', 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-success)]')
                                    @elseif ($action->status === 'dismissed')
                                        @svg('heroicon-o-x-circle', 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                                    @else
                                        @svg('heroicon-o-light-bulb', 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-info)]')
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="text-sm font-medium text-[color:var(--nx-text)]">{{ $action->title }}</div>
                                            @if ($action->status === 'applied')
                                                <x-nx-badge variant="success">Umgesetzt</x-nx-badge>
                                            @elseif ($action->status === 'dismissed')
                                                <x-nx-badge>Verworfen</x-nx-badge>
                                            @endif
                                        </div>
                                        @if (! empty($action->description))
                                            <p class="mt-0.5 text-xs text-[color:var(--nx-muted)]">{{ $action->description }}</p>
                                        @endif
                                        @if (! $isPending && $action->decision_reason)
                                            <p class="mt-2 text-xs italic text-[color:var(--nx-text)]">„{{ $action->decision_reason }}"</p>
                                        @endif
                                        @if (! $isPending)
                                            <p class="mt-1 text-[11px] text-[color:var(--nx-faint)]">
                                                {{ $action->decidedByUser?->name ?? 'System' }}@if ($action->decided_at) · {{ $action->decided_at->diffForHumans() }}@endif
                                            </p>
                                        @endif

                                        @if ($isPending && ! $isActive)
                                            <div class="mt-3 flex items-center gap-2">
                                                <x-nx-button variant="primary" size="sm" wire:click="startActionDecision({{ $action->id }}, 'applied')">
                                                    @svg('heroicon-o-check', 'w-4 h-4')
                                                    <span>Umsetzen</span>
                                                </x-nx-button>
                                                <x-nx-button size="sm" wire:click="startActionDecision({{ $action->id }}, 'dismissed')">
                                                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                                                    <span>Verwerfen</span>
                                                </x-nx-button>
                                            </div>
                                        @elseif ($isPending && $isActive)
                                            <div class="mt-3 space-y-2">
                                                <label class="block text-xs font-medium text-[color:var(--nx-muted)]">
                                                    {{ $activeActionDecision === 'applied' ? 'Notiz zur Umsetzung (optional)' : 'Begründung für das Verwerfen' }}
                                                </label>
                                                <textarea wire:model="activeActionReason" rows="2" autofocus class="{{ $textarea }}"
                                                    placeholder="{{ $activeActionDecision === 'applied' ? 'z. B. Weekly mit Team X ist eingerichtet …' : 'z. B. Empfehlung zielt am Kontext vorbei, weil …' }}"></textarea>
                                                @error('activeActionReason')
                                                    <p class="text-xs text-[color:var(--nx-danger)]">{{ $message }}</p>
                                                @enderror
                                                <div class="flex items-center justify-end gap-2">
                                                    <x-nx-button variant="ghost" size="sm" wire:click="cancelActionDecision">Abbrechen</x-nx-button>
                                                    <x-nx-button :variant="$activeActionDecision === 'applied' ? 'primary' : 'secondary'" size="sm" wire:click="confirmActionDecision">
                                                        {{ $activeActionDecision === 'applied' ? 'Umsetzen bestätigen' : 'Verwerfen bestätigen' }}
                                                    </x-nx-button>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-nx-panel>
            @endif

            @if ($signal->trigger_metrics && count($signal->trigger_metrics) > 0)
                <x-nx-panel title="Auslösende Werte">
                    <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                        @foreach ($signal->trigger_metrics as $key => $value)
                            <div class="min-w-0">
                                <div class="truncate text-xs text-[color:var(--nx-muted)]">{{ ucfirst(str_replace('_', ' ', $key)) }}</div>
                                <div class="mt-0.5 break-words text-sm font-medium tabular-nums text-[color:var(--nx-text)]">{{ is_array($value) ? json_encode($value) : $value }}</div>
                            </div>
                        @endforeach
                    </div>
                </x-nx-panel>
            @endif

            {{-- Kommentare --}}
            <x-nx-panel title="Kommentare" :subtitle="$this->signalComments->isNotEmpty() ? $this->signalComments->count() . ' Beiträge' : null">
                <div class="space-y-4">
                    @foreach ($this->signalComments as $comment)
                        <div wire:key="c-{{ $comment->id }}" class="flex gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-[6px] bg-[color:var(--nx-accent-soft)] text-[10px] font-medium text-[color:var(--nx-muted)]">
                                @if ($autorIcon($comment))
                                    @svg($autorIcon($comment), 'w-3 h-3')
                                @else
                                    {{ mb_strtoupper(mb_substr($comment->user?->name ?? '?', 0, 1)) }}
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 text-xs">
                                    <span class="font-semibold text-[color:var(--nx-text)]">{{ $autor($comment) }}</span>
                                    <span class="text-[color:var(--nx-faint)]">· {{ $comment->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="mt-0.5 text-sm leading-relaxed text-[color:var(--nx-text)]">{!! nl2br(e($comment->content)) !!}</div>

                                @if ($comment->replies->isNotEmpty())
                                    <div class="mt-2 space-y-2 border-l-2 border-[color:var(--nx-line)] pl-3">
                                        @foreach ($comment->replies as $reply)
                                            <div>
                                                <div class="flex items-center gap-1.5 text-xs">
                                                    @if ($autorIcon($reply))
                                                        @svg($autorIcon($reply), 'w-3 h-3 text-[color:var(--nx-faint)]')
                                                    @endif
                                                    <span class="font-medium text-[color:var(--nx-text)]">{{ $autor($reply) }}</span>
                                                    <span class="text-[color:var(--nx-faint)]">· {{ $reply->created_at->diffForHumans() }}</span>
                                                </div>
                                                <div class="mt-0.5 text-sm text-[color:var(--nx-text)]">{!! nl2br(e($reply->content)) !!}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($comment->author_context !== 'system')
                                    @if ($replyingTo === $comment->id)
                                        <div class="mt-2 space-y-2">
                                            <textarea wire:model="replyBody" wire:keydown.ctrl.enter="submitReply" rows="2" autofocus class="{{ $textarea }}" placeholder="Antwort schreiben…"></textarea>
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] text-[color:var(--nx-faint)]">Strg+Enter</span>
                                                <div class="flex items-center gap-2">
                                                    <x-nx-button variant="ghost" size="sm" wire:click="cancelReply">Abbrechen</x-nx-button>
                                                    <x-nx-button variant="primary" size="sm" wire:click="submitReply">Antworten</x-nx-button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <button wire:click="startReply({{ $comment->id }})" class="mt-1 inline-flex items-center gap-1 text-xs text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)]">
                                            @svg('heroicon-o-chat-bubble-left', 'w-3 h-3')
                                            Antworten
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div @class(['space-y-2', 'border-t border-[color:var(--nx-line)] pt-4' => $this->signalComments->isNotEmpty()])>
                        <textarea wire:model="newComment" wire:keydown.ctrl.enter="addComment" rows="2" class="{{ $textarea }}" placeholder="Kommentar schreiben…"></textarea>
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] text-[color:var(--nx-faint)]">Strg+Enter</span>
                            <x-nx-button size="sm" wire:click="addComment">Kommentieren</x-nx-button>
                        </div>
                    </div>
                </div>
            </x-nx-panel>

            {{-- Verlauf: vorher rechte Aktivitäten-Sidebar --}}
            <x-nx-panel title="Verlauf" subtitle="Änderungen und Notizen">
                <div class="space-y-3">
                    <form wire:submit="addNote" class="flex items-end gap-2">
                        <textarea wire:model="newNote" rows="1" class="{{ $textarea }}" placeholder="Notiz hinzufügen…"></textarea>
                        <x-nx-button type="submit" size="sm">Notiz</x-nx-button>
                    </form>
                    @forelse ($this->signalActivities as $activity)
                        @php
                            $actIcon = match (true) {
                                $activity->activity_type === 'manual' => 'heroicon-o-pencil',
                                $activity->name === 'created' => 'heroicon-o-plus',
                                $activity->name === 'deleted' => 'heroicon-o-trash',
                                default => 'heroicon-o-pencil-square',
                            };
                        @endphp
                        <div class="flex gap-2.5 text-sm">
                            @svg($actIcon, 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                            <div class="min-w-0 flex-1">
                                @if ($activity->activity_type === 'manual')
                                    <p class="m-0 text-[color:var(--nx-text)]">{{ $activity->message }}</p>
                                @elseif ($activity->name === 'created')
                                    <p class="m-0 text-[color:var(--nx-muted)]">Signal erstellt</p>
                                @elseif ($activity->name === 'updated' && is_array($activity->properties))
                                    <p class="m-0 text-[color:var(--nx-muted)]">{{ collect($activity->properties)->keys()->map(fn ($k) => ucfirst(str_replace('_', ' ', $k)))->implode(', ') }} geändert</p>
                                @elseif ($activity->name === 'deleted')
                                    <p class="m-0 text-[color:var(--nx-muted)]">Signal gelöscht</p>
                                @endif
                                <div class="text-[11px] text-[color:var(--nx-faint)]">{{ $activity->user?->name ?? 'System' }} · {{ $activity->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="m-0 text-sm text-[color:var(--nx-faint)]">Noch keine Einträge.</p>
                    @endforelse
                </div>
            </x-nx-panel>

            {{-- Gleiche Definition, gleiche Einheit --}}
            <x-nx-panel flush title="Frühere Signale dieser Art">
                @if ($this->historicalSignals->isEmpty())
                    <x-nx-empty icon="heroicon-o-bell-slash">Erstes Signal dieser Art.</x-nx-empty>
                @else
                    <div>
                        @foreach ($this->historicalSignals as $hist)
                            <a href="{{ route('organization.signals.show', $hist) }}" wire:navigate
                                class="flex items-center gap-3 border-b border-[color:var(--nx-line)] px-4 py-2 text-sm last:border-0 hover:bg-[color:var(--nx-hover)]">
                                <span class="w-24 shrink-0 tabular-nums text-[color:var(--nx-muted)]">{{ $hist->created_at->format('d.m.Y') }}</span>
                                <x-nx-badge :variant="$sevVariant($hist->severity)">{{ $sevLabel($hist->severity) }}</x-nx-badge>
                                <x-nx-badge :variant="$statusVariant($hist->status)" dot>{{ $statusLabel($hist->status) }}</x-nx-badge>
                                <span class="min-w-0 flex-1 truncate text-[color:var(--nx-muted)]">{{ $hist->message }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-nx-panel>
        </div>
    </x-ui-page-container>

    {{-- Begründung für Bestätigen / Lösen / Verwerfen --}}
    @if ($pendingAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="cancelAction">
            <div class="w-full max-w-md rounded-[10px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-5 shadow-xl">
                <h3 class="m-0 text-base font-semibold text-[color:var(--nx-text)]">
                    {{ match ($pendingAction) { 'acknowledge' => 'Signal bestätigen', 'resolve' => 'Signal lösen', 'dismiss' => 'Signal verwerfen', default => 'Signal' } }}
                </h3>
                <p class="mb-3 mt-1 text-sm text-[color:var(--nx-muted)]">
                    {{ match ($pendingAction) {
                        'acknowledge' => 'Was macht dieses Signal relevant?',
                        'resolve' => 'Was wurde unternommen? Bleibt am Signal sichtbar — Stichpunkte sind ok.',
                        'dismiss' => 'Warum ist dieses Signal nicht relevant?',
                        default => '',
                    } }}
                </p>
                <textarea wire:model="actionReason" rows="{{ $pendingAction === 'resolve' ? 5 : 3 }}" autofocus class="{{ $textarea }}"
                    placeholder="{{ match ($pendingAction) { 'acknowledge' => 'z. B. Bestätigt, prüfen wir im nächsten Weekly …', 'resolve' => "z. B.\n• Interlink zu Team B angelegt\n• Zuständigkeit mit C besprochen\n• Snapshot-Frequenz erhöht", 'dismiss' => 'z. B. Einheit bewusst inaktiv, kein Handlungsbedarf …', default => '' } }}"></textarea>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <x-nx-button variant="ghost" wire:click="cancelAction">Abbrechen</x-nx-button>
                    <x-nx-button :variant="$pendingAction === 'dismiss' ? 'danger' : 'primary'" wire:click="confirmAction">
                        {{ match ($pendingAction) { 'acknowledge' => 'Bestätigen', 'resolve' => 'Lösen', 'dismiss' => 'Verwerfen', default => 'OK' } }}
                    </x-nx-button>
                </div>
            </div>
        </div>
    @endif

    {{-- Snooze --}}
    @if ($showSnoozeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="cancelSnooze">
            <div class="w-full max-w-sm rounded-[10px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] p-5 shadow-xl">
                <h3 class="m-0 text-base font-semibold text-[color:var(--nx-text)]">Signal snoozen</h3>
                <p class="mb-3 mt-1 text-sm text-[color:var(--nx-muted)]">Bis zum gewählten Zeitpunkt aus der Übersicht ausgeblendet.</p>
                <div class="flex flex-col gap-1">
                    @foreach (['1d' => '1 Tag', '3d' => '3 Tage', '1w' => '1 Woche', '2w' => '2 Wochen', '1m' => '1 Monat', 'custom' => 'Benutzerdefiniert'] as $value => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-[6px] border px-3 py-2 text-sm text-[color:var(--nx-text)] transition-colors {{ $snoozeDuration === $value ? 'border-[color:var(--nx-accent)] bg-[color:var(--nx-hover)]' : 'border-[color:var(--nx-line)] hover:bg-[color:var(--nx-hover)]' }}">
                            <input type="radio" wire:model.live="snoozeDuration" value="{{ $value }}" class="accent-[var(--nx-accent)]">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @if ($snoozeDuration === 'custom')
                    <div class="mt-3">
                        <x-nx-input-date name="snoozeCustomDate" size="sm" wire:model="snoozeCustomDate" :min="now()->addDay()->format('Y-m-d')" />
                    </div>
                @endif
                <div class="mt-4 flex items-center justify-end gap-2">
                    <x-nx-button variant="ghost" wire:click="cancelSnooze">Abbrechen</x-nx-button>
                    <x-nx-button variant="primary" wire:click="confirmSnooze" :disabled="$snoozeDuration === 'custom' && ! $snoozeCustomDate">
                        @svg('heroicon-o-clock', 'w-4 h-4')
                        <span>Snoozen</span>
                    </x-nx-button>
                </div>
            </div>
        </div>
    @endif
</x-ui-page>
