{{-- resources/views/vendor/organization/livewire/sidebar-content.blade.php --}}
<div>
    {{-- Abschnitt: Allgemein --}}
    <div>
        <h4 x-show="!collapsed" class="p-3 text-sm italic text-secondary uppercase">Allgemein</h4>

        {{-- Dashboard --}}
        <a href="{{ route('organization.dashboard') }}"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname === '/' || 
               window.location.pathname.endsWith('/organization') || 
               window.location.pathname.endsWith('/organization/') ||
               (window.location.pathname.split('/').length === 1 && window.location.pathname === '/')
                   ? 'bg-primary text-on-primary shadow-md'
                   : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            @svg('heroicon-o-chart-bar', 'w-6 h-6 flex-shrink-0')
            <span x-show="!collapsed" class="truncate">Dashboard</span>
        </a>

        {{-- Organisationseinheiten --}}
        <a href="{{ route('organization.entities.index') }}"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/entities') || 
               window.location.pathname.endsWith('/entities') ||
               window.location.pathname.endsWith('/entities/')
                   ? 'bg-primary text-on-primary shadow-md'
                   : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            @svg('heroicon-o-building-office', 'w-6 h-6 flex-shrink-0')
            <span x-show="!collapsed" class="truncate">Organisationseinheiten</span>
        </a>
    </div>

    {{-- Abschnitt: Schnellzugriff --}}
    <div x-show="!collapsed">
        <h4 class="p-3 text-sm italic text-secondary uppercase">Schnellzugriff</h4>

        {{-- Neueste Organisationseinheiten --}}
        @foreach($recentEntities ?? [] as $entity)
            <a href="{{ route('organization.entities.index') }}"
               class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition gap-3"
               :class="[
                   'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md'
               ]"
               wire:navigate>
                @svg('heroicon-o-building-office', 'w-6 h-6 flex-shrink-0')
                <span class="truncate">{{ $entity->name }}</span>
            </a>
        @endforeach
    </div>
</div>