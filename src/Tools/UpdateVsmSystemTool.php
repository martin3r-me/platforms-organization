<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationVsmSystem;

class UpdateVsmSystemTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vsm_systems.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/vsm-systems/{id} - Aktualisiert ein VSM-System. Nutze organization.vsm_systems.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'vsm_system_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des VSM-Systems.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Code.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung ("" zum Leeren).',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Sortierung.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
            ],
            'required' => ['vsm_system_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['vsm_system_id'] ?? 0);
            if (!$id) {
                return ToolResult::error('VALIDATION_ERROR', 'vsm_system_id ist erforderlich.');
            }

            $system = OrganizationVsmSystem::find($id);
            if (!$system) {
                return ToolResult::error('NOT_FOUND', 'VSM-System nicht gefunden.');
            }

            $update = [];
            if (array_key_exists('code', $arguments)) {
                $code = trim((string) $arguments['code']);
                if ($code === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'code darf nicht leer sein.');
                }
                $exists = OrganizationVsmSystem::where('code', $code)->where('id', '!=', $id)->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "VSM-System mit code '{$code}' existiert bereits.");
                }
                $update['code'] = $code;
            }
            if (array_key_exists('name', $arguments)) {
                $name = trim((string) $arguments['name']);
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }
            if (array_key_exists('description', $arguments)) {
                $d = (string) ($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int) $arguments['sort_order'];
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (!empty($update)) {
                $system->update($update);
            }
            $system->refresh();

            return ToolResult::success([
                'id' => $system->id,
                'code' => $system->code,
                'name' => $system->name,
                'is_active' => (bool) $system->is_active,
                'message' => 'VSM-System erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des VSM-Systems: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'vsm', 'systems', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
