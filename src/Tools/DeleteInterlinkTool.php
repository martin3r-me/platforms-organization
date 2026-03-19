<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationInterlink;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteInterlinkTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.interlinks.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/interlinks/{id} - Löscht einen Interlink (soft delete).';
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
            ],
            'required' => ['interlink_id'],
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

            $interlink->delete();

            return ToolResult::success([
                'id' => $interlink->id,
                'message' => 'Interlink gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Interlinks: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'interlinks', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
