<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationAgentRule;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Die Arbeitsweise eines Agenten als Regeln lesen (Spiegel aus dem Engram) — Grundlage, um gezielt
 * Kuratier-Entwürfe zu einzelnen Regeln anzulegen (organization.agent_curations.POST).
 */
class ListAgentRulesTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.agent_rules.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/agents/{entity_id}/rules - Listet die Regeln der Arbeitsweise eines Agenten (Engram): rule_id, Art (step/rule/avoid), Anlass (trigger, leer = Kern), Text, Status, Bewährung. Standard: nur geltende Regeln (active/probation).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Entity-ID des Agenten.'],
                'status' => ['type' => 'string', 'description' => 'Optional: live (Standard: active+probation) | proposed | history (superseded+retired) | all.'],
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
            $statuses = match ($arguments['status'] ?? 'live') {
                'proposed' => ['proposed'],
                'history' => ['superseded', 'retired'],
                'all' => null,
                default => OrganizationAgentRule::LIVE,
            };
            $q = OrganizationAgentRule::where('organization_entity_id', $agent->id)->orderBy('ord')->orderBy('rule_id');
            if ($statuses) {
                $q->whereIn('status', $statuses);
            }

            return ToolResult::success([
                'agent' => ['entity_id' => $agent->id, 'name' => $agent->name],
                'rules' => $q->get()->map(fn ($r) => [
                    'rule_id' => $r->rule_id,
                    'kind' => $r->kind,
                    'trigger' => $r->trigger,
                    'text' => $r->text,
                    'why' => $r->why,
                    'status' => $r->status,
                    'confirmed' => $r->confirmed,
                    'refuted' => $r->refuted,
                    'supersedes' => $r->supersedes ?: null,
                ])->values()->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Regeln: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['organization', 'agents', 'rules', 'memory'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'read',
            'idempotent' => true,
        ];
    }
}
