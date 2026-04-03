<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationSlaContract;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateSlaContractTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.sla_contracts.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/sla_contracts/{id} - Aktualisiert einen SLA-Vertrag. Nutze organization.sla_contracts.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'sla_contract_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des SLA-Vertrags.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung ("" zum Leeren).',
                ],
                'response_time_hours' => [
                    'type' => 'integer',
                    'description' => 'Optional: Reaktionszeit in Stunden (null zum Leeren).',
                ],
                'resolution_time_hours' => [
                    'type' => 'integer',
                    'description' => 'Optional: Lösungszeit in Stunden (null zum Leeren).',
                ],
                'error_tolerance_percent' => [
                    'type' => 'integer',
                    'description' => 'Optional: Fehlertoleranz in Prozent 0-100 (null zum Leeren).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
            ],
            'required' => ['sla_contract_id'],
        ]);
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
                'sla_contract_id',
                OrganizationSlaContract::class,
                'NOT_FOUND',
                'SLA-Vertrag nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationSlaContract $sla */
            $sla = $found['model'];

            if ((int) $sla->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'SLA-Vertrag gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];

            if (array_key_exists('name', $arguments)) {
                $name = trim((string) ($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }

            if (array_key_exists('description', $arguments)) {
                $d = (string) ($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }

            if (array_key_exists('response_time_hours', $arguments)) {
                $update['response_time_hours'] = $arguments['response_time_hours'] !== null ? (int) $arguments['response_time_hours'] : null;
            }

            if (array_key_exists('resolution_time_hours', $arguments)) {
                $update['resolution_time_hours'] = $arguments['resolution_time_hours'] !== null ? (int) $arguments['resolution_time_hours'] : null;
            }

            if (array_key_exists('error_tolerance_percent', $arguments)) {
                if ($arguments['error_tolerance_percent'] !== null) {
                    $etp = (int) $arguments['error_tolerance_percent'];
                    if ($etp < 0 || $etp > 100) {
                        return ToolResult::error('VALIDATION_ERROR', 'error_tolerance_percent muss zwischen 0 und 100 liegen.');
                    }
                    $update['error_tolerance_percent'] = $etp;
                } else {
                    $update['error_tolerance_percent'] = null;
                }
            }

            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (! empty($update)) {
                $sla->update($update);
            }
            $sla->refresh();

            return ToolResult::success([
                'id' => $sla->id,
                'uuid' => $sla->uuid,
                'name' => $sla->name,
                'description' => $sla->description,
                'response_time_hours' => $sla->response_time_hours,
                'resolution_time_hours' => $sla->resolution_time_hours,
                'error_tolerance_percent' => $sla->error_tolerance_percent,
                'is_active' => (bool) $sla->is_active,
                'message' => 'SLA-Vertrag erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des SLA-Vertrags: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'sla', 'contracts', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
