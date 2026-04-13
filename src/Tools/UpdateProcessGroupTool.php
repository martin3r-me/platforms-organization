<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationProcessGroup;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateProcessGroupTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_groups.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/process-groups/{id} - Aktualisiert eine Prozess-Gruppe.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID. Default: Team aus Kontext.',
                ],
                'process_group_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Prozess-Gruppe.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Code ("" zum Leeren).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung ("" zum Leeren).',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Neues Icon ("" zum Leeren).',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Sortierreihenfolge.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Aktiv/Inaktiv.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Metadatenobjekt (null zum Leeren).',
                ],
            ],
            'required' => ['process_group_id'],
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
                $arguments, $context, 'process_group_id',
                OrganizationProcessGroup::class, 'NOT_FOUND', 'Prozess-Gruppe nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            $group = $found['model'];

            if ((int) $group->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Prozess-Gruppe gehört nicht zum Team.');
            }

            $update = [];

            foreach (['name'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = trim((string) ($arguments[$field] ?? ''));
                    if ($val === '') {
                        return ToolResult::error('VALIDATION_ERROR', "{$field} darf nicht leer sein.");
                    }
                    $update[$field] = $val;
                }
            }

            foreach (['code', 'description', 'icon'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $val = (string) ($arguments[$field] ?? '');
                    $update[$field] = $val === '' ? null : $val;
                }
            }

            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int) $arguments['sort_order'];
            }

            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (! empty($update)) {
                $group->update($update);
            }
            $group->refresh();

            return ToolResult::success([
                'id' => $group->id,
                'uuid' => $group->uuid,
                'name' => $group->name,
                'code' => $group->code,
                'description' => $group->description,
                'icon' => $group->icon,
                'sort_order' => $group->sort_order,
                'is_active' => (bool) $group->is_active,
                'message' => 'Prozess-Gruppe erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Prozess-Gruppe: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'processes', 'groups', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
