<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationStrategicDocument;

class CreateStrategicDocumentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.strategic_documents.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/strategic-documents - Erstellt Mission oder Vision an einer Carrier-Entity. '
            . 'entity_id (Pflicht) muss auf eine Carrier-Entity zeigen. '
            . 'Bei is_active=true werden andere Dokumente desselben (entity_id,type) automatisch inaktiv gesetzt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id'   => ['type' => 'integer', 'description' => 'Carrier-Entity, an die das Dokument haengt (PFLICHT).'],
                'type'        => ['type' => 'string', 'enum' => ['mission', 'vision'], 'description' => 'mission oder vision (PFLICHT).'],
                'title'       => ['type' => 'string', 'description' => 'Titel (PFLICHT).'],
                'valid_from'  => ['type' => 'string', 'description' => 'YYYY-MM-DD (PFLICHT).'],
                'content'     => ['type' => 'string', 'description' => 'Markdown-Content (optional).'],
                'version'     => ['type' => 'integer', 'description' => 'Explizite Version (optional; default: max+1 pro entity+type).'],
                'is_active'   => ['type' => 'boolean', 'description' => 'Als aktive Version markieren (optional, default false).'],
                'change_note' => ['type' => 'string', 'description' => 'Aenderungsnotiz (optional).'],
                'team_id'     => ['type' => 'integer', 'description' => 'Team-Scope (optional; default: Team aus Kontext).'],
                'created_by'  => ['type' => 'integer', 'description' => 'User-ID des Erstellers (optional; default: aktueller User).'],
                'created_at'  => ['type' => 'string', 'description' => 'Optional: expliziter Erstellzeitpunkt (fuer Datenmigration).'],
                'updated_at'  => ['type' => 'string', 'description' => 'Optional: expliziter Aenderungszeitpunkt.'],
                'deleted_at'  => ['type' => 'string', 'description' => 'Optional: Soft-Delete-Zeitpunkt (fuer Datenmigration).'],
            ],
            'required' => ['entity_id', 'type', 'title', 'valid_from'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $entityId = (int) ($arguments['entity_id'] ?? 0);
            if ($entityId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }

            $teamId = (int) ($arguments['team_id'] ?? $context->team?->id ?? 0);
            if ($teamId <= 0) {
                return ToolResult::error('MISSING_TEAM', 'team_id konnte nicht aufgeloest werden.');
            }

            $data = [
                'entity_id'   => $entityId,
                'type'        => $arguments['type'],
                'title'       => trim((string) $arguments['title']),
                'valid_from'  => $arguments['valid_from'],
                'content'     => $arguments['content'] ?? null,
                'version'     => isset($arguments['version']) ? (int) $arguments['version'] : null,
                'is_active'   => (bool) ($arguments['is_active'] ?? false),
                'change_note' => $arguments['change_note'] ?? null,
                'team_id'     => $teamId,
                'created_by'  => isset($arguments['created_by']) ? (int) $arguments['created_by'] : ($context->user?->id ?? null),
            ];

            foreach (['created_at', 'updated_at', 'deleted_at'] as $ts) {
                if (!empty($arguments[$ts])) {
                    $data[$ts] = $arguments[$ts];
                }
            }

            $doc = OrganizationStrategicDocument::create($data);

            return ToolResult::success([
                'id'         => $doc->id,
                'uuid'       => $doc->uuid,
                'entity_id'  => $doc->entity_id,
                'type'       => $doc->type,
                'title'      => $doc->title,
                'version'    => $doc->version,
                'is_active'  => (bool) $doc->is_active,
                'valid_from' => $doc->valid_from?->toDateString(),
                'team_id'    => $doc->team_id,
                'message'    => 'Strategic Document erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'strategic_documents', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
