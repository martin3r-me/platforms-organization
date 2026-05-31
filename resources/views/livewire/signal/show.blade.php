<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'Organization', 'href' => route('organization.dashboard'), 'icon' => 'building-office'],
            ['label' => $signal->entity?->name ?? 'Entity', 'href' => $signal->entity ? route('organization.entities.show', $signal->entity) : '#'],
            ['label' => 'Signale'],
            ['label' => $signal->definition?->name ?? 'Signal'],
        ]">
            @if($signal->status === 'open')
                <x-ui-button variant="primary" size="sm" wire:click="startAction('acknowledge')">
                    @svg('heroicon-o-check', 'w-4 h-4')
                    <span>Bestätigen</span>
                </x-ui-button>
                <x-ui-button variant="ghost" size="sm" wire:click="startAction('dismiss')">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Verwerfen</span>
                </x-ui-button>
            @elseif($signal->status === 'acknowledged')
                <x-ui-button variant="primary" size="sm" wire:click="startAction('resolve')">
                    @svg('heroicon-o-check-circle', 'w-4 h-4')
                    <span>Lösen</span>
                </x-ui-button>
                <x-ui-button variant="ghost" size="sm" wire:click="startAction('dismiss')">
                    @svg('heroicon-o-x-mark', 'w-4 h-4')
                    <span>Verwerfen</span>
                </x-ui-button>
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

                    @if($signal->suggested_actions && count($signal->suggested_actions) > 0)
                        <div>
                            <h3 class="text-sm font-medium text-[var(--ui-muted)] mb-2">Handlungsoptionen</h3>
                            <div class="space-y-2">
                                @foreach($signal->suggested_actions as $action)
                                    <div class="py-3 px-4 bg-blue-50 rounded-lg border border-blue-200">
                                        <div class="flex items-start gap-2">
                                            @svg('heroicon-o-light-bulb', 'w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0')
                                            <div>
                                                <div class="text-sm font-medium text-[var(--ui-secondary)]">{{ $action['title'] }}</div>
                                                @if(!empty($action['description']))
                                                    <p class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $action['description'] }}</p>
                                                @endif
                                            </div>
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
</x-ui-page>
