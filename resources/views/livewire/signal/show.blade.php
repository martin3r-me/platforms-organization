<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => $signal->entity?->name ?? 'Entity', 'href' => $signal->entity ? route('organization.entities.show', $signal->entity) : '#'],
            ['label' => 'Signale', 'href' => route('organization.signals.index')],
            ['label' => $signal->definition?->name ?? 'Signal'],
        ]">
            <button
                wire:click="toggleFocus"
                type="button"
                @class([
                    'inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-sm font-medium transition',
                    'bg-amber-100 text-amber-800 hover:bg-amber-200' => $this->isFocused,
                    'bg-transparent text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-gray-100' => ! $this->isFocused,
                ])
                title="{{ $this->isFocused ? 'Aus Fokus entfernen' : 'In Fokus aufnehmen' }}"
            >
                @if($this->isFocused)
                    @svg('heroicon-s-star', 'w-4 h-4')
                    <span>Fokus</span>
                @else
                    @svg('heroicon-o-star', 'w-4 h-4')
                    <span>Fokus</span>
                @endif
            </button>
            @php($hasActions = $signal->actions->isNotEmpty())
            @if($signal->status === 'open')
                @unless($hasActions)
                    <x-ui-button variant="primary" size="sm" wire:click="startAction('acknowledge')">
                        @svg('heroicon-o-check', 'w-4 h-4')
                        <span>Bestätigen</span>
                    </x-ui-button>
                @endunless
                <x-ui-button variant="ghost" size="sm" wire:click="openSnooze">
                    @svg('heroicon-o-clock', 'w-4 h-4')
                    <span>Snooze</span>
                </x-ui-button>
                @unless($hasActions)
                    <x-ui-button variant="ghost" size="sm" wire:click="startAction('dismiss')">
                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                        <span>Verwerfen</span>
                    </x-ui-button>
                @endunless
            @elseif($signal->status === 'acknowledged')
                @unless($hasActions)
                    <x-ui-button variant="primary" size="sm" wire:click="startAction('resolve')">
                        @svg('heroicon-o-check-circle', 'w-4 h-4')
                        <span>Lösen</span>
                    </x-ui-button>
                @endunless
                <x-ui-button variant="ghost" size="sm" wire:click="openSnooze">
                    @svg('heroicon-o-clock', 'w-4 h-4')
                    <span>Snooze</span>
                </x-ui-button>
                @unless($hasActions)
                    <x-ui-button variant="ghost" size="sm" wire:click="startAction('dismiss')">
                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                        <span>Verwerfen</span>
                    </x-ui-button>
                @endunless
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Informationen" width="w-80" :defaultOpen="true" side="left">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Status</h3>
                    <div class="space-y-3">
                        <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-medium
                            @if($signal->status === 'open') bg-yellow-100 text-yellow-800
                            @elseif($signal->status === 'acknowledged') bg-blue-100 text-blue-800
                            @elseif($signal->status === 'resolved') bg-green-100 text-green-800
                            @else bg-gray-100 text-gray-600
                            @endif
                        ">
                            @switch($signal->status)
                                @case('open') Offen @break
                                @case('acknowledged') Bestätigt @break
                                @case('resolved') Gelöst @break
                                @case('dismissed') Verworfen @break
                            @endswitch
                        </span>

                        {{-- Snooze Badge --}}
                        @if($signal->snooze_until && $signal->snooze_until->isFuture())
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                    @svg('heroicon-o-clock', 'w-3.5 h-3.5')
                                    Snoozed bis {{ $signal->snooze_until->format('d.m.Y') }}
                                </span>
                                @if(in_array($signal->status, ['open', 'acknowledged']))
                                    <button wire:click="cancelSnoozeActive" class="text-xs text-[var(--ui-muted)] hover:text-red-600 transition-colors" title="Snooze aufheben">
                                        @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-3">
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Schweregrad</span>
                            <div class="mt-1">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @if($signal->severity === 'critical') bg-red-100 text-red-800
                                    @elseif($signal->severity === 'warning') bg-amber-100 text-amber-800
                                    @else bg-blue-100 text-blue-800
                                    @endif
                                ">
                                    {{ ucfirst($signal->severity) }}
                                </span>
                            </div>
                        </div>
                        @if($signal->definition?->pattern_type)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Pattern</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">
                                    @switch($signal->definition->pattern_type)
                                        @case('threshold') Schwellenwert @break
                                        @case('trend') Trend @break
                                        @case('cross_dimension') Kreuz-Dimension @break
                                        @case('ratio') Verhältnis @break
                                        @default {{ $signal->definition->pattern_type }}
                                    @endswitch
                                </div>
                            </div>
                        @endif
                        <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Erstellt</span>
                            <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $signal->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        @if($signal->resolved_at)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Gelöst</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $signal->resolved_at->format('d.m.Y H:i') }}</div>
                            </div>
                        @endif
                        @if($signal->resolvedByUser)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Gelöst von</span>
                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $signal->resolvedByUser->name }}</div>
                            </div>
                        @endif
                        @if($signal->assignee)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Zugewiesen an</span>
                                <div class="text-sm font-medium">
                                    <a href="{{ route('organization.entities.show', $signal->assignee) }}" class="text-[var(--ui-primary)] hover:underline">
                                        {{ $signal->assignee->name }}
                                    </a>
                                </div>
                            </div>
                        @endif
                        @if($signal->definition)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Definition</span>
                                <div class="text-sm font-medium">
                                    <a href="{{ route('organization.settings.signal-definitions.show', $signal->definition) }}" class="text-[var(--ui-primary)] hover:underline">
                                        {{ $signal->definition->name }}
                                    </a>
                                </div>
                            </div>
                        @endif
                        @if($signal->entity)
                            <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                <span class="text-xs text-[var(--ui-muted)]">Entity</span>
                                <div class="text-sm font-medium">
                                    <a href="{{ route('organization.entities.show', $signal->entity) }}" class="text-[var(--ui-primary)] hover:underline">
                                        {{ $signal->entity->name }}
                                    </a>
                                    @if($signal->entity->type)
                                        <div class="text-xs text-[var(--ui-muted)]">{{ $signal->entity->type->name }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="h-full flex flex-col">
                {{-- Timeline --}}
                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    @forelse($this->signalActivities as $activity)
                        <div class="flex gap-3 group">
                            <div class="flex-shrink-0 mt-0.5">
                                @if($activity->activity_type === 'manual')
                                    <div class="w-6 h-6 rounded-full bg-[var(--ui-primary-10)] flex items-center justify-center">
                                        @svg('heroicon-s-pencil', 'w-3 h-3 text-[var(--ui-primary)]')
                                    </div>
                                @elseif($activity->name === 'created')
                                    <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                                        @svg('heroicon-s-plus', 'w-3 h-3 text-green-600')
                                    </div>
                                @elseif($activity->name === 'updated')
                                    <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center">
                                        @svg('heroicon-s-pencil-square', 'w-3 h-3 text-blue-600')
                                    </div>
                                @elseif($activity->name === 'deleted')
                                    <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center">
                                        @svg('heroicon-s-trash', 'w-3 h-3 text-red-600')
                                    </div>
                                @else
                                    <div class="w-6 h-6 rounded-full bg-[var(--ui-muted-10)] flex items-center justify-center">
                                        @svg('heroicon-s-cog-6-tooth', 'w-3 h-3 text-[var(--ui-muted)]')
                                    </div>
                                @endif
                            </div>
                            <div class="flex-grow min-w-0">
                                @if($activity->activity_type === 'manual')
                                    <p class="text-sm text-[var(--ui-secondary)]">{{ $activity->message }}</p>
                                @elseif($activity->name === 'created')
                                    <p class="text-sm text-[var(--ui-muted)]">Signal erstellt</p>
                                @elseif($activity->name === 'updated' && is_array($activity->properties))
                                    <p class="text-sm text-[var(--ui-muted)]">
                                        {{ collect($activity->properties)->keys()->map(fn($k) => ucfirst(str_replace('_', ' ', $k)))->implode(', ') }} geändert
                                    </p>
                                @elseif($activity->name === 'deleted')
                                    <p class="text-sm text-[var(--ui-muted)]">Signal gelöscht</p>
                                @endif

                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[10px] text-[var(--ui-muted)]">
                                        {{ $activity->user?->name ?? 'System' }} &middot; {{ $activity->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-[var(--ui-muted)] text-center py-4">Keine Aktivitäten vorhanden.</p>
                    @endforelse
                </div>

                {{-- Notiz-Eingabe --}}
                <div class="flex-shrink-0 border-t border-[var(--ui-border)] p-3">
                    <form wire:submit="addNote" class="flex items-end gap-2">
                        <textarea
                            wire:model="newNote"
                            rows="2"
                            class="flex-1 text-sm rounded-lg border border-[var(--ui-border)] bg-[var(--ui-surface)] px-3 py-2 text-[var(--ui-secondary)] placeholder:text-[var(--ui-muted)] focus:border-[var(--ui-primary)] focus:ring-1 focus:ring-[var(--ui-primary)] resize-none"
                            placeholder="Notiz hinzufügen..."
                        ></textarea>
                        <button
                            type="submit"
                            class="flex-shrink-0 w-8 h-8 rounded-lg bg-[var(--ui-primary)] text-white flex items-center justify-center hover:opacity-90 transition-opacity disabled:opacity-50"
                            @if(!$newNote) disabled @endif
                        >
                            @svg('heroicon-s-arrow-up', 'w-4 h-4')
                        </button>
                    </form>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Signal-Details --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Signal-Details</h2>

                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-[var(--ui-muted)] mb-1">Nachricht</h3>
                        <p class="text-sm text-[var(--ui-secondary)]">{{ $signal->message }}</p>
                    </div>

                    @if($signal->actions->isNotEmpty())
                        <div>
                            <h3 class="text-sm font-medium text-[var(--ui-muted)] mb-2">Handlungsoptionen</h3>
                            <div class="space-y-2">
                                @foreach($signal->actions as $action)
                                    @php($isActive = $activeActionId === $action->id)
                                    @php($isPending = $action->status === 'pending')
                                    <div @class([
                                        'rounded-lg border transition',
                                        'bg-blue-50 border-blue-200' => $isPending && ! $isActive,
                                        'bg-white border-[var(--ui-primary)] ring-2 ring-[var(--ui-primary)]/30 shadow-sm' => $isActive,
                                        'bg-green-50 border-green-200' => $action->status === 'applied',
                                        'bg-gray-50 border-gray-200 opacity-80' => $action->status === 'dismissed',
                                    ])>
                                        <div class="py-3 px-4">
                                            <div class="flex items-start gap-2">
                                                @if($action->status === 'applied')
                                                    @svg('heroicon-o-check-circle', 'w-4 h-4 text-green-600 mt-0.5 flex-shrink-0')
                                                @elseif($action->status === 'dismissed')
                                                    @svg('heroicon-o-x-circle', 'w-4 h-4 text-gray-500 mt-0.5 flex-shrink-0')
                                                @else
                                                    @svg('heroicon-o-light-bulb', 'w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0')
                                                @endif
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $action->title }}</div>
                                                        @if($action->status === 'applied')
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-800 whitespace-nowrap">Umgesetzt</span>
                                                        @elseif($action->status === 'dismissed')
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-200 text-gray-700 whitespace-nowrap">Verworfen</span>
                                                        @endif
                                                    </div>
                                                    @if(!empty($action->description))
                                                        <p class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $action->description }}</p>
                                                    @endif

                                                    @if(! $isPending && $action->decision_reason)
                                                        <p class="text-xs text-[var(--ui-secondary)] mt-2 italic">„{{ $action->decision_reason }}"</p>
                                                    @endif
                                                    @if(! $isPending)
                                                        <p class="text-[10px] text-[var(--ui-muted)] mt-1">
                                                            {{ $action->decidedByUser?->name ?? 'System' }}
                                                            @if($action->decided_at) · {{ $action->decided_at->diffForHumans() }} @endif
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>

                                            @if($isPending)
                                                @if(! $isActive)
                                                    <div class="flex items-center gap-2 mt-3 pl-6">
                                                        <button
                                                            wire:click="startActionDecision({{ $action->id }}, 'applied')"
                                                            class="px-3 py-1.5 rounded-md text-xs font-medium bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90 transition inline-flex items-center gap-1"
                                                        >
                                                            @svg('heroicon-o-check', 'w-3.5 h-3.5')
                                                            Umsetzen
                                                        </button>
                                                        <button
                                                            wire:click="startActionDecision({{ $action->id }}, 'dismissed')"
                                                            class="px-3 py-1.5 rounded-md text-xs font-medium bg-white border border-[var(--ui-border)] text-[var(--ui-secondary)] hover:bg-gray-50 transition inline-flex items-center gap-1"
                                                        >
                                                            @svg('heroicon-o-x-mark', 'w-3.5 h-3.5')
                                                            Verwerfen
                                                        </button>
                                                    </div>
                                                @else
                                                    <div class="mt-3 pl-6 space-y-2">
                                                        <label class="block text-xs font-medium text-[var(--ui-muted)]">
                                                            @if($activeActionDecision === 'applied')
                                                                Notiz zur Umsetzung (optional)
                                                            @else
                                                                Begründung für das Verwerfen
                                                            @endif
                                                        </label>
                                                        <textarea
                                                            wire:model="activeActionReason"
                                                            rows="2"
                                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm"
                                                            placeholder="{{ $activeActionDecision === 'applied' ? 'z.B. Weekly mit Team X ist eingerichtet ...' : 'z.B. Empfehlung zielt am Kontext vorbei, weil ...' }}"
                                                            autofocus
                                                        ></textarea>
                                                        @error('activeActionReason')
                                                            <p class="text-xs text-red-600">{{ $message }}</p>
                                                        @enderror
                                                        <div class="flex items-center justify-end gap-2">
                                                            <button wire:click="cancelActionDecision" class="text-xs text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                                                                Abbrechen
                                                            </button>
                                                            <button
                                                                wire:click="confirmActionDecision"
                                                                @class([
                                                                    'px-3 py-1.5 rounded-md text-xs font-medium transition',
                                                                    'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90' => $activeActionDecision === 'applied',
                                                                    'bg-gray-600 text-white hover:bg-gray-700' => $activeActionDecision === 'dismissed',
                                                                ])
                                                            >
                                                                @if($activeActionDecision === 'applied') Umsetzen bestätigen @else Verwerfen bestätigen @endif
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($signal->trigger_metrics && count($signal->trigger_metrics) > 0)
                        <div>
                            <h3 class="text-sm font-medium text-[var(--ui-muted)] mb-2">Trigger-Metriken</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @foreach($signal->trigger_metrics as $key => $value)
                                    <div class="py-3 px-4 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                                        <span class="text-xs text-[var(--ui-muted)]">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                        <div class="text-sm font-medium text-[var(--ui-secondary)] mt-0.5">
                                            {{ is_array($value) ? json_encode($value) : $value }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Kommentare --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">
                    Kommentare
                    @if($this->signalComments->isNotEmpty())
                        <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-neutral-200/80 text-gray-600 tabular-nums">{{ $this->signalComments->count() }}</span>
                    @endif
                </h2>

                @if($this->signalComments->isNotEmpty())
                    <div class="relative mb-6">
                        {{-- Vertical connection line --}}
                        <div class="absolute left-[11px] top-0 bottom-0 w-px bg-gray-200"></div>

                        <div class="space-y-4 relative">
                            @foreach($this->signalComments as $comment)
                                <div class="flex gap-3">
                                    <div class="flex-shrink-0 relative z-10">
                                        @if($comment->author_context === 'system')
                                            <span class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-full bg-amber-100 border border-amber-200">
                                                @svg('heroicon-s-cog-6-tooth', 'w-3 h-3 text-amber-600')
                                            </span>
                                        @elseif($comment->author_context === 'inference')
                                            <span class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-full bg-purple-100 border border-purple-200">
                                                @svg('heroicon-s-cpu-chip', 'w-3 h-3 text-purple-600')
                                            </span>
                                        @else
                                            <span class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-full bg-gray-100 border border-gray-200 text-[9px] font-medium text-gray-600">
                                                {{ mb_strtoupper(mb_substr($comment->user?->name ?? 'U', 0, 1)) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex-grow min-w-0 rounded-md border border-gray-200 overflow-hidden">
                                        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 flex items-center gap-2 text-[11px] text-gray-500">
                                            <span class="font-semibold text-gray-900">
                                                @if($comment->author_context === 'system')
                                                    System
                                                @elseif($comment->author_context === 'inference')
                                                    Inference
                                                @else
                                                    {{ $comment->user?->name ?? 'Unbekannt' }}
                                                @endif
                                            </span>
                                            <span class="text-gray-300">&middot;</span>
                                            <span>{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        <div class="px-4 py-3 text-xs text-gray-700 leading-relaxed">
                                            {!! nl2br(e($comment->content)) !!}
                                        </div>

                                        {{-- Nested replies --}}
                                        @if($comment->replies->isNotEmpty())
                                            <div class="border-t border-gray-100">
                                                @foreach($comment->replies as $reply)
                                                    <div class="px-4 py-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                                                        <div class="text-[11px] text-gray-500 mb-1 flex items-center gap-1.5">
                                                            @if($reply->author_context === 'system')
                                                                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-amber-100">
                                                                    @svg('heroicon-s-cog-6-tooth', 'w-2.5 h-2.5 text-amber-600')
                                                                </span>
                                                                <span class="font-medium text-gray-700">System</span>
                                                            @elseif($reply->author_context === 'inference')
                                                                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-purple-100">
                                                                    @svg('heroicon-s-cpu-chip', 'w-2.5 h-2.5 text-purple-600')
                                                                </span>
                                                                <span class="font-medium text-gray-700">Inference</span>
                                                            @else
                                                                <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-gray-100 text-[8px] font-medium text-gray-500">
                                                                    {{ mb_strtoupper(mb_substr($reply->user?->name ?? 'U', 0, 1)) }}
                                                                </span>
                                                                <span class="font-medium text-gray-700">{{ $reply->user?->name ?? 'Unbekannt' }}</span>
                                                            @endif
                                                            <span class="text-gray-300">&middot;</span>
                                                            <span>{{ $reply->created_at->diffForHumans() }}</span>
                                                        </div>
                                                        <div class="text-xs text-gray-700 pl-5">
                                                            {!! nl2br(e($reply->content)) !!}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif

                                        {{-- Reply button --}}
                                        @if($comment->author_context !== 'system')
                                            <div class="px-4 py-2 border-t border-gray-100 bg-gray-50/50">
                                                @if($replyingTo === $comment->id)
                                                    <div class="space-y-2">
                                                        <textarea
                                                            wire:model="replyBody"
                                                            wire:keydown.ctrl.enter="submitReply"
                                                            rows="2"
                                                            class="w-full text-xs rounded-md border border-gray-200 bg-white px-3 py-2 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-100 resize-none"
                                                            placeholder="Antwort schreiben..."
                                                            autofocus
                                                        ></textarea>
                                                        <div class="flex items-center justify-between">
                                                            <span class="text-[10px] text-gray-400">Ctrl+Enter</span>
                                                            <div class="flex items-center gap-2">
                                                                <button wire:click="cancelReply" class="text-[11px] text-gray-500 hover:text-gray-700">Abbrechen</button>
                                                                <button wire:click="submitReply" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-medium text-white bg-[var(--ui-primary)] hover:opacity-90 rounded-md transition-opacity">
                                                                    Antworten
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <button wire:click="startReply({{ $comment->id }})" class="text-[11px] text-gray-500 hover:text-[var(--ui-primary)] transition-colors">
                                                        @svg('heroicon-o-chat-bubble-left', 'w-3 h-3 inline-block mr-0.5')
                                                        Antworten
                                                    </button>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- New Comment Form --}}
                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <span class="inline-flex items-center justify-center w-[22px] h-[22px] rounded-full bg-green-100 border border-green-200 text-[9px] font-medium text-green-700">+</span>
                    </div>
                    <div class="flex-grow rounded-md border border-gray-200 overflow-hidden focus-within:border-blue-300 focus-within:ring-1 focus-within:ring-blue-100 transition-all">
                        <textarea
                            wire:model="newComment"
                            wire:keydown.ctrl.enter="addComment"
                            rows="2"
                            placeholder="Kommentar schreiben..."
                            class="w-full px-4 py-3 text-xs bg-white text-gray-900 placeholder-gray-400 focus:outline-none resize-none border-none"
                        ></textarea>
                        <div class="px-4 py-2 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                            <span class="text-[10px] text-gray-400">Ctrl+Enter</span>
                            <button wire:click="addComment"
                                    class="inline-flex items-center gap-1.5 px-3 py-[4px] text-[11px] font-medium text-white bg-[var(--ui-primary)] hover:opacity-90 rounded-md transition-opacity">
                                Kommentieren
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Signalverlauf --}}
            <div class="bg-white rounded-lg border border-[var(--ui-border)] p-6">
                <h2 class="text-lg font-semibold text-[var(--ui-secondary)] mb-4">Signalverlauf (gleiche Definition)</h2>

                @if($this->historicalSignals->isEmpty())
                    <div class="text-center py-6">
                        @svg('heroicon-o-bell-slash', 'w-6 h-6 text-[var(--ui-muted)] mx-auto mb-2')
                        <p class="text-sm text-[var(--ui-muted)]">Erstes Signal dieser Art.</p>
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($this->historicalSignals as $histSignal)
                            <a href="{{ route('organization.signals.show', $histSignal) }}" class="flex items-center gap-3 py-2.5 px-4 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors group">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium flex-shrink-0
                                    @if($histSignal->severity === 'critical') bg-red-100 text-red-800
                                    @elseif($histSignal->severity === 'warning') bg-amber-100 text-amber-800
                                    @else bg-blue-100 text-blue-800
                                    @endif
                                ">
                                    {{ ucfirst($histSignal->severity) }}
                                </span>
                                <span class="text-sm text-[var(--ui-muted)]">{{ $histSignal->created_at->format('d.m.Y') }}</span>
                                <span class="text-sm text-[var(--ui-secondary)]">&ndash;</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @if($histSignal->status === 'open') bg-yellow-100 text-yellow-800
                                    @elseif($histSignal->status === 'acknowledged') bg-blue-100 text-blue-800
                                    @elseif($histSignal->status === 'resolved') bg-green-100 text-green-800
                                    @else bg-gray-100 text-gray-600
                                    @endif
                                ">
                                    @switch($histSignal->status)
                                        @case('open') Offen @break
                                        @case('acknowledged') Bestätigt @break
                                        @case('resolved') Gelöst @break
                                        @case('dismissed') Verworfen @break
                                    @endswitch
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>

    {{-- Action Reason Modal --}}
    @if($pendingAction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="cancelAction">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-1">
                    @switch($pendingAction)
                        @case('acknowledge') Signal bestätigen @break
                        @case('resolve') Signal lösen @break
                        @case('dismiss') Signal verwerfen @break
                    @endswitch
                </h3>
                <p class="text-sm text-[var(--ui-muted)] mb-4">
                    @switch($pendingAction)
                        @case('acknowledge') Was macht dieses Signal relevant? @break
                        @case('resolve') Was wurde unternommen? @break
                        @case('dismiss') Warum ist dieses Signal nicht relevant? @break
                    @endswitch
                </p>

                <textarea
                    wire:model="actionReason"
                    rows="3"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm mb-4"
                    placeholder="{{ match($pendingAction) { 'acknowledge' => 'z.B. Bestätigt, prüfen wir im nächsten Weekly...', 'resolve' => 'z.B. Interlink angelegt, Zuständigkeit geklärt...', 'dismiss' => 'z.B. Entity bewusst inaktiv, kein Handlungsbedarf...' } }}"
                    autofocus
                ></textarea>

                <div class="flex items-center justify-end gap-3">
                    <button wire:click="cancelAction" class="text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                        Abbrechen
                    </button>
                    <button
                        wire:click="confirmAction"
                        class="px-4 py-2 rounded-md text-sm font-medium transition
                            {{ $pendingAction === 'dismiss'
                                ? 'bg-gray-600 text-white hover:bg-gray-700'
                                : 'bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90' }}"
                    >
                        @switch($pendingAction)
                            @case('acknowledge') Bestätigen @break
                            @case('resolve') Lösen @break
                            @case('dismiss') Verwerfen @break
                        @endswitch
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Snooze Modal --}}
    @if($showSnoozeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="cancelSnooze">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-sm mx-4 p-6">
                <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-1">Signal snoozen</h3>
                <p class="text-sm text-[var(--ui-muted)] mb-4">Signal wird bis zum gewählten Zeitpunkt aus der Übersicht ausgeblendet.</p>

                <div class="space-y-2 mb-4">
                    @foreach([
                        '1d' => '1 Tag',
                        '3d' => '3 Tage',
                        '1w' => '1 Woche',
                        '2w' => '2 Wochen',
                        '1m' => '1 Monat',
                        'custom' => 'Benutzerdefiniert',
                    ] as $value => $label)
                        <label class="flex items-center gap-3 px-3 py-2.5 rounded-md border cursor-pointer transition-colors
                            {{ $snoozeDuration === $value ? 'border-[var(--ui-primary)] bg-[var(--ui-primary)]/5' : 'border-gray-200 hover:border-gray-300' }}">
                            <input type="radio" wire:model.live="snoozeDuration" value="{{ $value }}"
                                   class="text-[var(--ui-primary)] focus:ring-[var(--ui-primary)]">
                            <span class="text-sm text-[var(--ui-secondary)]">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                @if($snoozeDuration === 'custom')
                    <div class="mb-4">
                        <input type="date" wire:model="snoozeCustomDate"
                               min="{{ now()->addDay()->format('Y-m-d') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 text-sm">
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3">
                    <button wire:click="cancelSnooze" class="text-sm text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]">
                        Abbrechen
                    </button>
                    <button
                        wire:click="confirmSnooze"
                        class="px-4 py-2 rounded-md text-sm font-medium bg-[var(--ui-primary)] text-[var(--ui-on-primary)] hover:opacity-90 transition"
                        @if($snoozeDuration === 'custom' && !$snoozeCustomDate) disabled @endif
                    >
                        @svg('heroicon-o-clock', 'w-4 h-4 inline-block mr-1')
                        Snoozen
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-ui-page>
