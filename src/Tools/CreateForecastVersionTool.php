<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationForecast;

/**
 * Erstellt eine neue Version eines bestehenden Forecasts und setzt sie als current_version.
 * Ruft intern OrganizationForecast::createNewVersion() auf.
 */
class CreateForecastVersionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.forecasts.versions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/forecasts/{id}/versions - Legt eine neue Version des Forecast-Contents an und setzt sie als current_version. '
            . 'Alter Content bleibt in vorherigen Versionen erhalten (Historie).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'forecast_id' => ['type' => 'integer', 'description' => 'PFLICHT.'],
                'content'     => ['type' => 'string', 'description' => 'Neuer Markdown-Content (PFLICHT).'],
                'change_note' => ['type' => 'string', 'description' => 'Optional: Grund der Aenderung.'],
            ],
            'required' => ['forecast_id', 'content'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $forecastId = (int) ($arguments['forecast_id'] ?? 0);
            $content    = (string) ($arguments['content'] ?? '');
            if ($forecastId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'forecast_id erforderlich.');
            }
            if ($content === '') {
                return ToolResult::error('VALIDATION_ERROR', 'content erforderlich.');
            }

            $forecast = OrganizationForecast::find($forecastId);
            if (!$forecast) {
                return ToolResult::error('NOT_FOUND', "Forecast #{$forecastId} nicht gefunden.");
            }

            $version = $forecast->createNewVersion($content, $arguments['change_note'] ?? null);
            $forecast->refresh();

            return ToolResult::success([
                'forecast_id'        => $forecast->id,
                'version_id'         => $version->id,
                'version'            => $version->version,
                'current_version_id' => $forecast->current_version_id,
                'message'            => 'Neue Version angelegt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'forecasts', 'versions', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
