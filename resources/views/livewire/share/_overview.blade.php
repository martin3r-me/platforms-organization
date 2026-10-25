{{-- Kunden-Tor · Übersicht: Kopf, Kennzahlen, Projekte, quer „Als Nächstes" / „Zuletzt erledigt". --}}
@php
    $k = $data['kpis'];
    $projectCount = collect($data['featured'])->sum(fn ($g) => count($g['items']));
    $openSteps = max(0, ($k['steps_total'] ?? 0) - ($k['steps_done'] ?? 0));
    // „Zuletzt erledigt" nach Tagen gruppiert
    $dayLabel = function ($d) use ($de) {
        if (! $d) {
            return 'Früher';
        }

        return match (true) {
            $d->isToday() => 'Heute',
            $d->isYesterday() => 'Gestern',
            $d->greaterThan(now()->subDays(7)) => $d->copy()->locale('de')->isoFormat('dddd'),
            default => $de($d, 'D. MMMM'),
        };
    };
    $recentByDay = collect($data['recent'])->groupBy(fn ($s) => $dayLabel($s['at'] ?? null));
@endphp
<div class="mx-auto max-w-[1120px] px-4 py-8 sm:px-8 md:py-10">
    {{-- Kopf --}}
    <div class="flex items-start gap-4">
        {!! $tile($entity->name, 'e' . $entity->id, 48) !!}
        <div class="min-w-0">
            <div class="text-xs font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $entity->type?->name ?? 'Ihr Bereich' }}</div>
            <h1 class="mt-0.5 text-2xl font-bold leading-tight tracking-tight md:text-3xl">{{ $title }}</h1>
            <div class="mt-1 text-sm text-[color:var(--nx-muted)]">
                {{ $projectCount ? $projectCount . ' ' . ($projectCount === 1 ? 'Projekt' : 'Projekte') . ' · ' : '' }}Stand {{ $de(now(), 'D. MMMM YYYY') }}
            </div>
        </div>
    </div>

    @if ($share->message)
        <div class="mt-6 max-w-3xl rounded-[12px] border border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] p-4 sm:p-5">
            @if ($senderName)
                <div class="mb-2 flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[color:var(--nx-accent)] text-[10px] font-semibold text-[color:var(--nx-on-accent)]">{{ mb_strtoupper(mb_substr($senderName, 0, 1)) }}</span>
                    <span class="text-sm font-medium">{{ $senderName }}</span>
                    <span class="text-xs text-[color:var(--nx-faint)]">{{ $teamName }}</span>
                </div>
            @endif
            <div class="whitespace-pre-line text-[15px] leading-relaxed">{{ $share->message }}</div>
        </div>
    @endif

    @if ($data['featured'])
        {{-- Kennzahlen --}}
        <div class="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="col-span-2 flex items-center gap-4 rounded-[12px] border border-[color:var(--nx-line)] p-4 lg:col-span-1">
                {!! $ring($k['progress'] ?? 0, 52, true) !!}
                <div class="min-w-0">
                    <div class="text-sm font-medium">Gesamtfortschritt</div>
                    <div class="mt-0.5 text-xs text-[color:var(--nx-muted)]">
                        {{ $k['steps_total'] ? $k['steps_done'] . ' von ' . $k['steps_total'] . ' Schritten erledigt' : 'Noch keine Schritte geplant' }}
                    </div>
                </div>
            </div>
            @foreach ([
                ['Aktive Projekte', $k['active'], $projectCount ? 'von ' . $projectCount : null, 'heroicon-o-bolt', 'var(--nx-info)'],
                ['Offene Schritte', $openSteps, $k['done'] ? $k['done'] . ' Projekt' . ($k['done'] === 1 ? '' : 'e') . ' abgeschlossen' : null, 'heroicon-o-list-bullet', 'var(--nx-tone-amber)'],
                ($k['overdue'] ?? 0)
                    ? ['Überfällig', $k['overdue'], ($k['overdue'] === 1 ? 'Schritt' : 'Schritte') . ' über dem Termin' . ($k['next_due'] ? ' · nächster ' . $de($k['next_due'], 'D. MMM') : ''), 'heroicon-o-exclamation-triangle', 'var(--nx-danger)']
                    : ['Nächste Fälligkeit', $k['next_due'] ? $de($k['next_due'], 'D. MMM') : '–', $k['next_due'] ? $duePhrase($k['next_due']) : 'nichts terminiert', 'heroicon-o-calendar-days', 'var(--nx-tone-violet)'],
            ] as [$label, $value, $hint, $icon, $accent])
                <div @class(['rounded-[12px] border border-[color:var(--nx-line)] p-4', 'col-span-2 lg:col-span-1' => $loop->last])>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs text-[color:var(--nx-muted)]">{{ $label }}</span>
                        <span style="color: {{ $accent }}">@svg($icon, 'w-4 h-4')</span>
                    </div>
                    <div class="mt-1.5 text-2xl font-semibold tabular-nums tracking-tight">{{ $value }}</div>
                    @if ($hint)<div class="mt-0.5 text-xs text-[color:var(--nx-faint)]">{{ $hint }}</div>@endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Projekte --}}
    @foreach ($data['featured'] as $group)
        <section class="mt-10">
            <div class="mb-3 flex items-baseline gap-2">
                <h2 class="text-[15px] font-semibold">{{ $group['label'] }}</h2>
                <span class="text-sm text-[color:var(--nx-faint)]">{{ count($group['items']) }}</span>
            </div>
            <div class="overflow-hidden rounded-[12px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                @foreach ($group['items'] as $item)
                    @php
                        $itemPct = $pct($item['progress'] ?? null);
                        $nextStep = ($item['next'] ?? [])[0] ?? null;
                        $isDone = ($item['state'] ?? '') === 'done';
                        $tag = $item['has_detail'] ? 'a' : 'div';
                    @endphp
                    <{{ $tag }} @if ($item['has_detail']) href="{{ $link($group['slug'], $item['id']) }}" wire:navigate @endif wire:key="ov-{{ $group['slug'] }}-{{ $item['id'] }}"
                        @class(['group flex items-center gap-4 px-4 py-4', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $item['has_detail']])>
                        {!! $tile($item['title'], $group['slug'] . $item['id'], 36) !!}
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span @class(['truncate font-medium', 'text-[color:var(--nx-muted)]' => $isDone])>{{ $item['title'] }}</span>
                                <x-nx-badge :variant="$badge[$item['state'] ?? 'active'] ?? 'neutral'" dot>{{ $item['state_label'] ?? 'Läuft' }}</x-nx-badge>
                            </div>
                            <div class="mt-1 flex min-w-0 items-center gap-1.5 text-xs text-[color:var(--nx-muted)]">
                                @if ($nextStep)
                                    @svg('heroicon-o-arrow-right-circle', 'w-3.5 h-3.5 shrink-0 text-[color:var(--nx-faint)]')
                                    <span class="truncate">{{ $nextStep['title'] }}</span>
                                    @if (! empty($nextStep['due']))<span class="shrink-0 text-[color:var(--nx-faint)]">· {{ $duePhrase($nextStep['due']) }}</span>@endif
                                @else
                                    <span class="truncate">{{ collect([$item['area'] ?? null, ! empty($item['updated_at']) ? 'Zuletzt bewegt ' . $ago($item['updated_at']) : null])->filter()->implode(' · ') }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($itemPct !== null)
                            <div class="hidden w-48 shrink-0 sm:block">
                                <div class="flex items-baseline justify-between text-xs">
                                    <span class="font-medium tabular-nums">{{ $itemPct }} %</span>
                                    <span class="tabular-nums text-[color:var(--nx-faint)]">{{ $item['progress']['done'] }}/{{ $item['progress']['total'] }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                                    <div class="h-full rounded-full" style="width: {{ $itemPct }}%; background: {{ $itemPct >= 100 ? 'var(--nx-success)' : 'var(--nx-info)' }}"></div>
                                </div>
                            </div>
                            <span class="sm:hidden">{!! $ring($itemPct, 28) !!}</span>
                        @endif
                        @if ($item['has_detail'])
                            @svg('heroicon-o-chevron-right', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)] transition-transform group-hover:translate-x-0.5 group-hover:text-[color:var(--nx-text)]')
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- Quer über alles --}}
    @if ($data['recent'] || $data['upcoming'])
        <div class="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-2">
            <section>
                <h2 class="mb-3 text-[15px] font-semibold">Als Nächstes</h2>
                @if ($data['upcoming'])
                    <div class="overflow-hidden rounded-[12px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                        @foreach ($data['upcoming'] as $step)
                            @php
                                $overdue = ! empty($step['due']) && $step['due']->isPast() && ! $step['due']->isToday();
                                $soon = ! empty($step['due']) && ! $overdue && $step['due']->lessThan(now()->addDays(3));
                                $stepTag = $step['linkable'] ? 'a' : 'div';
                            @endphp
                            <{{ $stepTag }} @if ($step['linkable']) href="{{ $link($step['slug'], $step['parent_id']) }}" wire:navigate @endif
                                @class(['flex items-center gap-3 px-4 py-3', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $step['linkable']])>
                                <span class="h-4 w-4 shrink-0 rounded-full border-[1.5px] border-[color:var(--nx-line-strong)]"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm">{{ $step['title'] }}</div>
                                    <div class="truncate text-xs text-[color:var(--nx-faint)]">{{ $step['parent'] }}</div>
                                </div>
                                @if (! empty($step['due']))
                                    <span @class(['shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                                  'bg-[rgba(224,49,49,.1)] text-[color:var(--nx-danger)]' => $overdue,
                                                  'bg-[rgba(232,89,12,.1)] text-[color:var(--nx-warning)]' => $soon,
                                                  'bg-[color:var(--nx-accent-soft)] text-[color:var(--nx-muted)]' => ! $overdue && ! $soon])>{{ $duePhrase($step['due']) }}</span>
                                @endif
                            </{{ $stepTag }}>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-[12px] border border-dashed border-[color:var(--nx-line-strong)] px-4 py-8 text-center text-sm text-[color:var(--nx-faint)]">Gerade nichts geplant</div>
                @endif
            </section>

            <section>
                <h2 class="mb-3 text-[15px] font-semibold">Zuletzt erledigt</h2>
                @if ($recentByDay->isNotEmpty())
                    <div class="rounded-[12px] border border-[color:var(--nx-line)] px-4 py-2">
                        @foreach ($recentByDay as $day => $steps)
                            <div @class(['py-2', 'border-t border-[color:var(--nx-line)]' => ! $loop->first])>
                                <div class="mb-1 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">{{ $day }}</div>
                                @foreach ($steps as $step)
                                    @php
                                        $stepTag = $step['linkable'] ? 'a' : 'div';
                                    @endphp
                                    <{{ $stepTag }} @if ($step['linkable']) href="{{ $link($step['slug'], $step['parent_id']) }}" wire:navigate @endif
                                        @class(['-mx-2 flex items-start gap-3 rounded-[6px] px-2 py-1.5', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $step['linkable']])>
                                        @svg('heroicon-s-check-circle', 'mt-0.5 w-4 h-4 shrink-0 text-[color:var(--nx-success)]')
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm leading-snug">{{ $step['title'] }}</div>
                                            <div class="truncate text-xs text-[color:var(--nx-faint)]">{{ $step['parent'] }}</div>
                                        </div>
                                    </{{ $stepTag }}>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-[12px] border border-dashed border-[color:var(--nx-line-strong)] px-4 py-8 text-center text-sm text-[color:var(--nx-faint)]">Noch nichts erledigt</div>
                @endif
            </section>
        </div>
    @endif

    {{-- Weitere Inhalte --}}
    @foreach ($data['sections'] as $group)
        <section class="mt-10">
            <div class="mb-3 flex items-baseline gap-2">
                <h2 class="text-[15px] font-semibold">{{ $group['label'] }}</h2>
                <span class="text-sm text-[color:var(--nx-faint)]">{{ count($group['items']) }}</span>
            </div>
            <div class="overflow-hidden rounded-[12px] border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                @foreach ($group['items'] as $item)
                    @php
                        $rowTag = $item['has_detail'] ? 'a' : 'div';
                    @endphp
                    <{{ $rowTag }} @if ($item['has_detail']) href="{{ $link($group['slug'], $item['id']) }}" wire:navigate @endif wire:key="sec-{{ $group['slug'] }}-{{ $item['id'] }}"
                        @class(['flex items-center gap-3 px-4 py-3 text-sm', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $item['has_detail']])>
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
        <div class="mt-10 rounded-[12px] border border-dashed border-[color:var(--nx-line-strong)] px-6 py-16 text-center">
            <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-[color:var(--nx-bg)]">@svg('heroicon-o-sparkles', 'w-6 h-6 text-[color:var(--nx-faint)]')</span>
            <div class="mt-3 font-semibold">Hier entsteht gerade etwas</div>
            <div class="mt-1 text-sm text-[color:var(--nx-muted)]">Sobald es Neuigkeiten gibt, sehen Sie sie an dieser Stelle.</div>
        </div>
    @endif
</div>
