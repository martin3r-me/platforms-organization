<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateEntityTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entities.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/entities/{id} - Aktualisiert eine Organisationseinheit (Entity). Nutze organization.entities.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Code ("" zum Leeren).',
                ],
                'entity_type_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Entity Type ID (0/null zum Leeren).',
                ],
                'vsm_system_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue VSM System ID (0/null zum Leeren).',
                ],
                'cost_center_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Kostenstellen-ID (0/null zum Leeren).',
                ],
                'parent_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Parent Entity ID (0/null zum Leeren).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung ("" zum Leeren).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Metadatenobjekt (null zum Leeren).',
                ],
            ],
            'required' => ['entity_id'],
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
                'entity_id',
                OrganizationEntity::class,
                'NOT_FOUND',
                'Entity nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            $entity = $found['model'];
            if ((int) $entity->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Entity gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];

            if (array_key_exists('name', $arguments)) {
                $name = trim((string) ($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }
            if (array_key_exists('code', $arguments)) {
                $code = trim((string) ($arguments['code'] ?? ''));
                $update['code'] = $code === '' ? null : $code;
                if ($update['code'] !== null) {
                    $exists = OrganizationEntity::query()
                        ->where('team_id', $rootTeamId)
                        ->where('code', $update['code'])
                        ->where('id', '!=', $entity->id)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        return ToolResult::error('VALIDATION_ERROR', "Entity mit code '{$update['code']}' existiert bereits im Team.");
                    }
                }
            }
            if (array_key_exists('description', $arguments)) {
                $d = (string) ($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }

            foreach (['entity_type_id', 'vsm_system_id', 'cost_center_id', 'parent_entity_id'] as $fkField) {
                if (array_key_exists($fkField, $arguments)) {
                    $val = $arguments[$fkField];
                    if ($val === null || $val === '' || $val === 'null' || $val === 0 || $val === '0') {
                        $update[$fkField] = null;
                    } else {
                        $update[$fkField] = (int) $val;
                    }
                }
            }

            if (isset($update['parent_entity_id']) && $update['parent_entity_id'] === $entity->id) {
                return ToolResult::error('VALIDATION_ERROR', 'Entity kann nicht sein eigener Parent sein.');
            }

            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (!empty($update)) {
                $entity->update($update);
            }
            $entity->refresh();

            return ToolResult::success([
                'id' => $entity->id,
                'code' => $entity->code,
                'name' => $entity->name,
                'team_id' => $entity->team_id,
                'entity_type_id' => $entity->entity_type_id,
                'vsm_system_id' => $entity->vsm_system_id,
                'cost_center_id' => $entity->cost_center_id,
                'parent_entity_id' => $entity->parent_entity_id,
                'is_active' => (bool) $entity->is_active,
                'message' => 'Entity erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Entity: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entities', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
