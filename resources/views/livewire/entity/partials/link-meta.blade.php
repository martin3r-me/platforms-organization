{{-- Rich metadata for link items --}}
@php
    $metaParts = [];
    $linkType = $linkType ?? '';

    // Helper: format minutes as H:MMh
    $fmtMin = fn($min) => intdiv($min, 60) . ':' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . 'h';

    if ($linkType === 'project') {
        if (($link['task_count'] ?? 0) > 0) {
            $metaParts[] = ($link['done_task_count'] ?? 0) . '/' . $link['task_count'] . ' Tasks';
        }
        if (($link['logged_minutes'] ?? 0) > 0) {
            $metaParts[] = $fmtMin($link['logged_minutes']);
        }
        if ($link['done'] ?? false) {
            $metaParts[] = '<span class="text-green-600">erledigt</span>';
        }
    } elseif ($linkType === 'planner_task') {
        if ($link['priority'] ?? null) {
            $metaParts[] = e($link['priority']);
        }
        if (($link['logged_minutes'] ?? 0) > 0) {
            $metaParts[] = $fmtMin($link['logged_minutes']);
        }
        if ($link['due_date'] ?? null) {
            $metaParts[] = e($link['due_date']);
        }
        if ($link['is_done'] ?? false) {
            $metaParts[] = '<span class="text-green-600">erledigt</span>';
        }
    } elseif ($linkType === 'helpdesk_ticket') {
        if ($link['priority'] ?? null) {
            $metaParts[] = e($link['priority']);
        }
        if ($link['escalation_level'] ?? null) {
            $metaParts[] = '<span class="text-red-600">' . e($link['escalation_level']) . '</span>';
        }
        if (($link['story_points'] ?? null)) {
            $metaParts[] = e($link['story_points']) . ' SP';
        }
        if ($link['due_date'] ?? null) {
            $metaParts[] = e($link['due_date']);
        }
        if (($link['escalation_count'] ?? 0) > 0) {
            $metaParts[] = $link['escalation_count'] . ' Eskalation' . ($link['escalation_count'] > 1 ? 'en' : '');
        }
        if ($link['is_done'] ?? false) {
            $metaParts[] = '<span class="text-green-600">erledigt</span>';
        }
    } elseif ($linkType === 'helpdesk_board') {
        if (($link['ticket_count'] ?? 0) > 0) {
            $metaParts[] = $link['ticket_count'] . ' Tickets';
        }
    } elseif (in_array($linkType, ['canvas', 'bmc_canvas', 'pc_canvas'])) {
        if ($link['status'] ?? null) {
            $metaParts[] = e($link['status']);
        }
        if (($link['block_count'] ?? 0) > 0) {
            $metaParts[] = $link['block_count'] . ' Blocks';
        }
    } elseif ($linkType === 'okr') {
        if (($link['objective_count'] ?? 0) > 0) {
            $metaParts[] = $link['objective_count'] . ' Objectives';
        }
        if (($link['cycle_count'] ?? 0) > 0) {
            $metaParts[] = $link['cycle_count'] . ' Zyklen';
        }
        if (($link['performance_score'] ?? null) !== null) {
            $metaParts[] = $link['performance_score'] . '%';
        }
    } elseif ($linkType === 'notes_note') {
        if ($link['is_pinned'] ?? false) {
            $metaParts[] = 'angepinnt';
        }
        if ($link['is_done'] ?? false) {
            $metaParts[] = '<span class="text-green-600">erledigt</span>';
        }
    } elseif ($linkType === 'slides_presentation') {
        if (($link['slide_count'] ?? 0) > 0) {
            $metaParts[] = $link['slide_count'] . ' Folien';
        }
        if ($link['is_published'] ?? false) {
            $metaParts[] = '<span class="text-green-600">veröffentlicht</span>';
        }
    } elseif ($linkType === 'sheets_spreadsheet') {
        if (($link['worksheet_count'] ?? 0) > 0) {
            $metaParts[] = $link['worksheet_count'] . ' Blätter';
        }
    } elseif ($linkType === 'rec_applicant') {
        if ($link['applied_at'] ?? null) {
            $metaParts[] = 'beworben ' . e($link['applied_at']);
        }
        if (($link['posting_count'] ?? 0) > 0) {
            $metaParts[] = $link['posting_count'] . ' Stellen';
        }
        if (($link['progress'] ?? 0) > 0) {
            $metaParts[] = $link['progress'] . '% Fortschritt';
        }
        if (!($link['is_active'] ?? true)) {
            $metaParts[] = '<span class="text-amber-600">inaktiv</span>';
        }
    } elseif ($linkType === 'rec_position') {
        if (($link['posting_count'] ?? 0) > 0) {
            $metaParts[] = $link['posting_count'] . ' Ausschreibungen';
        }
        if (!($link['is_active'] ?? true)) {
            $metaParts[] = '<span class="text-amber-600">inaktiv</span>';
        }
    } elseif ($linkType === 'hcm_employee') {
        if ($link['employee_number'] ?? null) {
            $metaParts[] = '#' . e($link['employee_number']);
        }
        if (!($link['is_active'] ?? true)) {
            $metaParts[] = '<span class="text-amber-600">inaktiv</span>';
        }
    }
@endphp
@if(!empty($metaParts))
    <span class="inline-flex items-center gap-1.5 text-[10px] text-[var(--ui-muted)] flex-shrink-0">{!! implode(' · ', $metaParts) !!}</span>
@endif
