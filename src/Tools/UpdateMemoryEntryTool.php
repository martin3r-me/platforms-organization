<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMemoryEntry;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateMemoryEntryTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.memory.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/memory/{id} - Aktualisiert einen Memory-Entry (Content, Confidence, Gültigkeit, Aktivstatus).';
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
                'content' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Inhalt.',
                ],
                'structured_data' => [
                    'type' => 'object',
                    'description' => 'Optional: Neue strukturierte Daten.',
                ],
                'confidence' => [
                    'type' => 'number',
                    'description' => 'Optional: Neue Confidence 0.0-1.0.',
                ],
                'valid_until' => [
                    'type' => 'string',
                    'description' => 'Optional: Neues Ablaufdatum (ISO 8601). null zum Entfernen.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Aktiv/Inaktiv setzen.',
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

            $update = [];

            if (array_key_exists('content', $arguments)) {
                $update['content'] = (string) $arguments['content'];
            }

            if (array_key_exists('structured_data', $arguments)) {
                $update['structured_data'] = $arguments['structured_data'];
            }

            if (array_key_exists('confidence', $arguments)) {
                $update['confidence'] = max(0.0, min(1.0, (float) $arguments['confidence']));
            }

            if (array_key_exists('valid_until', $arguments)) {
                $update['valid_until'] = $arguments['valid_until'] ?: null;
            }

            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Änderungen angegeben.');
            }

            $entry->update($update);

            return ToolResult::success([
                'id' => $entry->id,
                'uuid' => $entry->uuid,
                'message' => 'Memory-Entry aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'memory', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
