<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationPerspective;
use Platform\Organization\Services\PerspectiveService;

class SwitchPerspectiveTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.perspectives.switch';
    }

    public function getDescription(): string
    {
        return 'POST /organization/perspectives/switch - Wechselt die aktive Perspektive für die aktuelle Session. Beeinflusst alle nachfolgenden Organization-Tool-Aufrufe, die VSM-Daten zurückliefern. Nutze organization.perspectives.GET um verfügbare Perspektiven zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'perspective_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Perspektive, zu der gewechselt werden soll.',
                ],
            ],
            'required' => ['perspective_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $teamId = $context->getTeamId();
            $perspectiveId = (int) ($arguments['perspective_id'] ?? 0);

            if (!$perspectiveId) {
                return ToolResult::error('VALIDATION_ERROR', 'perspective_id ist erforderlich.');
            }

            $perspective = PerspectiveService::switchTo($perspectiveId, $teamId);

            if (!$perspective) {
                return ToolResult::error('NOT_FOUND', "Perspektive {$perspectiveId} nicht gefunden oder gehört nicht zu diesem Team.");
            }

            $service = new PerspectiveService();
            $entitiesCount = $service->entitiesInView($perspective)->count();

            return ToolResult::success([
                'message' => "Perspektive gewechselt zu: {$perspective->name}",
                'perspective' => [
                    'id' => $perspective->id,
                    'uuid' => $perspective->uuid,
                    'name' => $perspective->name,
                    'description' => $perspective->description,
                    'is_default' => (bool) $perspective->is_default,
                    'entities_in_view' => $entitiesCount,
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Perspektive-Wechsel: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'write',
            'tags' => ['organization', 'perspectives', 'session', 'switch'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
