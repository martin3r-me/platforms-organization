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
                'vsm_system'  => ['type' => 'string', 'description' => 'Optional: VSM-Funktion dieser Rolle (s1, s2, s3, s3_star, s4, s5). Beer: dieselbe Person traegt mehrere VSM-Funktionen durch ihre Rollen (GF=s3, Inhaber=s5).', 'enum' => ['s1', 's2', 's3', 's3_star', 's4', 's5']],
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

            $vsmSystem = $arguments['vsm_system'] ?? null;
            if ($vsmSystem !== null && ! in_array($vsmSystem, OrganizationRole::VSM_SYSTEMS, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'vsm_system muss einer von ' . implode(', ', OrganizationRole::VSM_SYSTEMS) . ' sein.');
            }

            $role = OrganizationRole::create([
                'team_id'     => $rootTeamId,
                'user_id'     => $context->user?->id,
                'name'        => $name,
                'slug'        => $slug !== '' ? $slug : null, // Auto via Model-Event
                'description' => ($arguments['description'] ?? null) ?: null,
                'vsm_system'  => $vsmSystem,
                'status'      => ($arguments['status'] ?? 'active'),
            ]);

            return ToolResult::success([
                'id'         => $role->id,
                'uuid'       => $role->uuid,
                'name'       => $role->name,
                'slug'       => $role->slug,
                'status'     => $role->status,
                'vsm_system' => $role->vsm_system,
                'team_id'    => $role->team_id,
                'message'    => 'Rolle erfolgreich erstellt.',
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
