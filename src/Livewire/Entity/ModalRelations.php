<?php

namespace Platform\Organization\Livewire\Entity;

use Livewire\Component;
use Livewire\Attributes\On;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityRelationship;
use Platform\Organization\Models\OrganizationEntityRelationType;
use Platform\Organization\Models\OrganizationEntityRelationshipInterlink;
use Platform\Organization\Models\OrganizationInterlink;
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
    public $availableEntities;
    public $availableRelationTypes;

    // Interlink-Verwaltung pro Relation
    public ?int $expandedRelationId = null;
    public ?int $selectedInterlinkId = null;
    public ?string $interlinkNote = null;
    public $availableInterlinks;

    public function mount(?int $entityId = null)
    {
        $this->entityId = $entityId;
        $this->availableEntities = collect();
        $this->availableRelationTypes = collect();
        $this->availableInterlinks = collect();
        if ($entityId) {
            $this->loadEntity();
        }
    }

    #[On('open-relations-modal')]
    public function handleOpenModal($entityId)
    {
        if ($entityId) {
            $this->openModal($entityId);
        }
    }

    public function openModal(int $entityId)
    {
        $this->entityId = $entityId;
        $this->loadEntity();
        $this->loadRelations();
        $this->loadAvailableEntities();
        $this->loadAvailableRelationTypes();
        $this->loadAvailableInterlinks();
        $this->resetForm();
        $this->open = true;
    }

    public function closeModal()
    {
        $this->open = false;
        $this->resetForm();
        $this->availableEntities = collect();
        $this->availableRelationTypes = collect();
        $this->availableInterlinks = collect();
        $this->expandedRelationId = null;
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
            ->with(['toEntity.type', 'relationType', 'user', 'interlinks.interlink.category', 'interlinks.interlink.type'])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->relationsTo = $this->entity->relationsTo()
            ->with(['fromEntity.type', 'relationType', 'user', 'interlinks.interlink.category', 'interlinks.interlink.type'])
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

    protected function loadAvailableInterlinks()
    {
        $team = Auth::user()?->currentTeamRelation;
        if (!$team) {
            $this->availableInterlinks = collect();
            return;
        }

        $this->availableInterlinks = OrganizationInterlink::query()
            ->where('team_id', $team->id)
            ->active()
            ->with(['category', 'type'])
            ->orderBy('name')
            ->get();
    }

    public function toggleRelationInterlinks(int $relationId)
    {
        $this->expandedRelationId = $this->expandedRelationId === $relationId ? null : $relationId;
        $this->selectedInterlinkId = null;
        $this->interlinkNote = null;
    }

    public function linkInterlink(int $relationId)
    {
        $this->validate([
            'selectedInterlinkId' => 'required|exists:organization_interlinks,id',
            'interlinkNote' => 'nullable|string|max:500',
        ], [
            'selectedInterlinkId.required' => 'Bitte wählen Sie einen Interlink aus.',
        ]);

        $relation = OrganizationEntityRelationship::findOrFail($relationId);

        if ($relation->team_id !== Auth::user()?->currentTeamRelation?->id) {
            session()->flash('error', 'Keine Berechtigung.');
            return;
        }

        // Prüfe Duplikat
        $exists = OrganizationEntityRelationshipInterlink::where('entity_relationship_id', $relationId)
            ->where('interlink_id', $this->selectedInterlinkId)
            ->exists();

        if ($exists) {
            $this->addError('selectedInterlinkId', 'Dieser Interlink ist bereits verknüpft.');
            return;
        }

        try {
            OrganizationEntityRelationshipInterlink::create([
                'entity_relationship_id' => $relationId,
                'interlink_id' => $this->selectedInterlinkId,
                'note' => $this->interlinkNote ?: null,
                'is_active' => true,
            ]);

            $this->selectedInterlinkId = null;
            $this->interlinkNote = null;
            $this->loadRelations();
        } catch (\Exception $e) {
            $this->addError('general', 'Fehler: ' . $e->getMessage());
        }
    }

    public function unlinkInterlink(int $pivotId)
    {
        try {
            $pivot = OrganizationEntityRelationshipInterlink::findOrFail($pivotId);

            if ($pivot->team_id !== Auth::user()?->currentTeamRelation?->id) {
                session()->flash('error', 'Keine Berechtigung.');
                return;
            }

            $pivot->delete();
            $this->loadRelations();
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Entfernen: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('organization::livewire.entity.modal-relations');
    }
}

