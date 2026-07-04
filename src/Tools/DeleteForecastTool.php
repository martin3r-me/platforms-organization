<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationForecast;

class DeleteForecastTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.forecasts.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/forecasts/{id} - Soft-Delete eines Forecasts (kaskadiert nicht auf FocusAreas, die separat gepflegt werden muessen).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer', 'description' => 'PFLICHT.']],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $id = (int) ($arguments['id'] ?? 0);
            $f = OrganizationForecast::find($id);
            if (!$f) {
                return ToolResult::error('NOT_FOUND', "Forecast #{$id} nicht gefunden.");
            }
            $f->delete();
            return ToolResult::success(['id' => $id, 'message' => 'Soft-deleted.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'forecasts', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'destructive',
            'idempotent'    => true,
        ];
    }
}
