<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntityRelationType;

class CreateRelationTypeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.relation_types.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/relation-types - Erstellt einen Entity Relation Type (global). Nutze organization.relation_types.GET um bestehende zu prüfen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Relation Types (ERFORDERLICH).',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Eindeutiger Code (ERFORDERLICH, muss eindeutig sein).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Icon-Bezeichnung.',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierreihenfolge. Default: 0.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                    'default' => true,
                ],
                'is_directional' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ist die Relation gerichtet (A→B ≠ B→A)? Default: false.',
                    'default' => false,
                ],
                'is_hierarchical' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ist die Relation hierarchisch (z.B. Parent→Child)? Default: false.',
                    'default' => false,
                ],
                'is_reciprocal' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Ist die Relation reziprok (A↔B automatisch)? Default: false.',
                    'default' => false,
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freies JSON-Metadatenobjekt.',
                ],
            ],
            'required' => ['name', 'code'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $code = trim((string)($arguments['code'] ?? ''));
            if ($code === '') {
                return ToolResult::error('VALIDATION_ERROR', 'code ist erforderlich.');
            }

            $exists = OrganizationEntityRelationType::query()
                ->where('code', $code)
                ->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "Relation Type mit code '{$code}' existiert bereits.");
            }

            $rt = OrganizationEntityRelationType::create([
                'name' => $name,
                'code' => $code,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string)$arguments['description'] : null,
                'icon' => (array_key_exists('icon', $arguments) && $arguments['icon'] !== '') ? (string)$arguments['icon'] : null,
                'sort_order' => (int)($arguments['sort_order'] ?? 0),
                'is_active' => (bool)($arguments['is_active'] ?? true),
                'is_directional' => (bool)($arguments['is_directional'] ?? false),
                'is_hierarchical' => (bool)($arguments['is_hierarchical'] ?? false),
                'is_reciprocal' => (bool)($arguments['is_reciprocal'] ?? false),
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            return ToolResult::success([
                'id' => $rt->id,
                'code' => $rt->code,
                'name' => $rt->name,
                'description' => $rt->description,
                'icon' => $rt->icon,
                'sort_order' => $rt->sort_order,
                'is_active' => (bool)$rt->is_active,
                'is_directional' => (bool)$rt->is_directional,
                'is_hierarchical' => (bool)$rt->is_hierarchical,
                'is_reciprocal' => (bool)$rt->is_reciprocal,
                'metadata' => $rt->metadata,
                'message' => 'Relation Type erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Relation Types: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'relation_types', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
