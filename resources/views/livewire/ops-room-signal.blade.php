<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    {{-- ╔═══════════════════════════════════════════════════════════════════════╗ --}}
    {{-- ║  CYBERSYN 2.0 — Signal Detail                                         ║ --}}
    {{-- ╚═══════════════════════════════════════════════════════════════════════╝ --}}

    <div class="flex-1 bg-zinc-900 text-zinc-100 w-full font-sans flex flex-col overflow-hidden min-h-0"
         style="background-image:
            radial-gradient(circle at 50% 0%, rgba(251,146,60,0.04) 0%, transparent 60%),
            radial-gradient(circle at 100% 100%, rgba(220,38,38,0.03) 0%, transparent 50%);"
         x-data
         @keydown.escape.window="window.history.back()">

        @php($p = $this->perspective)
        @php($totals = $this->totals)
        @php($availablePerspectives = $this->availablePerspectives)
        @php($perspectiveEntityId = $signal->perspective_entity_id)
        @php($path = $this->path)
        @php($breadcrumb = [
            ['label' => 'PERSP/'.str_pad((string) ($perspectiveEntityId ?? 0), 4, '0', STR_PAD_LEFT), 'href' => route('organization.ops-room')],
            ['label' => strtoupper(str_replace('_', '', (string) $signal->vsm_level)), 'href' => $signal->vsm_level && $perspectiveEntityId ? route('organization.ops-room.level', ['perspective' => $perspectiveEntityId, 'vsm' => $signal->vsm_level]) : null],
            ['label' => '#'.str_pad((string) $signal->id, 4, '0', STR_PAD_LEFT)],
        ])

        @include('organization::livewire.partials.ops-room-header', ['breadcrumb' => $breadcrumb])

        <main class="flex-1 min-h-0 flex overflow-hidden">

            {{-- LEFT — VSM-Pfad --}}
            <aside class="w-64 flex-shrink-0 border-r border-zinc-800 px-5 py-5 flex flex-col gap-4 overflow-y-auto">
                <div class="text-[9px] tracking-[0.35em] text-zinc-500 uppercase">VSM-Pfad</div>
                <div class="flex flex-col gap-1">
                    @foreach($path as $row)
                        @php($accent = $row['is_current'] ? 'text-orange-400' : ($row['is_passed'] ? 'text-amber-400' : ($row['is_origin'] ? 'text-zinc-300' : 'text-zinc-700')))
                        @php($bg = $row['is_current'] ? 'bg-orange-400/10 border-orange-400' : ($row['is_passed'] ? 'border-amber-500/40' : ($row['is_origin'] ? 'border-zinc-700' : 'border-zinc-800')))
                        <div class="px-3 py-2 border {{ $bg }} flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="font-mono tabular-nums text-xl font-light {{ $accent }}">{{ $row['display'] }}</span>
                                <span class="text-[10px] uppercase tracking-[0.2em] {{ $accent }}">{{ $row['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                @if($row['is_origin'])
                                    <span class="text-[9px] uppercase tracking-[0.2em] text-zinc-500">START</span>
                                @endif
                                @if($row['is_current'])
                                    <span class="w-1.5 h-1.5 bg-orange-400 animate-pulse"></span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($signal->escalated_at)
                    <div class="text-[10px] text-amber-400 uppercase tracking-wider mt-2 leading-relaxed">
                        Eskaliert am {{ $signal->escalated_at->format('d.m.Y H:i') }}
                    </div>
                @endif
                @if($signal->aggregated_at)
                    <div class="text-[10px] text-purple-400 uppercase tracking-wider leading-relaxed">
                        Aggregiert am {{ $signal->aggregated_at->format('d.m.Y H:i') }}
                    </div>
                @endif
            </aside>

            {{-- CENTER — Signal-Body --}}
            <section class="flex-1 min-w-0 flex flex-col overflow-hidden">
                <div class="flex-1 min-h-0 overflow-y-auto px-8 py-6">

                    {{-- Header-Block --}}
                    <div class="flex items-start gap-4 mb-5">
                        @if($signal->source_type === \Platform\Organization\Models\OrganizationSignal::SOURCE_TYPE_HUMAN_ALGEDONIC)
                            <span class="inline-flex items-center justify-center w-9 h-9 bg-red-600 text-white text-lg font-bold animate-pulse flex-shrink-0" title="Algedonic">!</span>
                        @elseif($signal->severity === 'critical')
                            <span class="inline-flex w-3 h-3 bg-red-500 mt-2 flex-shrink-0"></span>
                        @elseif($signal->severity === 'warning')
                            <span class="inline-flex w-3 h-3 bg-amber-400 mt-2 flex-shrink-0"></span>
                        @else
                            <span class="inline-flex w-3 h-3 bg-zinc-600 mt-2 flex-shrink-0"></span>
                        @endif

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-1.5 flex-wrap text-[10px] uppercase tracking-[0.25em] text-zinc-500">
                                <span class="font-mono text-zinc-300">#{{ str_pad((string) $signal->id, 4, '0', STR_PAD_LEFT) }}</span>
                                <span class="text-zinc-700">·</span>
                                <span>{{ $signal->severity }}</span>
                                <span class="text-zinc-700">·</span>
                                <span>{{ str_replace('_', ' ', (string) $signal->source_type) }}</span>
                                @if($signal->entity)
                                    <span class="text-zinc-700">·</span>
                                    <a href="{{ route('organization.entities.show', $signal->entity) }}" class="text-zinc-300 hover:text-orange-300 transition">{{ $signal->entity->name }}</a>
                                @endif
                            </div>
                            <div class="text-base text-zinc-100 leading-relaxed whitespace-pre-wrap">{{ $signal->message }}</div>
                        </div>
                    </div>

                    {{-- Trigger Metrics --}}
                    @if(!empty($signal->trigger_metrics))
                        <div class="mt-6 border-t border-zinc-800 pt-4">
                            <div class="text-[9px] tracking-[0.35em] text-zinc-500 uppercase mb-2">Evidenz</div>
                            <div class="bg-zinc-950/60 border border-zinc-800 p-3 text-[11px] font-mono text-zinc-300 leading-relaxed whitespace-pre-wrap">{{ json_encode($signal->trigger_metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div>
                        </div>
                    @endif

                    {{-- Suggested Actions --}}
                    @if(!empty($signal->suggested_actions))
                        <div class="mt-6 border-t border-zinc-800 pt-4">
                            <div class="text-[9px] tracking-[0.35em] text-zinc-500 uppercase mb-2">Vorgeschlagene Aktionen</div>
                            <ul class="space-y-2">
                                @foreach($signal->suggested_actions as $action)
                                    <li class="border border-zinc-800 bg-zinc-900/40 px-3 py-2">
                                        <div class="text-sm text-zinc-100">{{ $action['title'] ?? '—' }}</div>
                                        @if(!empty($action['description']))
                                            <div class="text-xs text-zinc-500 mt-1 leading-relaxed">{{ $action['description'] }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                </div>

                {{-- Bottom Action-Bar --}}
                <div class="flex-shrink-0 border-t border-zinc-800 px-8 py-3 flex items-center justify-between gap-3 bg-zinc-950/40">
                    <div class="flex items-center gap-3 text-[10px] uppercase tracking-[0.25em] text-zinc-500 font-mono">
                        <span>Status: <span class="text-zinc-300">{{ $signal->status }}</span></span>
                        @if($signal->currentOwner)
                            <span class="text-zinc-700">·</span>
                            <span>Owner: <span class="text-zinc-300">{{ $signal->currentOwner->name }}</span></span>
                        @endif
                        @if($signal->deadline_at)
                            <span class="text-zinc-700">·</span>
                            <span class="{{ $signal->status === 'open' && $signal->deadline_at->isPast() ? 'text-red-400' : '' }}">
                                Deadline: <span class="text-zinc-300">{{ $signal->deadline_at->diffForHumans() }}</span>
                            </span>
                        @endif
                    </div>

                    @if($signal->status === 'open' || $signal->status === 'acknowledged')
                        <div class="flex items-center gap-2">
                            @if($signal->status === 'open')
                                <button wire:click="acknowledge" class="px-3 py-1.5 text-[10px] uppercase tracking-[0.2em] border border-zinc-700 text-zinc-200 hover:border-orange-400 hover:text-orange-300 transition">Bestätigen</button>
                            @endif
                            <button wire:click="resolve" class="px-3 py-1.5 text-[10px] uppercase tracking-[0.2em] border border-emerald-500/50 text-emerald-300 hover:bg-emerald-500/10 transition">Lösen</button>
                            <button wire:click="dismiss" wire:confirm="Wirklich verwerfen?" class="px-3 py-1.5 text-[10px] uppercase tracking-[0.2em] border border-zinc-800 text-zinc-500 hover:text-zinc-300 transition">Verwerfen</button>
                        </div>
                    @endif
                </div>
            </section>
        </main>

        @include('organization::livewire.partials.ops-room-footer')
    </div>
</x-ui-page>
