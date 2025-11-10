<x-ui-table-row compact="true">
    <x-ui-table-cell compact="true">
        <div class="flex items-center">
            @if($entity->type->icon)
                @php
                    $iconName = str_replace('heroicons.', '', $entity->type->icon);
                    // Map non-existent icons to valid alternatives
                    $iconMap = [
                        'user-check' => 'user',
                        'folder-kanban' => 'folder',
                        'briefcase-globe' => 'briefcase',
                        'server-cog' => 'server',
                        'package-check' => 'package',
                        'badge-check' => 'badge',
                    ];
                    $iconName = $iconMap[$iconName] ?? $iconName;
                @endphp
                @svg('heroicon-o-' . $iconName, 'w-5 h-5 text-[var(--ui-muted)] mr-3')
            @endif
            <div>
                <div class="font-medium">
                    <a href="{{ route('organization.entities.show', $entity) }}" class="link">{{ $entity->name }}</a>
                </div>
                @if($entity->code)
                    <div class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $entity->code }}</div>
                @endif
                @if($entity->description)
                    <div class="text-xs text-[var(--ui-muted)] mt-0.5">{{ Str::limit($entity->description, 50) }}</div>
                @endif
            </div>
        </div>
    </x-ui-table-cell>
    <x-ui-table-cell compact="true">
        <div class="text-sm">{{ $entity->type->name }}</div>
        <div class="text-xs text-[var(--ui-muted)]">{{ $entity->type->group->name }}</div>
    </x-ui-table-cell>
    <x-ui-table-cell compact="true">
        @if($entity->vsmSystem)
            <x-ui-badge variant="secondary" size="sm">{{ $entity->vsmSystem->name }}</x-ui-badge>
        @else
            <span class="text-xs text-[var(--ui-muted)]">–</span>
        @endif
    </x-ui-table-cell>
    <x-ui-table-cell compact="true">
        @if($entity->costCenter)
            <x-ui-badge variant="primary" size="sm" title="{{ $entity->costCenter->name }}">{{ $entity->costCenter->code }}</x-ui-badge>
        @else
            <span class="text-xs text-[var(--ui-muted)]">–</span>
        @endif
    </x-ui-table-cell>
    <x-ui-table-cell compact="true">
        @if($entity->parent)
            <div class="text-sm">{{ $entity->parent->name }}</div>
            <div class="text-xs text-[var(--ui-muted)]">{{ $entity->parent->type->name }}</div>
        @else
            <span class="text-xs text-[var(--ui-muted)]">–</span>
        @endif
    </x-ui-table-cell>
    <x-ui-table-cell compact="true">
        @php
            $relationsFrom = $entity->relationsFrom->take(2);
            $relationsTo = $entity->relationsTo->take(2);
            $relationsFromCount = $entity->relationsFrom->count();
            $relationsToCount = $entity->relationsTo->count();
            $totalRelations = $relationsFromCount + $relationsToCount;
        @endphp
        @if($totalRelations > 0)
            <div class="space-y-0.5">
                @if($relationsToCount > 0)
                    <div class="flex items-start gap-0.5">
                        <span class="text-[0.55rem] text-[var(--ui-muted)] mt-0.5 flex-shrink-0">←</span>
                        <div class="flex-1 min-w-0">
                            @foreach($relationsTo as $rel)
                                <div class="text-[0.55rem] leading-tight truncate" title="{{ $rel->fromEntity->name }} ({{ $rel->relationType->name ?? 'Unbekannt' }})">
                                    <span class="text-[var(--ui-secondary)]">{{ $rel->fromEntity->name }}</span>
                                    @if($rel->relationType)
                                        <span class="text-[var(--ui-muted)]"> · </span>
                                        <span class="text-[var(--ui-muted)]">{{ $rel->relationType->name }}</span>
                                    @endif
                                </div>
                            @endforeach
                            @if($relationsToCount > 2)
                                <div class="text-[0.5rem] text-[var(--ui-muted)] mt-0.5">+{{ $relationsToCount - 2 }}</div>
                            @endif
                        </div>
                    </div>
                @endif
                @if($relationsFromCount > 0)
                    <div class="flex items-start gap-0.5">
                        <span class="text-[0.55rem] text-[var(--ui-muted)] mt-0.5 flex-shrink-0">→</span>
                        <div class="flex-1 min-w-0">
                            @foreach($relationsFrom as $rel)
                                <div class="text-[0.55rem] leading-tight truncate" title="{{ $rel->relationType->name ?? 'Unbekannt' }}: {{ $rel->toEntity->name }}">
                                    @if($rel->relationType)
                                        <span class="text-[var(--ui-muted)]">{{ $rel->relationType->name }}</span>
                                        <span class="text-[var(--ui-muted)]"> · </span>
                                    @endif
                                    <span class="text-[var(--ui-secondary)]">{{ $rel->toEntity->name }}</span>
                                </div>
                            @endforeach
                            @if($relationsFromCount > 2)
                                <div class="text-[0.5rem] text-[var(--ui-muted)] mt-0.5">+{{ $relationsFromCount - 2 }}</div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @else
            <span class="text-xs text-[var(--ui-muted)]">–</span>
        @endif
    </x-ui-table-cell>
    <x-ui-table-cell compact="true">
        @if($entity->is_active)
            <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
        @else
            <x-ui-badge variant="danger" size="sm">Inaktiv</x-ui-badge>
        @endif
    </x-ui-table-cell>
    <x-ui-table-cell compact="true">
        <span class="text-xs text-[var(--ui-muted)]">{{ $entity->created_at->format('d.m.Y') }}</span>
    </x-ui-table-cell>
</x-ui-table-row>

