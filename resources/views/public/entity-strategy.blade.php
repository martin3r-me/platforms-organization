<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Strategie – {{ $entity->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
        .page { max-width: 820px; margin: 0 auto; padding: 40px; }

        .header { border-bottom: 3px solid #6d28d9; padding-bottom: 16px; margin-bottom: 28px; }
        .header-eyebrow { font-size: 10px; text-transform: uppercase; letter-spacing: 3px; color: #7c3aed; font-weight: bold; }
        .header-title { font-size: 26px; font-weight: bold; color: #1e293b; margin-top: 6px; }
        .header-sub { font-size: 12px; color: #64748b; margin-top: 4px; }

        .empty { padding: 40px; text-align: center; color: #94a3b8; border: 1px dashed #cbd5e1; border-radius: 6px; }

        .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #6d28d9; margin: 28px 0 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }

        .mv-row { display: table; width: 100%; margin-bottom: 8px; }
        .mv-cell { display: table-cell; width: 50%; vertical-align: top; }
        .mv-cell.left { padding-right: 10px; }
        .mv-cell.right { padding-left: 10px; }
        .card { border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; background: #fafafa; }
        .card-label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; }
        .card-label.mission { color: #b45309; }
        .card-label.vision { color: #4338ca; }
        .card-title { font-size: 14px; font-weight: bold; color: #1e293b; margin: 3px 0; }
        .card-meta { font-size: 9px; color: #94a3b8; margin-bottom: 8px; }
        .prose { font-size: 11px; color: #334155; line-height: 1.5; }
        .prose p { margin-bottom: 6px; }
        .prose ul, .prose ol { margin: 0 0 6px 18px; }

        .forecast { border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 18px; margin-bottom: 18px; page-break-inside: avoid; }
        .forecast-head { border-bottom: 1px solid #ede9fe; padding-bottom: 8px; margin-bottom: 12px; }
        .forecast-title { font-size: 15px; font-weight: bold; color: #1e293b; }
        .forecast-date { font-size: 10px; color: #7c3aed; font-weight: bold; }

        .fa { margin-bottom: 12px; }
        .fa-title { font-size: 12px; font-weight: bold; color: #1e293b; }
        .fa-desc { font-size: 10px; color: #475569; margin: 2px 0 5px; line-height: 1.45; }
        .chip { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; margin: 0 4px 4px 0; }
        .chip.obstacle { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .chip.vision-image { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .milestone { font-size: 10px; color: #334155; padding: 2px 0 2px 12px; position: relative; }
        .milestone:before { content: "▸"; position: absolute; left: 0; color: #a78bfa; }
        .milestone .yr { color: #7c3aed; font-weight: bold; }

        table.tmap { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9px; }
        table.tmap th, table.tmap td { border: 1px solid #e2e8f0; padding: 5px 6px; vertical-align: top; text-align: left; }
        table.tmap th { background: #f5f3ff; color: #6d28d9; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; }
        table.tmap td.fa-col { background: #fafafa; font-weight: bold; color: #1e293b; width: 130px; }
        table.tmap .tm-item { display: block; padding: 1px 0; color: #334155; }

        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="header-eyebrow">Strategisches Zukunftsbild</div>
        <div class="header-title">{{ $entity->name }}</div>
        <div class="header-sub">{{ $entity->type?->name ?? 'Organisationseinheit' }}</div>
    </div>

    @if($strategy === null)
        <div class="empty">Für diesen Knoten ist noch kein strategisches Zukunftsbild hinterlegt.</div>
    @else
        {{-- Mission / Vision --}}
        @if(!empty($strategy['mission']) || !empty($strategy['vision']))
            <div class="section-title">Auftrag &amp; Zielbild</div>
            <div class="mv-row">
                <div class="mv-cell left">
                    @if(!empty($strategy['mission']))
                        <div class="card">
                            <div class="card-label mission">Mission</div>
                            <div class="card-title">{{ $strategy['mission']['title'] }}</div>
                            <div class="card-meta">v{{ $strategy['mission']['version'] }}@if($strategy['mission']['valid_from']) · gültig ab {{ $strategy['mission']['valid_from'] }}@endif</div>
                            @if(!empty($strategy['mission']['content']))
                                <div class="prose">{!! \Illuminate\Support\Str::markdown($strategy['mission']['content']) !!}</div>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="mv-cell right">
                    @if(!empty($strategy['vision']))
                        <div class="card">
                            <div class="card-label vision">Vision</div>
                            <div class="card-title">{{ $strategy['vision']['title'] }}</div>
                            <div class="card-meta">v{{ $strategy['vision']['version'] }}@if($strategy['vision']['valid_from']) · gültig ab {{ $strategy['vision']['valid_from'] }}@endif</div>
                            @if(!empty($strategy['vision']['content']))
                                <div class="prose">{!! \Illuminate\Support\Str::markdown($strategy['vision']['content']) !!}</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Fokusräume (entity-nativ) --}}
        @if(!empty($strategy['focus_areas']))
            <div class="section-title">Fokusräume</div>
            @foreach($strategy['focus_areas'] as $fa)
                <div class="fa">
                    <div class="fa-title">{{ $fa['title'] }}</div>
                    @if(!empty($fa['description']))
                        <div class="fa-desc">{{ $fa['description'] }}</div>
                    @endif
                    @foreach($fa['vision_images'] as $vi)
                        <span class="chip vision-image">🎯 {{ $vi['title'] }}</span>
                    @endforeach
                    @foreach($fa['obstacles'] as $ob)
                        <span class="chip obstacle">⚠ {{ $ob['title'] }}</span>
                    @endforeach
                    @foreach($fa['milestones'] as $m)
                        <div class="milestone">
                            @if($m['target_year'])<span class="yr">{{ $m['target_year'] }}@if($m['target_quarter']) Q{{ $m['target_quarter'] }}@endif</span> · @endif{{ $m['title'] }}
                        </div>
                    @endforeach
                </div>
            @endforeach

            {{-- Transformations-Map — über alle Fokusräume --}}
            @php $tmap = $strategy['transformation_map']; @endphp
            @if(!empty($tmap['years']))
                <table class="tmap">
                    <thead>
                        <tr>
                            <th>Fokusraum</th>
                            @foreach($tmap['years'] as $year)
                                <th>{{ $year }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($strategy['focus_areas'] as $fa)
                            <tr>
                                <td class="fa-col">{{ $fa['title'] }}</td>
                                @foreach($tmap['years'] as $year)
                                    <td>
                                        @foreach($tmap['grid'][$fa['id']][$year] ?? [] as $m)
                                            <span class="tm-item">{{ $m['title'] }}@if($m['target_quarter']) (Q{{ $m['target_quarter'] }})@endif</span>
                                        @endforeach
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif

        {{-- Regnosen (Rückblicke aus der Zukunft) --}}
        @if(!empty($strategy['forecasts']))
            <div class="section-title">Regnosen</div>
            @foreach($strategy['forecasts'] as $forecast)
                <div class="forecast">
                    <div class="forecast-head">
                        <div class="forecast-title">{{ $forecast['title'] }}</div>
                        @if($forecast['target_date'])
                            <div class="forecast-date">Zielhorizont: {{ $forecast['target_date'] }}</div>
                        @endif
                    </div>

                    @if(!empty($forecast['content']))
                        <div class="prose">{!! \Illuminate\Support\Str::markdown($forecast['content']) !!}</div>
                    @endif
                </div>
            @endforeach
        @endif
    @endif

    <div class="footer">
        {{ $entity->name }} · Strategie-Onepager · geteilt über die Plattform
    </div>
</div>
</body>
</html>
