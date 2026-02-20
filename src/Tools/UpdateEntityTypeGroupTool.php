<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntityTypeGroup;

class UpdateEntityTypeGroupTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.entity_type_groups.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/entity-type-groups/{id} - Aktualisiert eine Entity Type Group. Parameter: entity_type_group_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'entity_type_group_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Entity Type Group (ERFORDERLICH). Nutze organization.entity_type_groups.GET.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name (muss eindeutig sein).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung ("" zum Leeren).',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierreihenfolge.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
            ],
            'required' => ['entity_type_group_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'entity_type_group_id',
                OrganizationEntityTypeGroup::class,
                'NOT_FOUND',
                'Entity Type Group nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationEntityTypeGroup $group */
            $group = $found['model'];

            $update = [];
            if (array_key_exists('name', $arguments)) {
                $name = trim((string)($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                // Unique check excluding self
                $exists = OrganizationEntityTypeGroup::query()
                    ->where('name', $name)
                    ->where('id', '!=', $group->id)
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Entity Type Group mit name '{$name}' existiert bereits.");
                }
                $update['name'] = $name;
            }
            if (array_key_exists('description', $arguments)) {
                $d = (string)($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int)$arguments['sort_order'];
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool)$arguments['is_active'];
            }

            if (!empty($update)) {
                $group->update($update);
            }
            $group->refresh();

            return ToolResult::success([
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'sort_order' => $group->sort_order,
                'is_active' => (bool)$group->is_active,
                'message' => 'Entity Type Group erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Entity Type Group: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity_type_groups', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
