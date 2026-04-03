<?php

namespace Platform\Organization\Livewire\SlaContract;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Organization\Models\OrganizationSlaContract;

class Index extends Component
{
    public $search = '';
    public $showInactive = false;
    public $modalShow = false;
    public $newSlaContract = [
        'name' => '',
        'description' => '',
        'response_time_hours' => null,
        'resolution_time_hours' => null,
        'error_tolerance_percent' => null,
        'is_active' => true,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'showInactive' => ['except' => false],
    ];

    public function toggleInactive()
    {
        $this->showInactive = !$this->showInactive;
    }

    #[Computed]
    public function slaContracts()
    {
        $query = OrganizationSlaContract::query()
            ->with(['user'])
            ->where('team_id', auth()->user()->currentTeam->id);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if (!$this->showInactive) {
            $query->active();
        }

        return $query->orderBy('name')->get();
    }

    #[Computed]
    public function stats()
    {
        $teamId = auth()->user()->currentTeam->id;

        return [
            'total' => OrganizationSlaContract::where('team_id', $teamId)->count(),
            'active' => OrganizationSlaContract::where('team_id', $teamId)->active()->count(),
            'inactive' => OrganizationSlaContract::where('team_id', $teamId)->where('is_active', false)->count(),
        ];
    }

    public function openCreateModal()
    {
        $this->modalShow = true;
    }

    public function closeCreateModal()
    {
        $this->modalShow = false;
        $this->reset('newSlaContract');
    }

    public function createSlaContract()
    {
        $this->validate([
            'newSlaContract.name' => 'required|string|max:255',
            'newSlaContract.description' => 'nullable|string',
            'newSlaContract.response_time_hours' => 'nullable|integer|min:1',
            'newSlaContract.resolution_time_hours' => 'nullable|integer|min:1',
            'newSlaContract.error_tolerance_percent' => 'nullable|integer|min:0|max:100',
            'newSlaContract.is_active' => 'boolean',
        ]);

        OrganizationSlaContract::create([
            'name' => $this->newSlaContract['name'],
            'description' => $this->newSlaContract['description'] ?: null,
            'response_time_hours' => $this->newSlaContract['response_time_hours'] ?: null,
            'resolution_time_hours' => $this->newSlaContract['resolution_time_hours'] ?: null,
            'error_tolerance_percent' => $this->newSlaContract['error_tolerance_percent'],
            'is_active' => $this->newSlaContract['is_active'],
            'team_id' => auth()->user()->currentTeam->id,
            'user_id' => auth()->id(),
        ]);

        $this->closeCreateModal();
        session()->flash('message', 'SLA-Vertrag erfolgreich erstellt.');
    }

    public function deleteSlaContract(int $slaContractId)
    {
        $slaContract = OrganizationSlaContract::findOrFail($slaContractId);

        if ($slaContract->team_id !== auth()->user()->currentTeam->id) {
            session()->flash('error', 'Keine Berechtigung.');
            return;
        }

        $slaContract->delete();
        session()->flash('message', 'SLA-Vertrag erfolgreich gelöscht.');
    }

    public function render()
    {
        return view('organization::livewire.sla-contract.index')
            ->layout('platform::layouts.app');
    }
}
