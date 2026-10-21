{{-- Zweite Sidebar der Einstellungen: alle Kategorien an einer Stelle.
     Die Haupt-Sidebar hat dafür nur einen Eintrag „Einstellungen".

     Erwartet: $aktiv — Schlüssel der offenen Kategorie.

     Eigenes Markup statt x-ui-sidebar-item: Das hängt an der Alpine-Variable
     „collapsed" der Haupt-Sidebar, die es in der Seiten-Sidebar nicht gibt. --}}
@php
    $gruppen = [
        'Struktur' => [
            ['key' => 'entity-types',       'label' => 'Einheitstypen',          'icon' => 'heroicon-o-cube',                   'route' => 'organization.settings.entity-types.index'],
            ['key' => 'entity-type-groups', 'label' => 'Typ-Gruppen',            'icon' => 'heroicon-o-rectangle-stack',        'route' => 'organization.settings.entity-type-groups.index'],
            ['key' => 'relation-types',     'label' => 'Beziehungstypen',        'icon' => 'heroicon-o-link',                   'route' => 'organization.settings.relation-types.index'],
        ],
        'Schnittstellen' => [
            ['key' => 'interlink-categories', 'label' => 'Kategorien',           'icon' => 'heroicon-o-tag',                    'route' => 'organization.settings.interlink-categories.index'],
            ['key' => 'interlink-types',      'label' => 'Typen',                'icon' => 'heroicon-o-arrows-right-left',      'route' => 'organization.settings.interlink-types.index'],
        ],
        'Steuerung' => [
            ['key' => 'signal-definitions', 'label' => 'Signal-Definitionen',    'icon' => 'heroicon-o-adjustments-horizontal', 'route' => 'organization.settings.signal-definitions.index'],
            ['key' => 'inference-prompts',  'label' => 'Inference-Prompts',      'icon' => 'heroicon-o-sparkles',               'route' => 'organization.settings.inference-prompts.index'],
            ['key' => 'synthesis-prompts',  'label' => 'Synthese-Prompts',       'icon' => 'heroicon-o-chat-bubble-left-right', 'route' => 'organization.settings.synthesis-prompts.index'],
        ],
    ];

    $klassen = fn (bool $ist) => 'flex w-full items-center gap-3 rounded-md p-2 text-left text-sm font-medium transition-colors '
        . ($ist
            ? 'bg-[color:var(--nx-active)] font-semibold text-[color:var(--nx-text)]'
            : 'text-[color:var(--nx-text)] hover:bg-[color:var(--nx-hover)]');
@endphp

<div class="flex flex-col gap-4 p-3">
    @foreach ($gruppen as $label => $eintraege)
        <div @class(['flex flex-col gap-1', 'border-t border-[color:var(--nx-line)] pt-3' => ! $loop->first])>
            <span class="px-1 pb-1 text-xs font-medium tracking-wide text-[color:var(--nx-faint)]">{{ $label }}</span>
            @foreach ($eintraege as $e)
                <a href="{{ route($e['route']) }}" wire:navigate class="{{ $klassen(($aktiv ?? null) === $e['key']) }}">
                    @svg($e['icon'], 'w-4 h-4 shrink-0 text-[color:var(--nx-muted)]')
                    <span>{{ $e['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endforeach
</div>
