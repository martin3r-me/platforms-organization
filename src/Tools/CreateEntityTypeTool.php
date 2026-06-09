<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityTypeGroup;

class CreateEntityTypeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.entity_types.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/entity-types - Erstellt einen Entity Type (global). Nutze organization.entity_types.GET um bestehende zu prüfen. vsm_class (carrier/actor/observed) entscheidet ob Entities dieses Typs Perspektive sein duerfen (carrier) oder VSM-Zellen ausfuellen koennen (actor).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name des Entity Types (ERFORDERLICH).',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Eindeutiger Code (ERFORDERLICH, muss eindeutig sein).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'icon' => [
                    'type' => 'string',
                    'description' => 'Optional: Icon-Bezeichnung.',
                ],
                'sort_order' => [
                    'type' => 'integer',
                    'description' => 'Optional: Sortierreihenfolge. Default: 0.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                    'default' => true,
                ],
                'entity_type_group_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID der Entity Type Group. Nutze organization.entity_type_groups.GET.',
                ],
                'vsm_class' => [
                    'type' => 'string',
                    'enum' => OrganizationEntityType::VSM_CLASSES,
                    'description' => 'Optional: VSM-Klasse. carrier = lebensfaehiges System (kann Perspektive sein). actor = fuellt VSM-Funktionen aus (kann S1-S5 ausfuellen). observed = Umwelt (wird von S4 beobachtet). can_be_perspective wird automatisch aus vsm_class abgeleitet (true genau bei carrier).',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freies JSON-Metadatenobjekt.',
                ],
            ],
            'required' => ['name', 'code'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $code = trim((string)($arguments['code'] ?? ''));
            if ($code === '') {
                return ToolResult::error('VALIDATION_ERROR', 'code ist erforderlich.');
            }

            // Unique check for code
            $exists = OrganizationEntityType::query()
                ->where('code', $code)
                ->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "Entity Type mit code '{$code}' existiert bereits.");
            }

            // Validate entity_type_group_id if provided
            $groupId = null;
            if (array_key_exists('entity_type_group_id', $arguments) && $arguments['entity_type_group_id'] !== null && $arguments['entity_type_group_id'] !== '') {
                $groupId = (int)$arguments['entity_type_group_id'];
                $groupExists = OrganizationEntityTypeGroup::query()->where('id', $groupId)->exists();
                if (!$groupExists) {
                    return ToolResult::error('VALIDATION_ERROR', "Entity Type Group mit ID {$groupId} nicht gefunden. Nutze organization.entity_type_groups.GET.");
                }
            }

            // Validate vsm_class if provided
            $vsmClass = null;
            if (array_key_exists('vsm_class', $arguments) && $arguments['vsm_class'] !== null && $arguments['vsm_class'] !== '') {
                $vsmClass = (string) $arguments['vsm_class'];
                if (!in_array($vsmClass, OrganizationEntityType::VSM_CLASSES, true)) {
                    return ToolResult::error(
                        'VALIDATION_ERROR',
                        "vsm_class muss einer von: " . implode(', ', OrganizationEntityType::VSM_CLASSES) . "."
                    );
                }
            }

            $et = OrganizationEntityType::create([
                'name' => $name,
                'code' => $code,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string)$arguments['description'] : null,
                'icon' => (array_key_exists('icon', $arguments) && $arguments['icon'] !== '') ? (string)$arguments['icon'] : null,
                'sort_order' => (int)($arguments['sort_order'] ?? 0),
                'is_active' => (bool)($arguments['is_active'] ?? true),
                'entity_type_group_id' => $groupId,
                'vsm_class' => $vsmClass,
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            return ToolResult::success([
                'id' => $et->id,
                'code' => $et->code,
                'name' => $et->name,
                'description' => $et->description,
                'icon' => $et->icon,
                'sort_order' => $et->sort_order,
                'is_active' => (bool)$et->is_active,
                'entity_type_group_id' => $et->entity_type_group_id,
                'vsm_class' => $et->vsm_class,
                'can_be_perspective' => (bool) $et->can_be_perspective,
                'metadata' => $et->metadata,
                'message' => 'Entity Type erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Entity Types: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'entity_types', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
