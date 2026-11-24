<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Support\AgentWorkSources;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Den Arbeitseingang eines Agenten LESEN — genau das, was der Daemon über GET /api/org/agent/work bekommt.
 * So lässt sich per MCP prüfen, ob ein Agent sieht, was ihm hingelegt wurde.
 */
class GetAgentWorkTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.agent_work.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/agent-work - Zeigt den Arbeitseingang eines Agenten so, wie der Agent ihn jeden Takt zieht: items[] (je Art von Arbeit: source, key, text, count, weight 0..1, tool zum Abarbeiten, how) und sources[] (welche Module liefern, ok=false = Quelle ausgefallen). Module legen hier Arbeit hin (z. B. Signals: Zustell-Aufträge → signals.deliveries.GET).';
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

            return ToolResult::success(['agent' => ['entity_id' => $agent->id, 'name' => $agent->name]] + AgentWorkSources::forAgent($agent));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Arbeitseingangs: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['organization', 'agent_work', 'agent', 'work', 'inbox', 'deliveries'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
