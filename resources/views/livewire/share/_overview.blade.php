{{-- Kunden-Tor · Übersicht: Kopf, Kennzahlen, Projekte als Liste, quer „Zuletzt erledigt" / „Als Nächstes". --}}
@php
    $k = $data['kpis'];
@endphp
<div class="mx-auto max-w-[1200px] px-4 py-8 sm:px-8 sm:py-10">
    <div class="text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $entity->type?->name ?? 'Ihr Bereich' }}</div>
    <h1 class="mt-1 text-2xl font-bold tracking-tight md:text-3xl">{{ $title }}</h1>

    @if ($share->message)
        <div class="mt-5 flex max-w-3xl gap-3 rounded-[8px] bg-[color:var(--nx-bg)] p-4 text-sm leading-relaxed">
            @svg('heroicon-o-chat-bubble-left-ellipsis', 'w-5 h-5 shrink-0 text-[color:var(--nx-muted)]')
            <div class="min-w-0">
                <div class="whitespace-pre-line">{{ $share->message }}</div>
                @if ($senderName)<div class="mt-1 text-xs text-[color:var(--nx-muted)]">— {{ $senderName }}</div>@endif
            </div>
        </div>
    @endif

    @if ($data['featured'])
        <div class="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="col-span-2 rounded-[8px] border border-[color:var(--nx-line)] p-4 lg:col-span-1">
                <div class="text-xs text-[color:var(--nx-muted)]">Gesamtfortschritt</div>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-semibold tabular-nums">{{ $k['progress'] !== null ? $k['progress'] . ' %' : '–' }}</span>
                    @if ($k['steps_total'])
                        <span class="text-xs text-[color:var(--nx-faint)]">{{ $k['steps_done'] }} / {{ $k['steps_total'] }} Schritte</span>
                    @endif
                </div>
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                    <div class="h-full rounded-full bg-[color:var(--nx-success)]" style="width: {{ $k['progress'] ?? 0 }}%"></div>
                </div>
            </div>
            <x-nx-stat label="Laufend" :value="$k['active']" icon="heroicon-o-play-circle" accent="var(--nx-info)" />
            <x-nx-stat label="Abgeschlossen" :value="$k['done']" icon="heroicon-o-check-circle" accent="var(--nx-success)" />
            <x-nx-stat class="col-span-2 lg:col-span-1" label="Nächste Fälligkeit" :value="$k['next_due'] ? $de($k['next_due'], 'D. MMM') : '–'" :hint="$k['next_due'] ? $duePhrase($k['next_due']) : null" icon="heroicon-o-calendar" />
        </div>
    @endif

    {{-- Typen mit Fortschritt (Projekte) --}}
    @foreach ($data['featured'] as $group)
        <section class="mt-10">
            <div class="mb-2 flex items-baseline gap-2">
                <h2 class="text-base font-semibold">{{ $group['label'] }}</h2>
                <span class="text-sm text-[color:var(--nx-faint)]">{{ count($group['items']) }}</span>
            </div>
            <div class="overflow-hidden rounded-[8px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                @foreach ($group['items'] as $item)
                    @php
                        $itemPct = $pct($item['progress'] ?? null);
                        $nextStep = ($item['next'] ?? [])[0] ?? null;
                        $tag = $item['has_detail'] ? 'a' : 'div';
                    @endphp
                    <{{ $tag }} @if ($item['has_detail']) href="{{ $link($group['slug'], $item['id']) }}" wire:navigate @endif wire:key="ov-{{ $group['slug'] }}-{{ $item['id'] }}"
                        @class(['group flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:gap-5', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $item['has_detail'], 'opacity-70' => ($item['state'] ?? '') === 'done'])>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-medium">{{ $item['title'] }}</span>
                                <x-nx-badge :variant="$badge[$item['state'] ?? 'active'] ?? 'neutral'" dot>{{ $item['state_label'] ?? 'Läuft' }}</x-nx-badge>
                            </div>
                            <div class="mt-0.5 truncate text-xs text-[color:var(--nx-muted)]">
                                {{ collect([
                                    $item['area'] ?? null,
                                    $nextStep ? 'Als Nächstes: ' . $nextStep['title'] : null,
                                    ! $nextStep && ! empty($item['updated_at']) ? 'Zuletzt bewegt ' . $ago($item['updated_at']) : null,
                                ])->filter()->implode(' · ') }}
                            </div>
                        </div>
                        @if ($itemPct !== null)
                            <div class="flex shrink-0 items-center gap-3 sm:w-56">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                                    <div class="h-full rounded-full bg-[color:var(--nx-success)]" style="width: {{ $itemPct }}%"></div>
                                </div>
                                <span class="w-20 text-right text-xs tabular-nums text-[color:var(--nx-muted)]">{{ $item['progress']['done'] }} / {{ $item['progress']['total'] }}</span>
                            </div>
                        @endif
                        @if ($item['has_detail'])
                            @svg('heroicon-o-chevron-right', 'hidden w-4 h-4 shrink-0 text-[color:var(--nx-faint)] group-hover:text-[color:var(--nx-text)] sm:block')
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- Quer über alles: zuletzt erledigt / als Nächstes --}}
    @if ($data['recent'] || $data['upcoming'])
        <div class="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-2">
            @foreach ([['Als Nächstes', $data['upcoming'], 'next'], ['Zuletzt erledigt', $data['recent'], 'done']] as [$heading, $steps, $kind])
                <section>
                    <h2 class="mb-2 text-base font-semibold">{{ $heading }}</h2>
                    @if ($steps)
                        <div class="overflow-hidden rounded-[8px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                            @foreach ($steps as $step)
                                @php
                                    $overdue = $kind === 'next' && ! empty($step['due']) && $step['due']->isPast() && ! $step['due']->isToday();
                                    $stepTag = $step['linkable'] ? 'a' : 'div';
                                @endphp
                                <{{ $stepTag }} @if ($step['linkable']) href="{{ $link($step['slug'], $step['parent_id']) }}" wire:navigate @endif
                                    @class(['flex items-start gap-3 px-4 py-2.5', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $step['linkable']])>
                                    @if ($kind === 'done')
                                        @svg('heroicon-s-check-circle', 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-success)]')
                                    @else
                                        <span class="mt-1 h-3.5 w-3.5 shrink-0 rounded-full border-[1.5px] border-[color:var(--nx-line-strong)]"></span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm leading-snug">{{ $step['title'] }}</div>
                                        <div class="mt-0.5 truncate text-xs text-[color:var(--nx-faint)]">{{ $step['parent'] }}</div>
                                    </div>
                                    <span @class(['shrink-0 pt-0.5 text-xs', 'font-medium text-[color:var(--nx-danger)]' => $overdue, 'text-[color:var(--nx-faint)]' => ! $overdue])>
                                        {{ $kind === 'done' ? $de($step['at'] ?? null, 'D. MMM') : $duePhrase($step['due'] ?? null) }}
                                    </span>
                                </{{ $stepTag }}>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-[8px] border border-dashed border-[color:var(--nx-line-strong)] px-4 py-6 text-center text-sm text-[color:var(--nx-faint)]">
                            {{ $kind === 'done' ? 'Noch nichts erledigt' : 'Gerade nichts geplant' }}
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    @endif

    {{-- Weitere Inhalte --}}
    @foreach ($data['sections'] as $group)
        <section class="mt-10">
            <div class="mb-2 flex items-baseline gap-2">
                <h2 class="text-base font-semibold">{{ $group['label'] }}</h2>
                <span class="text-sm text-[color:var(--nx-faint)]">{{ count($group['items']) }}</span>
            </div>
            <div class="overflow-hidden rounded-[8px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                @foreach ($group['items'] as $item)
                    @php $rowTag = $item['has_detail'] ? 'a' : 'div'; @endphp
                    <{{ $rowTag }} @if ($item['has_detail']) href="{{ $link($group['slug'], $item['id']) }}" wire:navigate @endif wire:key="sec-{{ $group['slug'] }}-{{ $item['id'] }}"
                        @class(['flex items-center gap-3 px-4 py-2.5 text-sm', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $item['has_detail']])>
                        @svg('heroicon-o-' . $group['icon'], 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
                        <span class="min-w-0 flex-1 truncate">{{ $item['title'] }}</span>
                        @if (! empty($item['area']))<span class="hidden shrink-0 text-xs text-[color:var(--nx-faint)] sm:inline">{{ $item['area'] }}</span>@endif
                        <x-nx-badge :variant="$badge[$item['state'] ?? 'active'] ?? 'neutral'">{{ $item['state_label'] ?? '' }}</x-nx-badge>
                    </{{ $rowTag }}>
                @endforeach
            </div>
        </section>
    @endforeach

    @if (! $data['featured'] && ! $data['sections'])
        <div class="mt-10 rounded-[8px] border border-dashed border-[color:var(--nx-line-strong)] px-6 py-14 text-center">
            @svg('heroicon-o-sparkles', 'mx-auto w-6 h-6 text-[color:var(--nx-faint)]')
            <div class="mt-2 font-medium">Hier entsteht gerade etwas</div>
            <div class="mt-1 text-sm text-[color:var(--nx-muted)]">Sobald es Neuigkeiten gibt, sehen Sie sie an dieser Stelle.</div>
        </div>
    @endif
</div>
