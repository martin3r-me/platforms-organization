<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationInterlinkCategory;

class UpdateInterlinkCategoryTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.interlink_categories.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/interlink-categories/{id} - Aktualisiert eine Interlink-Kategorie. Parameter: interlink_category_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'interlink_category_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Interlink-Kategorie (ERFORDERLICH). Nutze organization.interlink_categories.GET.',
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
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Metadatenobjekt (null zum Leeren).',
                ],
            ],
            'required' => ['interlink_category_id'],
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
                'interlink_category_id',
                OrganizationInterlinkCategory::class,
                'NOT_FOUND',
                'Interlink-Kategorie nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationInterlinkCategory $cat */
            $cat = $found['model'];

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
                $exists = OrganizationInterlinkCategory::query()
                    ->where('code', $code)
                    ->where('id', '!=', $cat->id)
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Interlink-Kategorie mit code '{$code}' existiert bereits.");
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
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (!empty($update)) {
                $cat->update($update);
            }
            $cat->refresh();

            return ToolResult::success([
                'id' => $cat->id,
                'code' => $cat->code,
                'name' => $cat->name,
                'description' => $cat->description,
                'icon' => $cat->icon,
                'sort_order' => $cat->sort_order,
                'is_active' => (bool)$cat->is_active,
                'metadata' => $cat->metadata,
                'message' => 'Interlink-Kategorie erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Interlink-Kategorie: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'interlink_categories', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
