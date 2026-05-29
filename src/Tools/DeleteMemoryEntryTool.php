<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteMemoryEntryTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.memory.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/memory/{id} - Deaktiviert einen Memory-Entry (Soft-Delete). Setzt is_active=false.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'memory_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Memory-Entry.',
                ],
            ],
            'required' => ['memory_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $memoryId = (int) ($arguments['memory_id'] ?? 0);
            if ($memoryId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'memory_id ist erforderlich.');
            }

            $entry = OrganizationMemoryEntry::where('id', $memoryId)
                ->where('team_id', $rootTeamId)
                ->first();

            if (! $entry) {
                return ToolResult::error('NOT_FOUND', 'Memory-Entry nicht gefunden.');
            }

            $entry->update(['is_active' => false]);
            $entry->delete();

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'message' => 'Memory-Entry deaktiviert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Deaktivieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'memory', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
