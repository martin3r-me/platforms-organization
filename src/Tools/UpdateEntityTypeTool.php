<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeGroup;

class UpdateEntityTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.entity_types.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/entity-types/{id} - Aktualisiert einen Entity Type. Parameter: entity_type_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'entity_type_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Entity Types (ERFORDERLICH). Nutze organization.entity_types.GET.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Code (muss eindeutig sein).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung ("" zum Leeren).',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Icon-Bezeichnung ("" zum Leeren).',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierreihenfolge.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
                'entity_type_group_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID der Entity Type Group (0/null zum Entfernen). Nutze organization.entity_type_groups.GET.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Metadatenobjekt (null zum Leeren).',
                ],
            ],
            'required' => ['entity_type_id'],
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
                'entity_type_id',
                OrganizationEntityType::class,
                'NOT_FOUND',
                'Entity Type nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationEntityType $et */
            $et = $found['model'];

            $update = [];
            if (array_key_exists('name', $arguments)) {
                $name = trim((string)($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }
            if (array_key_exists('code', $arguments)) {
                $code = trim((string)($arguments['code'] ?? ''));
                if ($code === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'code darf nicht leer sein.');
                }
                // Unique check excluding self
                $exists = OrganizationEntityType::query()
                    ->where('code', $code)
                    ->where('id', '!=', $et->id)
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Entity Type mit code '{$code}' existiert bereits.");
                }
                $update['code'] = $code;
            }
            if (array_key_exists('description', $arguments)) {
                $d = (string)($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }
            if (array_key_exists('icon', $arguments)) {
                $i = (string)($arguments['icon'] ?? '');
                $update['icon'] = $i === '' ? null : $i;
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int)$arguments['sort_order'];
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool)$arguments['is_active'];
            }
            if (array_key_exists('entity_type_group_id', $arguments)) {
                $gid = $arguments['entity_type_group_id'];
                if ($gid === null || $gid === '' || $gid === 'null' || $gid === 0 || $gid === '0') {
                    $update['entity_type_group_id'] = null;
                } else {
                    $groupId = (int)$gid;
                    $groupExists = OrganizationEntityTypeGroup::query()->where('id', $groupId)->exists();
                    if (!$groupExists) {
                        return ToolResult::error('VALIDATION_ERROR', "Entity Type Group mit ID {$groupId} nicht gefunden. Nutze organization.entity_type_groups.GET.");
                    }
                    $update['entity_type_group_id'] = $groupId;
                }
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (!empty($update)) {
                $et->update($update);
            }
            $et->refresh();

            return ToolResult::success([
                'id' => $et->id,
                'code' => $et->code,
                'name' => $et->name,
                'description' => $et->description,
                'icon' => $et->icon,
                'sort_order' => $et->sort_order,
                'is_active' => (bool)$et->is_active,
                'entity_type_group_id' => $et->entity_type_group_id,
                'metadata' => $et->metadata,
                'message' => 'Entity Type erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Entity Types: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity_types', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
