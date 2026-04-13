<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationInterlink;
use Platform\Organization\Models\OrganizationInterlinkCategory;
use Platform\Organization\Models\OrganizationInterlinkType;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateInterlinkTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.interlinks.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/interlinks/{id} - Aktualisiert einen Interlink. Nutze organization.interlinks.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'interlink_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Interlinks.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung ("" zum Leeren).',
                ],
                'url' => [
                    'type' => 'string',
                    'description' => 'Optional: URL/Link ("" zum Leeren).',
                ],
                'reference' => [
                    'type' => 'string',
                    'description' => 'Optional: Kennung/Referenz ("" zum Leeren).',
                ],
                'category_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neue Kategorie-ID. Nutze organization.interlink_categories.GET.',
                ],
                'type_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Neuer Typ-ID. Nutze organization.interlink_types.GET.',
                ],
                'is_bidirectional' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Bidirektional ja/nein.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
                'valid_from' => [
                    'type' => 'string',
                    'description' => 'Optional: Gültig ab (YYYY-MM-DD, "" zum Leeren).',
                ],
                'valid_to' => [
                    'type' => 'string',
                    'description' => 'Optional: Gültig bis (YYYY-MM-DD, "" zum Leeren).',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Metadatenobjekt (null zum Leeren).',
                ],
            ],
            'required' => ['interlink_id'],
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
                'interlink_id',
                OrganizationInterlink::class,
                'NOT_FOUND',
                'Interlink nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationInterlink $interlink */
            $interlink = $found['model'];

            if ((int) $interlink->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Interlink gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];

            if (array_key_exists('name', $arguments)) {
                $name = trim((string)($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }

            if (array_key_exists('description', $arguments)) {
                $d = (string)($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }

            if (array_key_exists('url', $arguments)) {
                $v = (string)($arguments['url'] ?? '');
                $update['url'] = $v === '' ? null : $v;
            }

            if (array_key_exists('reference', $arguments)) {
                $v = (string)($arguments['reference'] ?? '');
                $update['reference'] = $v === '' ? null : $v;
            }

            if (array_key_exists('category_id', $arguments) && $arguments['category_id'] !== null) {
                $catId = (int) $arguments['category_id'];
                $category = OrganizationInterlinkCategory::find($catId);
                if (!$category) {
                    return ToolResult::error('NOT_FOUND', "Interlink-Kategorie mit ID {$catId} nicht gefunden.");
                }
                if (!$category->is_active) {
                    return ToolResult::error('VALIDATION_ERROR', "Interlink-Kategorie '{$category->name}' ist inaktiv.");
                }
                $update['category_id'] = $catId;
            }

            if (array_key_exists('type_id', $arguments) && $arguments['type_id'] !== null) {
                $tId = (int) $arguments['type_id'];
                $type = OrganizationInterlinkType::find($tId);
                if (!$type) {
                    return ToolResult::error('NOT_FOUND', "Interlink-Typ mit ID {$tId} nicht gefunden.");
                }
                if (!$type->is_active) {
                    return ToolResult::error('VALIDATION_ERROR', "Interlink-Typ '{$type->name}' ist inaktiv.");
                }
                $update['type_id'] = $tId;
            }

            if (array_key_exists('is_bidirectional', $arguments)) {
                $update['is_bidirectional'] = (bool) $arguments['is_bidirectional'];
            }

            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (array_key_exists('valid_from', $arguments)) {
                $vf = (string) ($arguments['valid_from'] ?? '');
                $update['valid_from'] = $vf === '' ? null : $vf;
            }
            if (array_key_exists('valid_to', $arguments)) {
                $vt = (string) ($arguments['valid_to'] ?? '');
                $update['valid_to'] = $vt === '' ? null : $vt;
            }

            $finalValidFrom = $update['valid_from'] ?? $interlink->valid_from?->toDateString();
            $finalValidTo = $update['valid_to'] ?? $interlink->valid_to?->toDateString();
            if ($finalValidFrom && $finalValidTo && $finalValidTo < $finalValidFrom) {
                return ToolResult::error('VALIDATION_ERROR', 'valid_to muss nach valid_from liegen.');
            }

            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (!empty($update)) {
                $interlink->update($update);
            }
            $interlink->refresh();
            $interlink->load(['category', 'type']);

            return ToolResult::success([
                'id' => $interlink->id,
                'uuid' => $interlink->uuid,
                'name' => $interlink->name,
                'description' => $interlink->description,
                'url' => $interlink->url,
                'reference' => $interlink->reference,
                'category_id' => $interlink->category_id,
                'category_name' => $interlink->category?->name,
                'type_id' => $interlink->type_id,
                'type_name' => $interlink->type?->name,
                'is_bidirectional' => (bool) $interlink->is_bidirectional,
                'is_active' => (bool) $interlink->is_active,
                'valid_from' => $interlink->valid_from?->toDateString(),
                'valid_to' => $interlink->valid_to?->toDateString(),
                'metadata' => $interlink->metadata,
                'message' => 'Interlink erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Interlinks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'interlinks', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
