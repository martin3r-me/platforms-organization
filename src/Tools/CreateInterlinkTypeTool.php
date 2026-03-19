<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationInterlinkType;

class CreateInterlinkTypeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.interlink_types.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/interlink-types - Erstellt einen Interlink-Typ (global). Nutze organization.interlink_types.GET um bestehende zu prüfen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Typs (ERFORDERLICH).',
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

            $exists = OrganizationInterlinkType::query()->where('code', $code)->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "Interlink-Typ mit code '{$code}' existiert bereits.");
            }

            $type = OrganizationInterlinkType::create([
                'name' => $name,
                'code' => $code,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string)$arguments['description'] : null,
                'icon' => (array_key_exists('icon', $arguments) && $arguments['icon'] !== '') ? (string)$arguments['icon'] : null,
                'sort_order' => (int)($arguments['sort_order'] ?? 0),
                'is_active' => (bool)($arguments['is_active'] ?? true),
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            return ToolResult::success([
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'description' => $type->description,
                'icon' => $type->icon,
                'sort_order' => $type->sort_order,
                'is_active' => (bool)$type->is_active,
                'metadata' => $type->metadata,
                'message' => 'Interlink-Typ erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Interlink-Typs: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'interlink_types', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
