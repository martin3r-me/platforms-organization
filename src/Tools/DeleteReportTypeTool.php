<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationReportType;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteReportTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.report_types.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/report_types/{id} - Löscht einen Berichtstyp (soft delete). Warnt wenn Berichte existieren, blockiert aber nicht.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'report_type_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Berichtstyps.',
                ],
            ],
            'required' => ['report_type_id'],
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
                'report_type_id',
                OrganizationReportType::class,
                'NOT_FOUND',
                'Berichtstyp nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            $reportType = $found['model'];
            if ((int) $reportType->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Berichtstyp gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $reportsCount = $reportType->reports()->count();
            $warning = null;
            if ($reportsCount > 0) {
                $warning = "Hinweis: {$reportsCount} Bericht(e) verweisen auf diesen Typ. Die Berichte bleiben erhalten.";
            }

            $reportType->delete();

            $result = [
                'id' => $reportType->id,
                'message' => 'Berichtstyp gelöscht (soft delete).',
            ];

            if ($warning) {
                $result['warning'] = $warning;
            }

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Berichtstyps: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'report_types', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
