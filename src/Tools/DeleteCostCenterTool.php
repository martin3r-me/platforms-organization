<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationCostCenter;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteCostCenterTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.cost_centers.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/cost-centers/{id} - Löscht eine Kostenstelle (soft delete). Hinweis: Wenn die Kostenstelle verlinkt ist, bitte is_active=false setzen statt löschen.';
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
            ],
            'required' => ['cost_center_id'],
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

            // Safety: don't delete if linked; prefer deactivation
            if (method_exists($cc, 'links') && $cc->links()->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Kostenstelle ist verlinkt und kann nicht gelöscht werden. Setze stattdessen is_active=false.');
            }

            $cc->delete();

            return ToolResult::success([
                'id' => $cc->id,
                'message' => 'Kostenstelle gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen der Kostenstelle: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'cost_centers', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}

