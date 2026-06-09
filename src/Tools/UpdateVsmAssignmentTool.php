<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;

class UpdateVsmAssignmentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vsm_assignments.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/vsm-assignments/{id} - Aktualisiert eine VSM-Zellen-Besetzung. Nur uebergebene Felder werden geaendert. Bei Wechsel von perspective_entity_id, vsm_system oder assigned_entity_id wird der Unique-Constraint geprueft.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Zuordnung.',
                ],
                'perspective_entity_id' => ['type' => 'integer'],
                'vsm_system' => ['type' => 'string'],
                'assigned_entity_id' => ['type' => 'integer'],
                'scope' => ['type' => ['string', 'null']],
                'valid_from' => ['type' => ['string', 'null']],
                'valid_until' => ['type' => ['string', 'null']],
                'notes' => ['type' => ['string', 'null']],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team im Kontext.');
            }

            $id = (int) ($arguments['id'] ?? 0);
            if (!$id) {
                return ToolResult::error('VALIDATION_ERROR', 'id ist erforderlich.');
            }

            $assignment = OrganizationEntityVsmAssignment::where('id', $id)
                ->where('team_id', $teamId)
                ->first();

            if (!$assignment) {
                return ToolResult::error('NOT_FOUND', "VSM-Zuordnung {$id} nicht im Team gefunden.");
            }

            $updatable = ['perspective_entity_id', 'vsm_system', 'assigned_entity_id', 'scope', 'valid_from', 'valid_until', 'notes'];
            $changes = [];
            foreach ($updatable as $field) {
                if (array_key_exists($field, $arguments)) {
                    $changes[$field] = $arguments[$field];
                }
            }

            if (empty($changes)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine aenderbaren Felder uebergeben.');
            }

            // Duplikat-Pruefung falls Triplet sich aendert
            $newPerspective = $changes['perspective_entity_id'] ?? $assignment->perspective_entity_id;
            $newSystem = $changes['vsm_system'] ?? $assignment->vsm_system;
            $newAssigned = $changes['assigned_entity_id'] ?? $assignment->assigned_entity_id;

            $tripletChanged = $newPerspective != $assignment->perspective_entity_id
                || $newSystem != $assignment->vsm_system
                || $newAssigned != $assignment->assigned_entity_id;

            if ($tripletChanged) {
                $dup = OrganizationEntityVsmAssignment::where('perspective_entity_id', $newPerspective)
                    ->where('vsm_system', $newSystem)
                    ->where('assigned_entity_id', $newAssigned)
                    ->where('id', '!=', $assignment->id)
                    ->exists();
                if ($dup) {
                    return ToolResult::error('VALIDATION_ERROR',
                        "Zuordnung (Perspektive {$newPerspective}, {$newSystem}, Actor {$newAssigned}) existiert bereits.");
                }
            }

            $assignment->fill($changes);
            $assignment->save();

            return ToolResult::success([
                'id' => $assignment->id,
                'uuid' => $assignment->uuid,
                'updated_fields' => array_keys($changes),
                'message' => "VSM-Zuordnung {$assignment->id} aktualisiert.",
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'write',
            'tags' => ['organization', 'vsm', 'assignments'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => false,
        ];
    }
}
