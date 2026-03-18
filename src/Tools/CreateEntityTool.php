<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateEntityTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.entities.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/entities - Erstellt eine Organisationseinheit (Entity) im Root/Elterteam. Nutze organization.entity_types.GET und organization.entities.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name der Entity (z.B. "Broich Catering", "Abteilung Marketing").',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Eindeutiger Code.',
                ],
                'entity_type_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Entity Type ID. Nutze organization.entity_types.GET.',
                ],
                'vsm_system_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: VSM System ID. Nutze organization.vsm_systems.GET.',
                ],
                'cost_center_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Direkte Kostenstellen-ID (Standard-KST der Entity).',
                ],
                'parent_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Parent Entity ID für Hierarchie.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
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

            $code = null;
            if (array_key_exists('code', $arguments)) {
                $code = trim((string) ($arguments['code'] ?? ''));
                $code = $code === '' ? null : $code;
            }

            if ($code !== null) {
                $exists = OrganizationEntity::query()
                    ->where('team_id', $rootTeamId)
                    ->where('code', $code)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Entity mit code '{$code}' existiert bereits im Team.");
                }
            }

            $entity = OrganizationEntity::create([
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
                'name' => $name,
                'code' => $code,
                'entity_type_id' => isset($arguments['entity_type_id']) ? (int) $arguments['entity_type_id'] : null,
                'vsm_system_id' => isset($arguments['vsm_system_id']) ? (int) $arguments['vsm_system_id'] : null,
                'cost_center_id' => isset($arguments['cost_center_id']) ? (int) $arguments['cost_center_id'] : null,
                'parent_entity_id' => isset($arguments['parent_entity_id']) ? (int) $arguments['parent_entity_id'] : null,
                'description' => ($arguments['description'] ?? null) ?: null,
                'is_active' => (bool) ($arguments['is_active'] ?? true),
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

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
                'message' => 'Entity erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Entity: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entities', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
