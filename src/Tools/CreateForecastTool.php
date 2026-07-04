<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationForecast;

class CreateForecastTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.forecasts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/forecasts - Erstellt ein Forecast (versionierbares strategisches Dokument) an einer Carrier-Entity. '
            . 'Optional wird direkt eine erste Version aus content angelegt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id'          => ['type' => 'integer', 'description' => 'Carrier-Entity (PFLICHT).'],
                'title'              => ['type' => 'string', 'description' => 'Titel des Forecasts (PFLICHT).'],
                'target_date'        => ['type' => 'string', 'description' => 'Zieldatum YYYY-MM-DD (PFLICHT).'],
                'content'            => ['type' => 'string', 'description' => 'Aktueller Markdown-Content (optional).'],
                'create_initial_version' => ['type' => 'boolean', 'description' => 'Optional: aus content direkt eine erste Version anlegen und als current markieren.'],
                'team_id'            => ['type' => 'integer', 'description' => 'Optional: Team-Scope.'],
                'user_id'            => ['type' => 'integer', 'description' => 'Optional: User-ID des Erstellers.'],
                'created_at'         => ['type' => 'string', 'description' => 'Optional: expliziter Erstellzeitpunkt.'],
                'updated_at'         => ['type' => 'string', 'description' => 'Optional: expliziter Aenderungszeitpunkt.'],
                'deleted_at'         => ['type' => 'string', 'description' => 'Optional: Soft-Delete-Zeitpunkt.'],
            ],
            'required' => ['entity_id', 'title', 'target_date'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $entityId = (int) ($arguments['entity_id'] ?? 0);
            if ($entityId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }
            $teamId = (int) ($arguments['team_id'] ?? $context->team?->id ?? 0);
            if ($teamId <= 0) {
                return ToolResult::error('MISSING_TEAM', 'team_id konnte nicht aufgeloest werden.');
            }

            $data = [
                'entity_id'   => $entityId,
                'title'       => trim((string) $arguments['title']),
                'target_date' => $arguments['target_date'],
                'content'     => $arguments['content'] ?? null,
                'team_id'     => $teamId,
                'user_id'     => isset($arguments['user_id']) ? (int) $arguments['user_id'] : ($context->user?->id ?? null),
            ];
            foreach (['created_at', 'updated_at', 'deleted_at'] as $ts) {
                if (!empty($arguments[$ts])) {
                    $data[$ts] = $arguments[$ts];
                }
            }

            $forecast = OrganizationForecast::create($data);

            if (!empty($arguments['create_initial_version']) && !empty($arguments['content'])) {
                $forecast->createNewVersion((string) $arguments['content'], 'Initial version');
                $forecast->refresh();
            }

            return ToolResult::success([
                'id'                 => $forecast->id,
                'uuid'               => $forecast->uuid,
                'entity_id'          => $forecast->entity_id,
                'title'              => $forecast->title,
                'target_date'        => $forecast->target_date?->toDateString(),
                'current_version_id' => $forecast->current_version_id,
                'team_id'            => $forecast->team_id,
                'message'            => 'Forecast erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'forecasts', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => false,
        ];
    }
}
