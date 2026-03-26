{{-- Rich metadata for link items --}}
@php
    $metaParts = [];
    $linkType = $linkType ?? '';
    if ($linkType === 'project') {
        if (($link['task_count'] ?? 0) > 0) {
            $metaParts[] = ($link['done_task_count'] ?? 0) . '/' . $link['task_count'] . ' Tasks';
        }
        if (($link['logged_minutes'] ?? 0) > 0) {
            $h = intdiv($link['logged_minutes'], 60);
            $m = $link['logged_minutes'] % 60;
            $metaParts[] = $h . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . 'h';
        }
        if ($link['done'] ?? false) {
            $metaParts[] = '<span class="text-green-600">erledigt</span>';
        }
    } elseif ($linkType === 'planner_task') {
        if ($link['priority'] ?? null) {
            $metaParts[] = e($link['priority']);
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
        if ($link['is_done'] ?? false) {
            $metaParts[] = '<span class="text-green-600">erledigt</span>';
        }
    }
@endphp
@if(!empty($metaParts))
    <span class="inline-flex items-center gap-1.5 text-[10px] text-[var(--ui-muted)] flex-shrink-0">{!! implode(' · ', $metaParts) !!}</span>
@endif
