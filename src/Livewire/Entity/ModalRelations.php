<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\On;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationType;
use Illuminate\Support\Facades\Auth;

class ModalRelations extends Component
{
    public bool $open = false;
    public ?int $entityId = null;
    public OrganizationEntity $entity;
    
    // Form für neue Relation
    public ?int $selectedToEntityId = null;
    public ?int $selectedRelationTypeId = null;
    public ?string $validFrom = null;
    public ?string $validTo = null;
    
    // Liste der Relations
    public $relationsFrom = [];
    public $relationsTo = [];
    
    // Verfügbare Entities und Relation Types
    public $availableEntities = [];
    public $availableRelationTypes = [];

    public function mount(?int $entityId = null)
    {
        $this->entityId = $entityId;
        if ($entityId) {
            $this->loadEntity();
        }
    }

    #[On('open-relations-modal')]
    public function handleOpenModal(array $data)
    {
        if (isset($data['entityId'])) {
            $this->openModal($data['entityId']);
        }
    }

    public function openModal(int $entityId)
    {
        $this->entityId = $entityId;
        $this->loadEntity();
        $this->loadRelations();
        $this->loadAvailableEntities();
        $this->loadAvailableRelationTypes();
        $this->resetForm();
        $this->open = true;
    }

    public function closeModal()
    {
        $this->open = false;
        $this->resetForm();
        $this->reset('entityId', 'entity', 'relationsFrom', 'relationsTo');
    }

    protected function loadEntity()
    {
        $this->entity = OrganizationEntity::with(['type', 'team'])
            ->findOrFail($this->entityId);
    }

    protected function loadRelations()
    {
        $this->relationsFrom = $this->entity->relationsFrom()
            ->with(['toEntity.type', 'relationType', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        $this->relationsTo = $this->entity->relationsTo()
            ->with(['fromEntity.type', 'relationType', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    protected function loadAvailableEntities()
    {
        $team = Auth::user()?->currentTeamRelation;
        if (!$team) {
            $this->availableEntities = collect();
            return;
        }

        $this->availableEntities = OrganizationEntity::query()
            ->where('team_id', $team->id)
            ->where('id', '!=', $this->entityId) // Exclude self
            ->where('is_active', true)
            ->with('type')
            ->orderBy('name')
            ->get();
    }

    protected function loadAvailableRelationTypes()
    {
        $this->availableRelationTypes = OrganizationEntityRelationType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function resetForm()
    {
        $this->selectedToEntityId = null;
        $this->selectedRelationTypeId = null;
        $this->validFrom = null;
        $this->validTo = null;
        $this->resetValidation();
    }

    public function createRelation()
    {
        $this->validate([
            'selectedToEntityId' => 'required|exists:organization_entities,id',
            'selectedRelationTypeId' => 'required|exists:organization_entity_relation_types,id',
            'validFrom' => 'nullable|date',
            'validTo' => 'nullable|date|after_or_equal:validFrom',
        ], [
            'selectedToEntityId.required' => 'Bitte wählen Sie eine Ziel-Entity aus.',
            'selectedRelationTypeId.required' => 'Bitte wählen Sie einen Relation Type aus.',
            'validTo.after_or_equal' => 'Das Enddatum muss nach dem Startdatum liegen.',
        ]);

        // Prüfe ob Relation bereits existiert
        $exists = OrganizationEntityRelationship::where('from_entity_id', $this->entityId)
            ->where('to_entity_id', $this->selectedToEntityId)
            ->where('relation_type_id', $this->selectedRelationTypeId)
            ->exists();

        if ($exists) {
            $this->addError('selectedToEntityId', 'Diese Relation existiert bereits.');
            return;
        }

        try {
            OrganizationEntityRelationship::create([
                'from_entity_id' => $this->entityId,
                'to_entity_id' => $this->selectedToEntityId,
                'relation_type_id' => $this->selectedRelationTypeId,
                'valid_from' => $this->validFrom,
                'valid_to' => $this->validTo,
                'team_id' => Auth::user()?->currentTeamRelation?->id,
            ]);

            $this->loadRelations();
            $this->resetForm();
            
            session()->flash('message', 'Relation erfolgreich erstellt.');
        } catch (\Exception $e) {
            $this->addError('general', 'Fehler beim Erstellen: ' . $e->getMessage());
        }
    }

    public function deleteRelation(int $relationId)
    {
        try {
            $relation = OrganizationEntityRelationship::findOrFail($relationId);
            
            // Sicherheitsprüfung: Nur Relations des aktuellen Teams
            if ($relation->team_id !== Auth::user()?->currentTeamRelation?->id) {
                session()->flash('error', 'Sie haben keine Berechtigung, diese Relation zu löschen.');
                return;
            }

            $relation->delete();
            $this->loadRelations();
            
            session()->flash('message', 'Relation erfolgreich gelöscht.');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('organization::livewire.entity.modal-relations');
    }
}

