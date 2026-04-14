<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Prozessausweis – {{ $data['process']['name'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
        .page { padding: 36px 40px; }

        /* Header */
        .header { border-bottom: 3px solid #1e293b; padding-bottom: 16px; margin-bottom: 24px; }
        .header-title { font-size: 26px; font-weight: bold; letter-spacing: 4px; color: #1e293b; text-transform: uppercase; }
        .header-sub { font-size: 15px; color: #475569; margin-top: 6px; }
        .header-code { font-size: 11px; color: #94a3b8; font-family: monospace; margin-top: 2px; }

        /* Meta row */
        .meta-row { display: table; width: 100%; margin-bottom: 24px; }
        .meta-cell { display: table-cell; width: 25%; padding: 10px 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
        .meta-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: bold; }
        .meta-value { font-size: 12px; font-weight: bold; color: #1e293b; margin-top: 3px; }

        /* Efficiency scale */
        .efficiency-section { margin-bottom: 24px; }
        .efficiency-title { font-size: 12px; font-weight: bold; color: #1e293b; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .scale-table { width: 100%; border-collapse: collapse; }
        .scale-table td { padding: 0; height: 36px; text-align: center; font-size: 13px; font-weight: bold; color: #fff; }
        .scale-arrow { width: 100%; height: 20px; position: relative; margin-top: 2px; }
        .efficiency-result { margin-top: 12px; padding: 10px 16px; border-radius: 4px; display: inline-block; }
        .efficiency-result-class { font-size: 36px; font-weight: bold; }
        .efficiency-result-label { font-size: 12px; margin-left: 10px; }
        .efficiency-result-percent { font-size: 14px; color: #64748b; margin-left: 14px; }

        /* KPI grid */
        .kpi-grid { display: table; width: 100%; margin-bottom: 24px; }
        .kpi-cell { display: table-cell; width: 25%; padding: 12px; text-align: center; border: 1px solid #e2e8f0; background: #fff; }
        .kpi-value { font-size: 26px; font-weight: bold; color: #1e293b; }
        .kpi-label { font-size: 9px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; }
        .kpi-detail { font-size: 9px; color: #64748b; margin-top: 3px; }

        /* Bars */
        .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #1e293b; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }
        .bar-row { margin-bottom: 10px; }
        .bar-label { font-size: 10px; color: #475569; margin-bottom: 3px; }
        .bar-track { width: 100%; height: 18px; background: #f1f5f9; border-radius: 3px; overflow: hidden; }
        .bar-fill { height: 18px; border-radius: 3px; min-width: 2px; }
        .bar-info { font-size: 9px; color: #64748b; margin-top: 2px; }

        /* Two columns */
        .two-col { display: table; width: 100%; margin-bottom: 24px; }
        .col-left { display: table-cell; width: 48%; vertical-align: top; padding-right: 16px; }
        .col-right { display: table-cell; width: 48%; vertical-align: top; padding-left: 16px; }

        /* Action items */
        .action-badge { display: inline-block; padding: 4px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; margin-right: 6px; margin-bottom: 6px; }

        /* Page 2 header */
        .page2-header { border-bottom: 2px solid #1e293b; padding-bottom: 10px; margin-bottom: 20px; }
        .page2-title { font-size: 14px; font-weight: bold; color: #1e293b; text-transform: uppercase; letter-spacing: 1px; }
        .page2-sub { font-size: 10px; color: #94a3b8; margin-top: 2px; }

        /* Steps table */
        .steps-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .steps-table th { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: bold; text-align: left; padding: 6px 6px; border-bottom: 2px solid #e2e8f0; }
        .steps-table td { font-size: 10px; color: #475569; padding: 5px 6px; border-bottom: 1px solid #f1f5f9; }
        .steps-table .pos { font-family: monospace; color: #94a3b8; width: 24px; text-align: right; }
        .steps-table .name { color: #1e293b; font-weight: 500; }
        .badge-sm { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }

        /* Improvements table */
        .imp-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .imp-table th { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: bold; text-align: left; padding: 6px 6px; border-bottom: 2px solid #e2e8f0; }
        .imp-table td { font-size: 10px; color: #475569; padding: 5px 6px; border-bottom: 1px solid #f1f5f9; }

        /* Footer */
        .footer { border-top: 2px solid #1e293b; padding-top: 10px; margin-top: 28px; font-size: 9px; color: #94a3b8; }
        .footer-row { display: table; width: 100%; }
        .footer-left { display: table-cell; width: 50%; }
        .footer-right { display: table-cell; width: 50%; text-align: right; }
        .checksum { font-family: monospace; font-size: 8px; color: #cbd5e1; word-break: break-all; }
    </style>
</head>
<body>
<div class="page">
    {{-- Header --}}
    <div class="header">
        <div class="header-title">Prozessausweis</div>
        <div class="header-sub">{{ $data['process']['name'] }}</div>
        @if($data['process']['code'])
            <div class="header-code">{{ $data['process']['code'] }} &middot; Version {{ $data['process']['version'] }}</div>
        @else
            <div class="header-code">Version {{ $data['process']['version'] }}</div>
        @endif
    </div>

    {{-- Description --}}
    @if($data['process']['description'])
        <div style="margin-bottom: 20px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px;">
            <div style="font-size: 10px; color: #475569; line-height: 1.5;">{{ \Illuminate\Support\Str::limit($data['process']['description'], 400) }}</div>
        </div>
    @endif

    {{-- Meta --}}
    <div class="meta-row">
        <div class="meta-cell">
            <div class="meta-label">Owner</div>
            <div class="meta-value">{{ $data['process']['owner'] ?? '–' }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">VSM System</div>
            <div class="meta-value">{{ $data['process']['vsm_system'] ?? '–' }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Status</div>
            <div class="meta-value">{{ ucfirst($data['process']['status']) }}</div>
        </div>
        <div class="meta-cell">
            <div class="meta-label">Team</div>
            <div class="meta-value">{{ $data['process']['team'] ?? '–' }}</div>
        </div>
    </div>

    {{-- Efficiency Scale --}}
    <div class="efficiency-section">
        <div class="efficiency-title">Effizienzklasse</div>

        @php
            $scaleClasses = [
                ['class' => 'A+', 'color' => '#16a34a', 'width' => '10%'],
                ['class' => 'A',  'color' => '#22c55e', 'width' => '11%'],
                ['class' => 'B',  'color' => '#84cc16', 'width' => '12%'],
                ['class' => 'C',  'color' => '#eab308', 'width' => '13%'],
                ['class' => 'D',  'color' => '#f97316', 'width' => '14%'],
                ['class' => 'E',  'color' => '#ef4444', 'width' => '14%'],
                ['class' => 'F',  'color' => '#dc2626', 'width' => '13%'],
                ['class' => 'G',  'color' => '#991b1b', 'width' => '13%'],
            ];
            $currentClass = $data['efficiency_class']['class'];
        @endphp

        <table class="scale-table">
            <tr>
                @foreach($scaleClasses as $sc)
                    <td style="background: {{ $sc['color'] }}; width: {{ $sc['width'] }};{{ $sc['class'] === $currentClass ? ' border: 3px solid #1e293b; font-size: 16px;' : '' }}">
                        {{ $sc['class'] }}
                    </td>
                @endforeach
            </tr>
        </table>

        <div class="efficiency-result" style="background: {{ $data['efficiency_class']['color'] }}20; border: 2px solid {{ $data['efficiency_class']['color'] }};">
            <span class="efficiency-result-class" style="color: {{ $data['efficiency_class']['color'] }};">{{ $data['efficiency_class']['class'] }}</span>
            <span class="efficiency-result-label" style="color: {{ $data['efficiency_class']['color'] }};">{{ $data['efficiency_class']['label'] }}</span>
            <span class="efficiency-result-percent">({{ $data['efficiency_percent'] }}%)</span>
        </div>
    </div>

    {{-- KPI Grid --}}
    <div class="kpi-grid">
        <div class="kpi-cell">
            <div class="kpi-label">Steps</div>
            <div class="kpi-value">{{ $data['kpis']['total_steps'] }}</div>
            <div class="kpi-detail">Prozessschritte</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Durchlaufzeit</div>
            <div class="kpi-value">{{ $data['kpis']['lead_time'] }}</div>
            <div class="kpi-detail">Min. ({{ $data['kpis']['total_duration'] }} Arbeit + {{ $data['kpis']['total_wait'] }} Warten)</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">Effizienz</div>
            <div class="kpi-value" style="color: {{ $data['efficiency_class']['color'] }};">{{ $data['efficiency_percent'] }}%</div>
            <div class="kpi-detail">Anteil aktiver Arbeit</div>
        </div>
        <div class="kpi-cell">
            <div class="kpi-label">LLM-Quote</div>
            <div class="kpi-value" style="color: {{ $data['kpis']['llm_quote'] >= 70 ? '#16a34a' : ($data['kpis']['llm_quote'] >= 30 ? '#3b82f6' : '#64748b') }};">{{ $data['kpis']['llm_quote'] }}%</div>
            <div class="kpi-detail">{{ $data['kpis']['llm_count'] }} von {{ $data['kpis']['total_steps'] }} Steps</div>
        </div>
    </div>

    {{-- Two columns: COREFIT + Automation --}}
    <div class="two-col">
        <div class="col-left">
            <div class="section-title">COREFIT-Verteilung</div>
            @php
                $corefitColors = ['core' => '#22c55e', 'context' => '#eab308', 'no_fit' => '#ef4444'];
                $corefitLabels = ['core' => 'Core', 'context' => 'Context', 'no_fit' => 'No Fit'];
            @endphp
            @foreach(['core', 'context', 'no_fit'] as $cf)
                <div class="bar-row">
                    <div class="bar-label">{{ $corefitLabels[$cf] }} ({{ $data['corefit'][$cf]['count'] }})</div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: {{ max(1, $data['corefit'][$cf]['percent']) }}%; background: {{ $corefitColors[$cf] }};"></div>
                    </div>
                    <div class="bar-info">{{ $data['corefit'][$cf]['percent'] }}% &middot; {{ $data['corefit'][$cf]['minutes'] }} Min.</div>
                </div>
            @endforeach
        </div>
        <div class="col-right">
            <div class="section-title">Automatisierungsgrad</div>
            @php
                $autoColors = ['human' => '#94a3b8', 'llm_assisted' => '#3b82f6', 'llm_autonomous' => '#22c55e', 'hybrid' => '#eab308'];
                $autoLabels = ['human' => 'Human', 'llm_assisted' => 'LLM-Assisted', 'llm_autonomous' => 'LLM-Autonomous', 'hybrid' => 'Hybrid'];
            @endphp
            @foreach(['human', 'llm_assisted', 'llm_autonomous', 'hybrid'] as $al)
                <div class="bar-row">
                    <div class="bar-label">{{ $autoLabels[$al] }} ({{ $data['automation'][$al]['count'] }})</div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: {{ max(1, $data['automation'][$al]['percent']) }}%; background: {{ $autoColors[$al] }};"></div>
                    </div>
                    <div class="bar-info">{{ $data['automation'][$al]['percent'] }}% &middot; {{ $data['automation'][$al]['minutes'] }} Min.</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Handlungsbedarf --}}
    <div style="margin-bottom: 24px;">
        <div class="section-title">Handlungsbedarf</div>
        @if($data['kpis']['total_steps'] > 0)
            @if($data['action_items']['eliminate'] > 0)
                <span class="action-badge" style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;">{{ $data['action_items']['eliminate'] }} eliminieren</span>
            @endif
            @if($data['action_items']['automate'] > 0)
                <span class="action-badge" style="background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa;">{{ $data['action_items']['automate'] }} automatisieren</span>
            @endif
            @if($data['action_items']['invest'] > 0)
                <span class="action-badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">{{ $data['action_items']['invest'] }} investieren</span>
            @endif
            @if($data['action_items']['optimal'] > 0)
                <span class="action-badge" style="background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;">{{ $data['action_items']['optimal'] }} optimal/gut</span>
            @endif
            @if(array_sum($data['action_items']) === 0)
                <span style="font-size: 9px; color: #94a3b8;">Keine Daten</span>
            @endif
        @else
            <span style="font-size: 9px; color: #94a3b8;">Keine Prozessschritte vorhanden</span>
        @endif
    </div>

    {{-- COREFIT Analysis Texts --}}
    @php
        $analysisBlocks = [
            ['key' => 'target_description',    'label' => 'Zielbeschreibung',        'icon' => 'Ziel'],
            ['key' => 'value_proposition',     'label' => 'Wertbeitrag',             'icon' => 'Wert'],
            ['key' => 'cost_analysis',         'label' => 'Kostenanalyse',           'icon' => 'Kosten'],
            ['key' => 'risk_assessment',       'label' => 'Risikobewertung',         'icon' => 'Risiko'],
            ['key' => 'improvement_levers',    'label' => 'Verbesserungshebel',      'icon' => 'Hebel'],
            ['key' => 'action_plan',           'label' => 'Maßnahmenplan',           'icon' => 'Plan'],
            ['key' => 'standardization_notes', 'label' => 'Standardisierung',        'icon' => 'Std.'],
        ];
        $hasAnyText = collect($analysisBlocks)->contains(fn ($b) => !empty($data['process'][$b['key']]));
    @endphp
    @if($hasAnyText)
        <div style="page-break-before: always; padding-top: 36px;">
            <div class="page2-header">
                <div class="page2-title">Analyse & Bewertung</div>
                <div class="page2-sub">{{ $data['process']['name'] }} &middot; COREFIT-Analyse</div>
            </div>

            @foreach($analysisBlocks as $block)
                @if(!empty($data['process'][$block['key']]))
                    <div style="margin-bottom: 14px;">
                        <div style="font-size: 10px; font-weight: bold; color: #1e293b; margin-bottom: 4px; padding-bottom: 3px; border-bottom: 1px solid #f1f5f9;">
                            <span style="display: inline-block; padding: 1px 5px; background: #f1f5f9; border-radius: 2px; font-size: 8px; color: #64748b; margin-right: 6px;">{{ $block['icon'] }}</span>
                            {{ $block['label'] }}
                        </div>
                        <div style="font-size: 10px; color: #475569; line-height: 1.5; padding-left: 4px;">{{ \Illuminate\Support\Str::limit($data['process'][$block['key']], 600) }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Steps List --}}
    @if(count($data['steps_list']) > 0)
        <div style="page-break-before: always; padding-top: 36px; margin-bottom: 24px;">
            {{-- Page 2 Header --}}
            <div class="page2-header">
                <div class="page2-title">Prozessschritte</div>
                <div class="page2-sub">{{ $data['process']['name'] }} &middot; {{ count($data['steps_list']) }} Schritte &middot; {{ $data['kpis']['lead_time'] }} Min. Durchlaufzeit</div>
            </div>

            <table class="steps-table">
                <thead>
                    <tr>
                        <th style="width: 24px;">#</th>
                        <th>Schritt</th>
                        <th style="width: 55px;">COREFIT</th>
                        <th style="width: 75px;">Automation</th>
                        <th style="width: 45px; text-align: right;">Dauer</th>
                        <th style="width: 45px; text-align: right;">Warten</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $cfBadge = ['core' => ['bg' => '#dcfce7', 'color' => '#15803d', 'label' => 'Core'], 'context' => ['bg' => '#fef9c3', 'color' => '#a16207', 'label' => 'Ctx'], 'no_fit' => ['bg' => '#fef2f2', 'color' => '#b91c1c', 'label' => 'NF']];
                        $alBadge = ['human' => ['bg' => '#f1f5f9', 'color' => '#64748b', 'label' => 'Human'], 'llm_assisted' => ['bg' => '#dbeafe', 'color' => '#1d4ed8', 'label' => 'Assisted'], 'llm_autonomous' => ['bg' => '#dcfce7', 'color' => '#15803d', 'label' => 'Autonom'], 'hybrid' => ['bg' => '#fef9c3', 'color' => '#a16207', 'label' => 'Hybrid']];
                    @endphp
                    @foreach($data['steps_list'] as $step)
                        <tr>
                            <td class="pos">{{ $step['position'] }}</td>
                            <td class="name">{{ \Illuminate\Support\Str::limit($step['name'], 60) }}</td>
                            <td>
                                @php $cb = $cfBadge[$step['corefit']] ?? $cfBadge['core']; @endphp
                                <span class="badge-sm" style="background: {{ $cb['bg'] }}; color: {{ $cb['color'] }};">{{ $cb['label'] }}</span>
                            </td>
                            <td>
                                @php $ab = $alBadge[$step['automation']] ?? $alBadge['human']; @endphp
                                <span class="badge-sm" style="background: {{ $ab['bg'] }}; color: {{ $ab['color'] }};">{{ $ab['label'] }}</span>
                            </td>
                            <td style="text-align: right;">{{ $step['duration'] ?? '–' }}</td>
                            <td style="text-align: right; color: #94a3b8;">{{ $step['wait'] ?? '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Improvements --}}
    @if(count($data['improvements_list']) > 0)
        <div style="margin-bottom: 24px; margin-top: 12px;">
            <div class="section-title">Verbesserungen ({{ count($data['improvements_list']) }})</div>
            <table class="imp-table">
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th style="width: 70px;">Kategorie</th>
                        <th style="width: 55px;">Priorität</th>
                        <th style="width: 65px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $catLabels = ['cost' => 'Kosten', 'quality' => 'Qualität', 'speed' => 'Speed', 'risk' => 'Risiko', 'standardization' => 'Standard'];
                        $prioColors = ['critical' => ['bg' => '#fef2f2', 'color' => '#b91c1c'], 'high' => ['bg' => '#fff7ed', 'color' => '#c2410c'], 'medium' => ['bg' => '#fef9c3', 'color' => '#a16207'], 'low' => ['bg' => '#f1f5f9', 'color' => '#64748b']];
                        $statusLabels = ['identified' => 'Erkannt', 'planned' => 'Geplant', 'in_progress' => 'In Arbeit', 'completed' => 'Erledigt', 'rejected' => 'Abgelehnt'];
                    @endphp
                    @foreach($data['improvements_list'] as $imp)
                        <tr>
                            <td style="color: #1e293b; font-weight: 500;">{{ \Illuminate\Support\Str::limit($imp['title'], 65) }}</td>
                            <td>{{ $catLabels[$imp['category']] ?? $imp['category'] }}</td>
                            <td>
                                @php $pc = $prioColors[$imp['priority']] ?? $prioColors['medium']; @endphp
                                <span class="badge-sm" style="background: {{ $pc['bg'] }}; color: {{ $pc['color'] }};">{{ ucfirst($imp['priority']) }}</span>
                            </td>
                            <td>
                                <span style="font-size: 9px;">{{ $statusLabels[$imp['status']] ?? $imp['status'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <div class="footer-row">
            <div class="footer-left">
                Erstellt am {{ $data['meta']['generated_at_formatted'] }}
            </div>
            <div class="footer-right">
                Prozessausweis &middot; {{ $data['process']['team'] ?? '' }}
            </div>
        </div>
        <div class="checksum" style="margin-top: 4px;">
            Prüfsumme: {{ $data['meta']['checksum'] }}
        </div>
    </div>
</div>
</body>
</html>
