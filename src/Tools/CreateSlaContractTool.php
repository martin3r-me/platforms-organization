<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationSlaContract;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateSlaContractTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.sla_contracts.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/sla_contracts - Erstellt einen SLA-Vertrag. Felder: name, response_time_hours, resolution_time_hours, error_tolerance_percent.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name des SLA-Vertrags.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung.',
                ],
                'response_time_hours' => [
                    'type' => 'integer',
                    'description' => 'Optional: Reaktionszeit in Stunden.',
                ],
                'resolution_time_hours' => [
                    'type' => 'integer',
                    'description' => 'Optional: Lösungszeit in Stunden.',
                ],
                'error_tolerance_percent' => [
                    'type' => 'integer',
                    'description' => 'Optional: Fehlertoleranz in Prozent (0-100).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                    'default' => true,
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            if (isset($arguments['error_tolerance_percent'])) {
                $etp = (int) $arguments['error_tolerance_percent'];
                if ($etp < 0 || $etp > 100) {
                    return ToolResult::error('VALIDATION_ERROR', 'error_tolerance_percent muss zwischen 0 und 100 liegen.');
                }
            }

            $sla = OrganizationSlaContract::create([
                'name' => $name,
                'description' => (array_key_exists('description', $arguments) && $arguments['description'] !== '') ? (string) $arguments['description'] : null,
                'response_time_hours' => isset($arguments['response_time_hours']) ? (int) $arguments['response_time_hours'] : null,
                'resolution_time_hours' => isset($arguments['resolution_time_hours']) ? (int) $arguments['resolution_time_hours'] : null,
                'error_tolerance_percent' => isset($arguments['error_tolerance_percent']) ? (int) $arguments['error_tolerance_percent'] : null,
                'is_active' => (bool) ($arguments['is_active'] ?? true),
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $sla->id,
                'uuid' => $sla->uuid,
                'name' => $sla->name,
                'description' => $sla->description,
                'response_time_hours' => $sla->response_time_hours,
                'resolution_time_hours' => $sla->resolution_time_hours,
                'error_tolerance_percent' => $sla->error_tolerance_percent,
                'is_active' => (bool) $sla->is_active,
                'message' => 'SLA-Vertrag erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des SLA-Vertrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'sla', 'contracts', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
