<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationVsmSystem;

class CreateVsmSystemTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.vsm_systems.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/vsm-systems - Erstellt ein neues VSM-System. VSM-Systeme sind global (nicht team-spezifisch).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Code (z.B. "S1", max 10 Zeichen).',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name des VSM-Systems.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierung. Default: 0.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                ],
            ],
            'required' => ['code', 'name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $code = trim((string) ($arguments['code'] ?? ''));
            $name = trim((string) ($arguments['name'] ?? ''));

            if ($code === '' || $name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'code und name sind erforderlich.');
            }

            $exists = OrganizationVsmSystem::where('code', $code)->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "VSM-System mit code '{$code}' existiert bereits.");
            }

            $system = OrganizationVsmSystem::create([
                'code' => $code,
                'name' => $name,
                'description' => ($arguments['description'] ?? null) ?: null,
                'sort_order' => (int) ($arguments['sort_order'] ?? 0),
                'is_active' => (bool) ($arguments['is_active'] ?? true),
            ]);

            return ToolResult::success([
                'id' => $system->id,
                'code' => $system->code,
                'name' => $system->name,
                'message' => 'VSM-System erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des VSM-Systems: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'vsm', 'systems', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
