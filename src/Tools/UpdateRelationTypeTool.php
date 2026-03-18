<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEntityRelationType;

class UpdateRelationTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.relation_types.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/relation-types/{id} - Aktualisiert einen Relation Type. Parameter: relation_type_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'relation_type_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Relation Types (ERFORDERLICH). Nutze organization.relation_types.GET.',
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
                'is_directional' => [
                    'type' => 'boolean',
                    'description' => 'Optional: gerichtet ja/nein.',
                ],
                'is_hierarchical' => [
                    'type' => 'boolean',
                    'description' => 'Optional: hierarchisch ja/nein.',
                ],
                'is_reciprocal' => [
                    'type' => 'boolean',
                    'description' => 'Optional: reziprok ja/nein.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Metadatenobjekt (null zum Leeren).',
                ],
            ],
            'required' => ['relation_type_id'],
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
                'relation_type_id',
                OrganizationEntityRelationType::class,
                'NOT_FOUND',
                'Relation Type nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationEntityRelationType $rt */
            $rt = $found['model'];

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
                $exists = OrganizationEntityRelationType::query()
                    ->where('code', $code)
                    ->where('id', '!=', $rt->id)
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Relation Type mit code '{$code}' existiert bereits.");
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
            foreach (['is_directional', 'is_hierarchical', 'is_reciprocal'] as $boolField) {
                if (array_key_exists($boolField, $arguments)) {
                    $update[$boolField] = (bool)$arguments[$boolField];
                }
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (!empty($update)) {
                $rt->update($update);
            }
            $rt->refresh();

            return ToolResult::success([
                'id' => $rt->id,
                'code' => $rt->code,
                'name' => $rt->name,
                'description' => $rt->description,
                'icon' => $rt->icon,
                'sort_order' => $rt->sort_order,
                'is_active' => (bool)$rt->is_active,
                'is_directional' => (bool)$rt->is_directional,
                'is_hierarchical' => (bool)$rt->is_hierarchical,
                'is_reciprocal' => (bool)$rt->is_reciprocal,
                'metadata' => $rt->metadata,
                'message' => 'Relation Type erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Relation Types: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'relation_types', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
