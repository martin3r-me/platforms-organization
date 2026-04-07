<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationRole;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateRoleTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.roles.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/roles/{id} - Aktualisiert eine Rolle.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'     => ['type' => 'integer'],
                'role_id'     => ['type' => 'integer', 'description' => 'ERFORDERLICH.'],
                'name'        => ['type' => 'string'],
                'slug'        => ['type' => 'string'],
                'description' => ['type' => 'string', 'description' => '"" zum Leeren.'],
                'status'      => ['type' => 'string'],
            ],
            'required' => ['role_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'role_id',
                OrganizationRole::class,
                'NOT_FOUND',
                'Rolle nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationRole $role */
            $role = $found['model'];
            if ((int) $role->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Rolle gehört nicht zum Root/Elterteam.');
            }

            $update = [];
            if (array_key_exists('name', $arguments)) {
                $val = trim((string) ($arguments['name'] ?? ''));
                if ($val === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $val;
            }
            if (array_key_exists('slug', $arguments)) {
                $val = trim((string) ($arguments['slug'] ?? ''));
                if ($val !== '') {
                    $exists = OrganizationRole::query()
                        ->where('team_id', $rootTeamId)
                        ->where('slug', $val)
                        ->where('id', '!=', $role->id)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        return ToolResult::error('VALIDATION_ERROR', "Rolle mit slug '{$val}' existiert bereits.");
                    }
                    $update['slug'] = $val;
                }
            }
            if (array_key_exists('description', $arguments)) {
                $val = (string) ($arguments['description'] ?? '');
                $update['description'] = $val === '' ? null : $val;
            }
            if (array_key_exists('status', $arguments)) {
                $update['status'] = (string) $arguments['status'];
            }

            if (! empty($update)) {
                $role->update($update);
            }
            $role->refresh();

            return ToolResult::success([
                'id'      => $role->id,
                'name'    => $role->name,
                'slug'    => $role->slug,
                'status'  => $role->status,
                'team_id' => $role->team_id,
                'message' => 'Rolle erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Rolle: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'roles', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
