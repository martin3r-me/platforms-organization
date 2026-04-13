<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationProcessGroup;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateProcessGroupTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_groups.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-groups - Erstellt eine Prozess-Gruppe zum thematischen Clustern von Prozessen.';
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
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name der Gruppe (z.B. "KyberOS Entwicklung", "Interne Administration").',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Kurzcode.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Icon-Name (z.B. "heroicon-o-cog").',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierreihenfolge. Default: 0.',
                    'default' => 0,
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Aktiv/Inaktiv. Default: true.',
                    'default' => true,
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freies JSON-Metadatenobjekt.',
                ],
            ],
            'required' => ['name'],
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

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $group = OrganizationProcessGroup::create([
                'name' => $name,
                'code' => (isset($arguments['code']) && $arguments['code'] !== '') ? (string) $arguments['code'] : null,
                'description' => (isset($arguments['description']) && $arguments['description'] !== '') ? (string) $arguments['description'] : null,
                'icon' => (isset($arguments['icon']) && $arguments['icon'] !== '') ? (string) $arguments['icon'] : null,
                'sort_order' => (int) ($arguments['sort_order'] ?? 0),
                'is_active' => (bool) ($arguments['is_active'] ?? true),
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            return ToolResult::success([
                'id' => $group->id,
                'uuid' => $group->uuid,
                'name' => $group->name,
                'code' => $group->code,
                'description' => $group->description,
                'icon' => $group->icon,
                'sort_order' => $group->sort_order,
                'is_active' => (bool) $group->is_active,
                'message' => 'Prozess-Gruppe erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Prozess-Gruppe: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'processes', 'groups', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
