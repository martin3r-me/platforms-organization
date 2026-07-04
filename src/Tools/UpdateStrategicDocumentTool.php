<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationStrategicDocument;

class UpdateStrategicDocumentTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.strategic_documents.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/strategic-documents/{id} - Aktualisiert Mission oder Vision. '
            . 'Bei is_active=true werden andere Dokumente desselben (entity_id, type) automatisch inaktiv gesetzt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id'          => ['type' => 'integer', 'description' => 'PFLICHT.'],
                'title'       => ['type' => 'string'],
                'content'     => ['type' => 'string'],
                'valid_from'  => ['type' => 'string'],
                'change_note' => ['type' => 'string'],
                'is_active'   => ['type' => 'boolean'],
                'version'     => ['type' => 'integer'],
                'entity_id'   => ['type' => 'integer', 'description' => 'Optional: Carrier-Entity wechseln (selten sinnvoll).'],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $doc = OrganizationStrategicDocument::find($id);
            if (!$doc) {
                return ToolResult::error('NOT_FOUND', "StrategicDocument #{$id} nicht gefunden.");
            }
            foreach (['title', 'content', 'valid_from', 'change_note', 'is_active', 'version', 'entity_id'] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $doc->{$f} = $arguments[$f];
                }
            }
            $doc->save();
            return ToolResult::success([
                'id'         => $doc->id,
                'entity_id'  => $doc->entity_id,
                'type'       => $doc->type,
                'title'      => $doc->title,
                'version'    => $doc->version,
                'is_active'  => (bool) $doc->is_active,
                'valid_from' => $doc->valid_from?->toDateString(),
                'message'    => 'Aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'strategic_documents', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
