<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationInterlink;
use Platform\Organization\Models\OrganizationInterlinkCategory;
use Platform\Organization\Models\OrganizationInterlinkType;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateInterlinkTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.interlinks.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/interlinks - Erstellt einen Interlink. Nutze organization.interlink_categories.GET und organization.interlink_types.GET um IDs zu ermitteln.';
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
                    'description' => 'ERFORDERLICH: Name des Interlinks.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'category_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Interlink-Kategorie. Nutze organization.interlink_categories.GET.',
                ],
                'type_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Interlink-Typs. Nutze organization.interlink_types.GET.',
                ],
                'is_bidirectional' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bidirektional (gilt in beide Richtungen). Default: false.',
                    'default' => false,
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                    'default' => true,
                ],
                'valid_from' => [
                    'type' => 'string',
                    'description' => 'Optional: Gültig ab (YYYY-MM-DD).',
                ],
                'valid_to' => [
                    'type' => 'string',
                    'description' => 'Optional: Gültig bis (YYYY-MM-DD).',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freies JSON-Metadatenobjekt.',
                ],
            ],
            'required' => ['name', 'category_id', 'type_id'],
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

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $categoryId = $arguments['category_id'] ?? null;
            $typeId = $arguments['type_id'] ?? null;

            if (!$categoryId) {
                return ToolResult::error('VALIDATION_ERROR', 'category_id ist erforderlich. Nutze organization.interlink_categories.GET.');
            }
            if (!$typeId) {
                return ToolResult::error('VALIDATION_ERROR', 'type_id ist erforderlich. Nutze organization.interlink_types.GET.');
            }

            $categoryId = (int) $categoryId;
            $typeId = (int) $typeId;

            $category = OrganizationInterlinkCategory::find($categoryId);
            if (!$category) {
                return ToolResult::error('NOT_FOUND', "Interlink-Kategorie mit ID {$categoryId} nicht gefunden. Nutze organization.interlink_categories.GET.");
            }
            if (!$category->is_active) {
                return ToolResult::error('VALIDATION_ERROR', "Interlink-Kategorie '{$category->name}' ist inaktiv.");
            }

            $type = OrganizationInterlinkType::find($typeId);
            if (!$type) {
                return ToolResult::error('NOT_FOUND', "Interlink-Typ mit ID {$typeId} nicht gefunden. Nutze organization.interlink_types.GET.");
            }
            if (!$type->is_active) {
                return ToolResult::error('VALIDATION_ERROR', "Interlink-Typ '{$type->name}' ist inaktiv.");
            }

            $validFrom = isset($arguments['valid_from']) && $arguments['valid_from'] !== '' ? $arguments['valid_from'] : null;
            $validTo = isset($arguments['valid_to']) && $arguments['valid_to'] !== '' ? $arguments['valid_to'] : null;

            if ($validFrom && $validTo && $validTo < $validFrom) {
                return ToolResult::error('VALIDATION_ERROR', 'valid_to muss nach valid_from liegen.');
            }

            $interlink = OrganizationInterlink::create([
                'name' => $name,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string)$arguments['description'] : null,
                'category_id' => $categoryId,
                'type_id' => $typeId,
                'is_bidirectional' => (bool)($arguments['is_bidirectional'] ?? false),
                'is_active' => (bool)($arguments['is_active'] ?? true),
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            $interlink->load(['category', 'type']);

            return ToolResult::success([
                'id' => $interlink->id,
                'uuid' => $interlink->uuid,
                'name' => $interlink->name,
                'description' => $interlink->description,
                'category_id' => $interlink->category_id,
                'category_name' => $interlink->category?->name,
                'type_id' => $interlink->type_id,
                'type_name' => $interlink->type?->name,
                'is_bidirectional' => (bool) $interlink->is_bidirectional,
                'is_active' => (bool) $interlink->is_active,
                'valid_from' => $interlink->valid_from?->toDateString(),
                'valid_to' => $interlink->valid_to?->toDateString(),
                'metadata' => $interlink->metadata,
                'message' => 'Interlink erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Interlinks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'interlinks', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
