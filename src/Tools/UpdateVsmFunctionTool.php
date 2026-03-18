<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationVsmFunction;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateVsmFunctionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.vsm_functions.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/vsm-functions/{id} - Aktualisiert eine VSM-Funktion. Nutze organization.vsm_functions.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'vsm_function_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der VSM-Funktion.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Code ("" zum Leeren).',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung ("" zum Leeren).',
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
            'required' => ['vsm_function_id'],
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
                'vsm_function_id',
                OrganizationVsmFunction::class,
                'NOT_FOUND',
                'VSM-Funktion nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            $fn = $found['model'];
            if ((int) $fn->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'VSM-Funktion gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];
            if (array_key_exists('code', $arguments)) {
                $code = trim((string) ($arguments['code'] ?? ''));
                $update['code'] = $code === '' ? null : $code;
            }
            if (array_key_exists('name', $arguments)) {
                $name = trim((string) ($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }
            if (array_key_exists('description', $arguments)) {
                $d = (string) ($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }
            if (array_key_exists('root_entity_id', $arguments)) {
                $rid = $arguments['root_entity_id'];
                if ($rid === null || $rid === '' || $rid === 'null' || $rid === 0 || $rid === '0') {
                    $update['root_entity_id'] = null;
                } else {
                    $update['root_entity_id'] = (int) $rid;
                }
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (!empty($update)) {
                $fn->update($update);
            }
            $fn->refresh();

            return ToolResult::success([
                'id' => $fn->id,
                'code' => $fn->code,
                'name' => $fn->name,
                'team_id' => $fn->team_id,
                'root_entity_id' => $fn->root_entity_id,
                'is_active' => (bool) $fn->is_active,
                'message' => 'VSM-Funktion erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der VSM-Funktion: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'vsm', 'functions', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
