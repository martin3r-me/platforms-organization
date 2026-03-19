<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationInterlinkType;

class UpdateInterlinkTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;

    public function getName(): string
    {
        return 'organization.interlink_types.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/interlink-types/{id} - Aktualisiert einen Interlink-Typ. Parameter: interlink_type_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'interlink_type_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Interlink-Typs (ERFORDERLICH). Nutze organization.interlink_types.GET.',
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
            'required' => ['interlink_type_id'],
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
                'interlink_type_id',
                OrganizationInterlinkType::class,
                'NOT_FOUND',
                'Interlink-Typ nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationInterlinkType $type */
            $type = $found['model'];

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
                $exists = OrganizationInterlinkType::query()
                    ->where('code', $code)
                    ->where('id', '!=', $type->id)
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Interlink-Typ mit code '{$code}' existiert bereits.");
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
                $type->update($update);
            }
            $type->refresh();

            return ToolResult::success([
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'description' => $type->description,
                'icon' => $type->icon,
                'sort_order' => $type->sort_order,
                'is_active' => (bool)$type->is_active,
                'metadata' => $type->metadata,
                'message' => 'Interlink-Typ erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Interlink-Typs: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'interlink_types', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
