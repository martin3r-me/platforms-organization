<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationVsmFunction;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateVsmFunctionTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.vsm_functions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/vsm-functions - Erstellt eine VSM-Funktion im Root/Elterteam. Kann global (scope_entity_id=null) oder entity-spezifisch sein.';
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
                    'description' => 'Optional: Code der VSM-Funktion.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name der VSM-Funktion.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'scope_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: scope_entity_id (NULL = global, X = entity-spezifisch).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
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
            $rootTeamId = (int) $resolved['root_team_id'];

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $code = null;
            if (array_key_exists('code', $arguments)) {
                $code = trim((string) ($arguments['code'] ?? ''));
                $code = $code === '' ? null : $code;
            }

            $rid = $arguments['scope_entity_id'] ?? null;
            $scopeEntityId = null;
            if ($rid !== null && $rid !== '' && $rid !== 'null' && $rid !== 0 && $rid !== '0') {
                $scopeEntityId = (int) $rid;
            }

            $fn = OrganizationVsmFunction::create([
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
                'code' => $code,
                'name' => $name,
                'description' => ($arguments['description'] ?? null) ?: null,
                'scope_entity_id' => $scopeEntityId,
                'is_active' => (bool) ($arguments['is_active'] ?? true),
                'metadata' => (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null,
            ]);

            return ToolResult::success([
                'id' => $fn->id,
                'code' => $fn->code,
                'name' => $fn->name,
                'team_id' => $fn->team_id,
                'scope_entity_id' => $fn->scope_entity_id,
                'is_active' => (bool) $fn->is_active,
                'message' => 'VSM-Funktion erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der VSM-Funktion: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'vsm', 'functions', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
