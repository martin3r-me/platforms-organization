<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateCostCenterTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.cost_centers.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/cost-centers/{id} - Aktualisiert eine Kostenstelle im Root/Elterteam. Parameter: cost_center_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'cost_center_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Kostenstelle (ERFORDERLICH). Nutze organization.cost_centers.GET.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Code ("" zum Leeren).',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung ("" zum Leeren).',
                ],
                'root_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: root_entity_id (0/null zum Globalisieren).',
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
            'required' => ['cost_center_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int)$resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'cost_center_id',
                OrganizationCostCenter::class,
                'NOT_FOUND',
                'Kostenstelle nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationCostCenter $cc */
            $cc = $found['model'];
            if ((int)$cc->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kostenstelle gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];
            if (array_key_exists('code', $arguments)) {
                $code = trim((string)($arguments['code'] ?? ''));
                $update['code'] = $code === '' ? null : $code;
                if ($update['code'] !== null) {
                    $exists = OrganizationCostCenter::query()
                        ->where('team_id', $rootTeamId)
                        ->where('code', $update['code'])
                        ->where('id', '!=', $cc->id)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        return ToolResult::error('VALIDATION_ERROR', "Kostenstelle mit code '{$update['code']}' existiert bereits im Root/Elterteam.");
                    }
                }
            }
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
            if (array_key_exists('root_entity_id', $arguments)) {
                $rid = $arguments['root_entity_id'];
                if ($rid === null || $rid === '' || $rid === 'null' || $rid === 0 || $rid === '0') {
                    $update['root_entity_id'] = null;
                } else {
                    $update['root_entity_id'] = (int)$rid;
                }
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool)$arguments['is_active'];
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (!empty($update)) {
                $cc->update($update);
            }
            $cc->refresh();

            return ToolResult::success([
                'id' => $cc->id,
                'code' => $cc->code,
                'name' => $cc->name,
                'team_id' => $cc->team_id,
                'root_entity_id' => $cc->root_entity_id,
                'is_active' => (bool)$cc->is_active,
                'message' => 'Kostenstelle erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Kostenstelle: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'cost_centers', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

