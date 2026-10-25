{{-- Kunden-Tor · Aktivitätsstrom nach Tagen: Arbeit, erledigte/neue Schritte, Canvas-Arbeit, Statuswechsel, Zahlungen.
     Daten: $events (EntityShareService::pageData()['activity']), $limit (null = alle). Neues seit dem letzten Besuch ist markiert. --}}
@php
    $sinceAt = $data['since']['at'] ?? null;
    $list = collect($events)->when($limit, fn ($c) => $c->take($limit));
    $kinds = [
        'work' => ['heroicon-s-clock', 'var(--nx-tone-violet)'],
        'done' => ['heroicon-s-check-circle', 'var(--nx-success)'],
        'canvas' => ['heroicon-s-squares-2x2', 'var(--nx-info)'],
        'planned' => ['heroicon-s-plus-circle', 'var(--nx-faint)'],
        'state' => ['heroicon-s-flag', 'var(--nx-warning)'],
        'payment' => ['heroicon-s-banknotes', 'var(--nx-success)'],
    ];
@endphp
@if ($list->isEmpty())
    <div class="rounded-[12px] border border-dashed border-[color:var(--nx-line-strong)] px-4 py-8 text-center text-sm text-[color:var(--nx-faint)]">In den letzten 60 Tagen ist nichts eingetragen.</div>
@else
    <div class="gp-card px-4 py-2">
        @foreach ($list->groupBy(fn ($e) => $dayLabel($e['at'])) as $day => $dayEvents)
            <div @class(['py-2.5', 'border-t border-[color:var(--nx-line)]' => ! $loop->first])>
                <div class="mb-1.5 flex items-center justify-between text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">
                    <span>{{ $day }}</span>
                    @if (($m = $dayEvents->where('kind', 'work')->sum('minutes')) > 0)
                        <span class="normal-case tracking-normal tabular-nums">{{ $hours($m) }} gearbeitet</span>
                    @endif
                </div>
                @foreach ($dayEvents as $e)
                    @php
                        [$icon, $color] = $kinds[$e['kind']] ?? ['heroicon-s-information-circle', 'var(--nx-faint)'];
                        $href = $e['href'] ?? null;
                        $tag = $href ? 'a' : 'div';
                        $isNew = $sinceAt && $e['at']->gt($sinceAt);
                    @endphp
                    <{{ $tag }} @if ($href) href="{{ $pageLink($href[0], $href[1], $href[2] ?? null) }}" wire:navigate @endif
                        @class(['-mx-2 flex items-start gap-3 rounded-[6px] px-2 py-1.5', 'transition-colors hover:bg-[color:var(--nx-hover)]' => $href])>
                        @if ($e['kind'] === 'work')
                            <span class="mt-px">{!! $person($e['person'], 18) !!}</span>
                        @else
                            <span class="mt-0.5 shrink-0" style="color: {{ $color }}">@svg($icon, 'w-4 h-4')</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="text-sm leading-snug">
                                @if ($e['kind'] === 'work')
                                    <span class="font-medium">{{ $e['person'] }}</span>
                                    <span class="text-[color:var(--nx-muted)]">· {{ $hours($e['minutes']) }} ·</span>
                                    {{ $e['title'] }}
                                @elseif ($e['kind'] === 'payment')
                                    <span class="font-medium">{{ $e['title'] }}</span>
                                    <span class="tabular-nums text-[color:var(--nx-success)]">{{ $money($e['amount'], $e['currency'] ?? 'EUR') }}</span>
                                @else
                                    {{ $e['title'] }}
                                @endif
                            </div>
                            @if (! empty($e['text']))
                                <div @class(['mt-0.5 text-[13px] leading-snug text-[color:var(--nx-muted)]', 'whitespace-pre-line' => $e['kind'] === 'work'])>{{ $e['text'] }}</div>
                            @endif
                            @if (! empty($e['parent']))
                                <div class="mt-0.5 truncate text-xs text-[color:var(--nx-faint)]">{{ $e['parent'] }}</div>
                            @endif
                        </div>
                        @if ($isNew)
                            <span class="mt-1.5 gp-dot-new" title="Neu seit Ihrem letzten Besuch"></span>
                        @elseif ($e['kind'] !== 'work' && ! $e['at']->isMidnight())
                            <span class="mt-0.5 shrink-0 text-[11px] tabular-nums text-[color:var(--nx-faint)]">{{ $de($e['at'], 'HH:mm') }}</span>
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
        @endforeach
    </div>
@endif
