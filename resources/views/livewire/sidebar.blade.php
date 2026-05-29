{{-- resources/views/vendor/organization/livewire/sidebar-content.blade.php --}}
<div>
    {{-- Modul Header --}}
    <x-sidebar-module-header module-name="Organization" />

    {{-- Perspective Switcher --}}
    <div x-show="!collapsed">
        @livewire('organization.perspective-switcher')
    </div>

    {{-- Abschnitt: Allgemein --}}
    <div>
        <h4 x-show="!collapsed" class="px-4 py-3 text-xs tracking-wide font-semibold text-[color:var(--ui-muted)] uppercase">Allgemein</h4>

        {{-- Dashboard --}}
        <a href="{{ route('organization.dashboard') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname === '/' || 
               window.location.pathname.endsWith('/organization') || 
               window.location.pathname.endsWith('/organization/') ||
               (window.location.pathname.split('/').length === 1 && window.location.pathname === '/')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-chart-bar class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Dashboard</span>
        </a>

        {{-- Organisationseinheiten --}}
        <a href="{{ route('organization.entities.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/entities') ||
               window.location.pathname.endsWith('/entities') ||
               window.location.pathname.endsWith('/entities/')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-building-office class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Organisationseinheiten</span>
        </a>

    </div>

    {{-- Abschnitt: Verbindungen --}}
    <div>
        <h4 x-show="!collapsed" class="px-4 py-3 text-xs tracking-wide font-semibold text-[color:var(--ui-muted)] uppercase">Verbindungen</h4>

        {{-- Interlinks --}}
        <a href="{{ route('organization.interlinks.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/interlinks') ||
               window.location.pathname.endsWith('/interlinks') ||
               window.location.pathname.endsWith('/interlinks/')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-arrows-right-left class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Interlinks</span>
        </a>

        {{-- SLA-Verträge --}}
        <a href="{{ route('organization.sla-contracts.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/sla-contracts') ||
               window.location.pathname.endsWith('/sla-contracts') ||
               window.location.pathname.endsWith('/sla-contracts/')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-shield-check class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">SLA-Verträge</span>
        </a>
    </div>

    {{-- Abschnitt: Personen --}}
    <div>
        <h4 x-show="!collapsed" class="px-4 py-3 text-xs tracking-wide font-semibold text-[color:var(--ui-muted)] uppercase">Personen</h4>

        {{-- Jobprofile --}}
        <a href="{{ route('organization.job-profiles.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/job-profiles')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-identification class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Jobprofile</span>
        </a>

        {{-- Rollen --}}
        <a href="{{ route('organization.roles.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/roles')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-user-group class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Rollen</span>
        </a>

        {{-- Skills --}}
        <a href="{{ route('organization.skills.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/skills')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-academic-cap class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Skills</span>
        </a>
    </div>

    {{-- Abschnitt: Dimensionen --}}
    <div>
        <h4 x-show="!collapsed" class="px-4 py-3 text-xs tracking-wide font-semibold text-[color:var(--ui-muted)] uppercase">Dimensionen</h4>

        {{-- Kostenstellen --}}
        <a href="{{ route('organization.cost-centers.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/cost-centers') ||
               window.location.pathname.endsWith('/cost-centers') ||
               window.location.pathname.endsWith('/cost-centers/')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-currency-dollar class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Kostenstellen</span>
        </a>


        {{-- Perspektiven --}}
        <a href="{{ route('organization.perspectives.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/perspectives')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-eye class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Perspektiven</span>
        </a>

    </div>

    {{-- Abschnitt: Zeiten --}}
    <div>
        <h4 x-show="!collapsed" class="px-4 py-3 text-xs tracking-wide font-semibold text-[color:var(--ui-muted)] uppercase">Zeiten</h4>

        {{-- Ist-Zeiten --}}
        <a href="{{ route('organization.time-entries.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/time-entries') ||
               window.location.pathname.endsWith('/time-entries') ||
               window.location.pathname.endsWith('/time-entries/')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-clock class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Ist-Zeiten</span>
        </a>

        {{-- Geplante Zeiten --}}
        <a href="{{ route('organization.planned-times.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/planned-times') ||
               window.location.pathname.endsWith('/planned-times') ||
               window.location.pathname.endsWith('/planned-times/')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-calendar class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Geplante Zeiten</span>
        </a>
    </div>

    {{-- Abschnitt: Inference --}}
    <div>
        <h4 x-show="!collapsed" class="px-4 py-3 text-xs tracking-wide font-semibold text-[color:var(--ui-muted)] uppercase">Inference</h4>

        {{-- Meine Inquiries --}}
        <a href="{{ route('organization.my-inquiries.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/my-inquiries')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-inbox class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Meine Inquiries</span>
        </a>

        {{-- Signale --}}
        <a href="{{ route('organization.signals.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/signals')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-bell-alert class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Signale</span>
        </a>

        {{-- Inference Runs --}}
        <a href="{{ route('organization.inference-runs.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/inference-runs')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-play class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Inference Runs</span>
        </a>

        {{-- Memory --}}
        <a href="{{ route('organization.memory.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/memory')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-circle-stack class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Memory</span>
        </a>

        {{-- Inquiries --}}
        <a href="{{ route('organization.inquiries.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/inquiries')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-question-mark-circle class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Inquiries</span>
        </a>

        {{-- Synthesis Reports --}}
        <a href="{{ route('organization.synthesis-reports.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/synthesis-reports')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-document-text class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Synthesis Reports</span>
        </a>
    </div>

    {{-- Abschnitt: Schnellzugriff --}}
    <div x-show="!collapsed">
        <h4 class="px-4 py-3 text-xs tracking-wide font-semibold text-[color:var(--ui-muted)] uppercase">Schnellzugriff</h4>

        {{-- Neueste Organisationseinheiten --}}
        @foreach($recentEntities ?? [] as $entity)
            <a href="{{ route('organization.entities.index') }}"
               class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition gap-3"
               :class="[
                   'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]'
               ]"
               wire:navigate>
                <x-heroicon-o-building-office class="w-6 h-6 flex-shrink-0"/>
                <span class="truncate">{{ $entity->name }}</span>
            </a>
        @endforeach
    </div>

    {{-- Abschnitt: Einstellungen --}}
    <div>
        <h4 x-show="!collapsed" class="px-4 py-3 text-xs tracking-wide font-semibold text-[color:var(--ui-muted)] uppercase">Einstellungen</h4>

        {{-- Entity Types --}}
        <a href="{{ route('organization.settings.entity-types.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/settings/entity-types') ||
               window.location.pathname.endsWith('/settings/entity-types') ||
               window.location.pathname.endsWith('/settings/entity-types/')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-cube class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Entity Types</span>
        </a>

        {{-- Relation Types --}}
        <a href="{{ route('organization.settings.relation-types.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/settings/relation-types') ||
               window.location.pathname.endsWith('/settings/relation-types') ||
               window.location.pathname.endsWith('/settings/relation-types/')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-arrows-right-left class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Relation Types</span>
        </a>

        {{-- Inference Prompts --}}
        <a href="{{ route('organization.settings.inference-prompts.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/settings/inference-prompts')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-cpu-chip class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Inference Prompts</span>
        </a>

        {{-- Signaldefinitionen --}}
        <a href="{{ route('organization.settings.signal-definitions.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/settings/signal-definitions')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-bell-alert class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Signaldefinitionen</span>
        </a>
    </div>
</div>