<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateCostCenterTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.cost_centers.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/cost-centers - Erstellt eine Kostenstelle im Root/Elterteam (IDs nie raten; zuerst organization.cost_centers.GET).';
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
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Kostenstellen-Code (empfohlen).',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Name der Kostenstelle (ERFORDERLICH).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'root_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: root_entity_id (NULL = global, X = entity-spezifisch).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default true.',
                    'default' => true,
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Freies JSON-Metadatenobjekt.',
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int)$resolved['root_team_id'];

            $name = trim((string)($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $code = null;
            if (array_key_exists('code', $arguments)) {
                $code = trim((string)($arguments['code'] ?? ''));
                $code = $code === '' ? null : $code;
            }

            if ($code !== null) {
                $exists = OrganizationCostCenter::query()
                    ->where('team_id', $rootTeamId)
                    ->where('code', $code)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Kostenstelle mit code '{$code}' existiert bereits im Root/Elterteam.");
                }
            }

            $rid = $arguments['root_entity_id'] ?? null;
            $rootEntityId = null;
            if ($rid !== null && $rid !== '' && $rid !== 'null' && $rid !== 0 && $rid !== '0') {
                $rootEntityId = (int)$rid;
            }

            $cc = OrganizationCostCenter::create([
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
                'code' => $code,
                'name' => $name,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string)$arguments['description'] : null,
                'root_entity_id' => $rootEntityId,
                'is_active' => (bool)($arguments['is_active'] ?? true),
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            return ToolResult::success([
                'id' => $cc->id,
                'code' => $cc->code,
                'name' => $cc->name,
                'team_id' => $cc->team_id,
                'root_entity_id' => $cc->root_entity_id,
                'is_active' => (bool)$cc->is_active,
                'message' => 'Kostenstelle erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Kostenstelle: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'cost_centers', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}

