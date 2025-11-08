<?php

namespace Platform\Organization\Livewire\Settings\EntityType;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeGroup;
use Platform\Core\PlatformCore;

class Index extends Component
{
    public $search = '';
    public $selectedGroup = '';
    public $showInactive = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedGroup' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    #[Computed]
    public function entityTypes()
    {
        $query = OrganizationEntityType::query()
            ->with('group');

        // Suche
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        // Group Filter
        if ($this->selectedGroup) {
            $query->where('entity_type_group_id', $this->selectedGroup);
        }

        // Active/Inactive Filter
        if (!$this->showInactive) {
            $query->active();
        }

        return $query->ordered()->get();
    }

    #[Computed]
    public function entityTypeGroups()
    {
        return OrganizationEntityTypeGroup::active()
            ->ordered()
            ->get();
    }

    #[Computed]
    public function modules()
    {
        return collect(PlatformCore::getModules())
            ->mapWithKeys(function ($module) {
                return [$module['key'] => $module['title'] ?? ucfirst($module['key'])];
            })
            ->toArray();
    }

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    public function render()
    {
        return view('organization::livewire.settings.entity-type.index')
            ->layout('platform::layouts.app');
    }
}

