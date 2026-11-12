<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\AgentRuntimeProfile;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Das Agent-Profil LESEN — genau das, was der Daemon über GET /api/org/agent/profile bekommt:
 * Job-Profil (Stelle des Mitarbeiters in People), Bezugsperson aus der Struktur, Stufen, Governor,
 * Einstellungen. So lässt sich per MCP prüfen, womit ein Agent tatsächlich arbeitet.
 */
class GetAgentProfileTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.agent_profile.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/agent-profile - Zeigt das Profil eines Agenten so, wie der Agent es zieht: job_profile (Stelle des Agenten als Mitarbeiter in People — people.employee_job_profiles; mit vsm_level, Verantwortlichkeiten, KPIs), supervisor (Bezugsperson aus der Org-Struktur — schlägt reporting.reports_to im Job-Profil), stages, governor, settings. Ändern: organization.agent_profile.PUT bzw. people.job_profiles.PATCH.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Entity-ID des Agenten.'],
            ],
            'required' => ['entity_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $agent = OrganizationEntity::query()->agents()
                ->where('id', (int) ($arguments['entity_id'] ?? 0))
                ->where('team_id', (int) $resolved['root_team_id'])->first();
            if (! $agent) {
                return ToolResult::error('NOT_FOUND', 'Agent nicht gefunden. Nutze organization.entities.GET.');
            }
            $profile = $agent->agentProfile;
            if (! $profile) {
                return ToolResult::error('NOT_FOUND', 'Der Agent hat noch kein Agent-Profil. Anlegen mit organization.agent_profile.PUT.');
            }

            return ToolResult::success([
                'agent' => [
                    'entity_id' => $agent->id,
                    'name' => $agent->name,
                    'last_heartbeat_at' => $profile->last_heartbeat_at?->toIso8601String(),
                ],
                'profile' => app(AgentRuntimeProfile::class)->build($profile),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Agent-Profils: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['organization', 'agent_profile', 'agent', 'profile', 'job_profile', 'supervisor'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
