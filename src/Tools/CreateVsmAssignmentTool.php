<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;

class CreateVsmAssignmentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vsm_assignments.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/vsm-assignments - Legt eine VSM-Zellen-Besetzung an. perspective_entity_id muss Carrier sein, assigned_entity_id muss Actor sein. Mehrfachbesetzung pro Zelle ist erlaubt; (perspective_entity_id, vsm_system, assigned_entity_id) muss eindeutig sein.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'perspective_entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: Carrier-Entity (aus deren Sicht).',
                ],
                'vsm_system' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: VSM-Ebene (s1, s2, s3, s3_star, s4, s5).',
                ],
                'assigned_entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: Actor-Entity, die die Zelle ausfuellt.',
                ],
                'scope' => [
                    'type' => 'string',
                    'description' => 'Optional: Einschraenkung (z.B. "Cashflow", "Backend").',
                ],
                'valid_from' => [
                    'type' => 'string',
                    'description' => 'Optional: Gueltig ab (YYYY-MM-DD).',
                ],
                'valid_until' => [
                    'type' => 'string',
                    'description' => 'Optional: Gueltig bis (YYYY-MM-DD).',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional: Begruendung / Notiz.',
                ],
            ],
            'required' => ['perspective_entity_id', 'vsm_system', 'assigned_entity_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->getTeamId();
            if (!$teamId) {
                return ToolResult::error('AUTH_ERROR', 'Kein Team im Kontext.');
            }

            $perspectiveId = (int) ($arguments['perspective_entity_id'] ?? 0);
            $vsmSystem = (string) ($arguments['vsm_system'] ?? '');
            $assignedId = (int) ($arguments['assigned_entity_id'] ?? 0);

            if (!$perspectiveId || !$vsmSystem || !$assignedId) {
                return ToolResult::error('VALIDATION_ERROR', 'perspective_entity_id, vsm_system und assigned_entity_id sind erforderlich.');
            }

            // Team-Constraint sicherstellen
            $perspective = OrganizationEntity::find($perspectiveId);
            $assignee = OrganizationEntity::find($assignedId);
            if (!$perspective || $perspective->team_id !== $teamId) {
                return ToolResult::error('NOT_FOUND', "perspective_entity_id {$perspectiveId} nicht im Team.");
            }
            if (!$assignee || $assignee->team_id !== $teamId) {
                return ToolResult::error('NOT_FOUND', "assigned_entity_id {$assignedId} nicht im Team.");
            }

            // Duplikat-Check (vor Saving-Hook fuer freundlichere Fehlermeldung)
            $exists = OrganizationEntityVsmAssignment::where('perspective_entity_id', $perspectiveId)
                ->where('vsm_system', $vsmSystem)
                ->where('assigned_entity_id', $assignedId)
                ->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR',
                    "Zuordnung (Perspektive {$perspectiveId}, {$vsmSystem}, Actor {$assignedId}) existiert bereits.");
            }

            $assignment = OrganizationEntityVsmAssignment::create([
                'team_id' => $teamId,
                'perspective_entity_id' => $perspectiveId,
                'vsm_system' => $vsmSystem,
                'assigned_entity_id' => $assignedId,
                'scope' => $arguments['scope'] ?? null,
                'valid_from' => $arguments['valid_from'] ?? null,
                'valid_until' => $arguments['valid_until'] ?? null,
                'notes' => $arguments['notes'] ?? null,
                'created_by_user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $assignment->id,
                'uuid' => $assignment->uuid,
                'perspective_entity_id' => $assignment->perspective_entity_id,
                'vsm_system' => $assignment->vsm_system,
                'assigned_entity_id' => $assignment->assigned_entity_id,
                'message' => "VSM-Zuordnung angelegt: {$perspective->name} / {$vsmSystem} = {$assignee->name}",
            ]);
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen der VSM-Zuordnung: ' . $e->getMessage());
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
