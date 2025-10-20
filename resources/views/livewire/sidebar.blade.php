{{-- resources/views/vendor/organization/livewire/sidebar-content.blade.php --}}
<div>
    {{-- Modul Header --}}
    <x-sidebar-module-header module-name="Organization" />
    
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

        {{-- VSM Systeme --}}
        <a href="{{ route('organization.vsm-systems.index') }}"
           class="relative flex items-center px-3 py-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/vsm-systems') ||
               window.location.pathname.endsWith('/vsm-systems') ||
               window.location.pathname.endsWith('/vsm-systems/')
                   ? 'bg-[color:var(--ui-primary)] text-[color:var(--ui-on-primary)] shadow'
                   : 'text-[color:var(--ui-secondary)] hover:bg-[color:var(--ui-primary-5)] hover:text-[color:var(--ui-primary)]',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-rectangle-group class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">VSM Systeme</span>
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
</div>