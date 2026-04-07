<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationRole;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateRoleTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.roles.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/roles - Erstellt eine Rolle (z.B. "Projektleiter") im Rollen-Katalog. Slug wird automatisch generiert, falls nicht angegeben.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'     => ['type' => 'integer'],
                'name'        => ['type' => 'string', 'description' => 'ERFORDERLICH: Name der Rolle.'],
                'slug'        => ['type' => 'string', 'description' => 'Optional: Slug (auto, falls nicht angegeben).'],
                'description' => ['type' => 'string'],
                'status'      => ['type' => 'string', 'description' => 'Optional: active/archived. Default: active.'],
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

            $slug = isset($arguments['slug']) ? trim((string) $arguments['slug']) : '';
            if ($slug !== '') {
                $exists = OrganizationRole::query()
                    ->where('team_id', $rootTeamId)
                    ->where('slug', $slug)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Rolle mit slug '{$slug}' existiert bereits.");
                }
            }

            $role = OrganizationRole::create([
                'team_id'     => $rootTeamId,
                'user_id'     => $context->user?->id,
                'name'        => $name,
                'slug'        => $slug !== '' ? $slug : null, // Auto via Model-Event
                'description' => ($arguments['description'] ?? null) ?: null,
                'status'      => ($arguments['status'] ?? 'active'),
            ]);

            return ToolResult::success([
                'id'      => $role->id,
                'uuid'    => $role->uuid,
                'name'    => $role->name,
                'slug'    => $role->slug,
                'status'  => $role->status,
                'team_id' => $role->team_id,
                'message' => 'Rolle erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Rolle: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'roles', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
