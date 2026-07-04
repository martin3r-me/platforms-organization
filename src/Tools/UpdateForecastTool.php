<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationForecast;

class UpdateForecastTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.forecasts.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/forecasts/{id} - Aktualisiert einen Forecast. Content-Aenderungen bevorzugt ueber forecasts.versions.POST (versioniert).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id'                 => ['type' => 'integer', 'description' => 'PFLICHT.'],
                'title'              => ['type' => 'string'],
                'target_date'        => ['type' => 'string'],
                'content'            => ['type' => 'string', 'description' => 'Direkt-Update ohne Versionierung.'],
                'current_version_id' => ['type' => 'integer'],
                'entity_id'          => ['type' => 'integer'],
            ],
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
            foreach (['title', 'target_date', 'content', 'current_version_id', 'entity_id'] as $col) {
                if (array_key_exists($col, $arguments)) {
                    $f->{$col} = $arguments[$col];
                }
            }
            $f->save();
            return ToolResult::success([
                'id' => $f->id, 'title' => $f->title,
                'target_date' => $f->target_date?->toDateString(),
                'current_version_id' => $f->current_version_id,
                'entity_id' => $f->entity_id, 'message' => 'Aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'forecasts', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
