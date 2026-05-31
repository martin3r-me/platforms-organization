<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteEnvironmentSourceTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.environment_sources.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/environment_sources/{id} - Löscht eine Environment-Source (soft delete).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'source_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Environment-Source.',
                ],
            ],
            'required' => ['source_id'],
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
                'source_id',
                OrganizationEnvironmentSource::class,
                'NOT_FOUND',
                'Environment-Source nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationEnvironmentSource $source */
            $source = $found['model'];

            if ((int) $source->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Environment-Source gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $source->delete();

            return ToolResult::success([
                'id' => $source->id,
                'message' => 'Environment-Source gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Environment-Source: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'environment', 'vsm', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
