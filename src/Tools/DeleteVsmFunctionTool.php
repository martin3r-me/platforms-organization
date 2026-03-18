<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationVsmFunction;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteVsmFunctionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.vsm_functions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/vsm-functions/{id} - Löscht eine VSM-Funktion (soft delete). Bevorzuge is_active=false wenn möglich.';
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
            ],
            'required' => ['vsm_function_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
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

            $fn->delete();

            return ToolResult::success([
                'id' => $fn->id,
                'message' => 'VSM-Funktion gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der VSM-Funktion: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'vsm', 'functions', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
