{{-- Generic rule-based metadata renderer for link items --}}
@php
    $metaParts = [];
    $linkType = $linkType ?? '';
    $fmtMin = fn($min) => intdiv($min, 60) . ':' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . 'h';

    $rules = resolve(\Platform\Organization\Services\EntityLinkRegistry::class)->allMetadataDisplayRules()[$linkType] ?? [];

    foreach ($rules as $rule) {
        if ($rule['format'] === 'expandable_children') continue;

        $val = $link[$rule['field']] ?? null;
        if ($val === null) continue;

        switch ($rule['format']) {
            case 'text':
                if (!$val) break;
                $text = e($val);
                if (!empty($rule['suffix'])) $text .= ' ' . e($rule['suffix']);
                if (!empty($rule['css_class'])) $text = '<span class="' . e($rule['css_class']) . '">' . $text . '</span>';
                $metaParts[] = $text;
                break;

            case 'prefixed_text':
                if (!$val) break;
                $metaParts[] = (!empty($rule['prefix']) ? e($rule['prefix']) . ' ' : '') . e($val);
                break;

            case 'time':
                if ($val > 0) $metaParts[] = $fmtMin($val);
                break;

            case 'count':
                if ($val > 0) {
                    $suffix = $rule['suffix'] ?? '';
                    if (!empty($rule['suffix_plural']) && $val > 1) $suffix = $rule['suffix_plural'];
                    $metaParts[] = $val . ($suffix ? ' ' . $suffix : '');
                }
                break;

            case 'count_ratio':
                if ($val > 0) {
                    $done = $link[$rule['done_field']] ?? 0;
                    $metaParts[] = $done . '/' . $val . (!empty($rule['suffix']) ? ' ' . $rule['suffix'] : '');
                }
                break;

            case 'percentage':
                if ($val > 0) {
                    $metaParts[] = $val . '%' . (!empty($rule['suffix']) ? ' ' . $rule['suffix'] : '');
                }
                break;

            case 'boolean_done':
                if ($val) $metaParts[] = '<span class="text-green-600">erledigt</span>';
                break;

            case 'boolean_active':
                if ($val === false) $metaParts[] = '<span class="text-amber-600">inaktiv</span>';
                break;

            case 'boolean_published':
                if ($val) $metaParts[] = '<span class="text-green-600">veröffentlicht</span>';
                break;

            case 'boolean_pinned':
                if ($val) $metaParts[] = 'angepinnt';
                break;

            case 'boolean_frog':
                if ($val) $metaParts[] = '<span class="text-green-700">🐸</span>';
                break;

            case 'badge':
                if ($val) $metaParts[] = e($val);
                break;
        }
    }
@endphp
@if(!empty($metaParts))
    <span class="inline-flex items-center gap-1.5 text-[10px] text-[var(--ui-muted)] flex-shrink-0">{!! implode(' · ', $metaParts) !!}</span>
@endif
