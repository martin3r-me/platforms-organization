<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;

class DeleteVsmAssignmentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vsm_assignments.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/vsm-assignments/{id} - Loescht eine VSM-Zellen-Besetzung (Soft-Delete).';
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

            $assignment->delete();

            return ToolResult::success([
                'id' => $id,
                'message' => "VSM-Zuordnung {$id} geloescht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'write',
            'tags' => ['organization', 'vsm', 'assignments', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'moderate',
            'idempotent' => true,
        ];
    }
}
