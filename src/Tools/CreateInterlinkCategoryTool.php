<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationInterlinkCategory;

class CreateInterlinkCategoryTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.interlink_categories.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/interlink-categories - Erstellt eine Interlink-Kategorie (global). Nutze organization.interlink_categories.GET um bestehende zu prüfen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name der Kategorie (ERFORDERLICH).',
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

            $exists = OrganizationInterlinkCategory::query()->where('code', $code)->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "Interlink-Kategorie mit code '{$code}' existiert bereits.");
            }

            $cat = OrganizationInterlinkCategory::create([
                'name' => $name,
                'code' => $code,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string)$arguments['description'] : null,
                'icon' => (array_key_exists('icon', $arguments) && $arguments['icon'] !== '') ? (string)$arguments['icon'] : null,
                'sort_order' => (int)($arguments['sort_order'] ?? 0),
                'is_active' => (bool)($arguments['is_active'] ?? true),
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            return ToolResult::success([
                'id' => $cat->id,
                'code' => $cat->code,
                'name' => $cat->name,
                'description' => $cat->description,
                'icon' => $cat->icon,
                'sort_order' => $cat->sort_order,
                'is_active' => (bool)$cat->is_active,
                'metadata' => $cat->metadata,
                'message' => 'Interlink-Kategorie erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Interlink-Kategorie: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'interlink_categories', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
