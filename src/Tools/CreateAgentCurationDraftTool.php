<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationAgentCuration;
use Platform\Organization\Models\OrganizationAgentRule;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

/**
 * Kuratier-ENTWURF für die Arbeitsweise eines Agenten anlegen: eine Regel korrigieren, stilllegen oder
 * eine neue hinzufügen. Entwürfe gehen NICHT ans Engram — erst wenn ein Mensch sie im Reiter „Gedächtnis“
 * freigibt. So können Menschen und Agenten Vorschläge machen, entschieden wird in der Organisation.
 */
class CreateAgentCurationDraftTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.agent_curations.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/agents/{entity_id}/curations - Legt einen Kuratier-ENTWURF für die Arbeitsweise eines Agenten an (wird erst nach Freigabe im Reiter „Gedächtnis“ an den Agenten geschickt). '
            . 'op=revise (rule_id + text: neue Fassung löst die alte ab), op=retire (rule_id: stilllegen), op=add (text + kind step|rule|avoid + optional trigger: neue Regel). '
            . 'note = Begründung, die Mensch und Agent sehen. Regeln vorher mit organization.agent_rules.GET lesen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: Team aus Kontext.'],
                'entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Entity-ID des Agenten.'],
                'op' => ['type' => 'string', 'enum' => ['revise', 'retire', 'add'], 'description' => 'ERFORDERLICH: revise | retire | add.'],
                'rule_id' => ['type' => 'integer', 'description' => 'Für revise/retire: die Regel-Kennung (rN → N).'],
                'text' => ['type' => 'string', 'description' => 'Für revise/add: der Regeltext.'],
                'kind' => ['type' => 'string', 'enum' => ['step', 'rule', 'avoid'], 'description' => 'Für add: step = Schritt im Ablauf, rule = Vorgehen, avoid = bewusst nicht tun.'],
                'trigger' => ['type' => 'string', 'description' => 'Für add: Anlass, bei dem die Regel gilt (leer = Kern, in jedem Takt).'],
                'note' => ['type' => 'string', 'description' => 'ERFORDERLICH: Begründung (ein bis zwei Sätze).'],
            ],
            'required' => ['entity_id', 'op', 'note'],
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
            $op = (string) ($arguments['op'] ?? '');
            $note = trim((string) ($arguments['note'] ?? ''));
            $text = trim((string) ($arguments['text'] ?? ''));
            if ($note === '') {
                return ToolResult::error('VALIDATION_ERROR', 'note (Begründung) ist erforderlich.');
            }

            if ($op === 'add') {
                $kind = (string) ($arguments['kind'] ?? '');
                if ($text === '' || ! in_array($kind, ['step', 'rule', 'avoid'], true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'add braucht text und kind (step|rule|avoid).');
                }
                $path = 'rules';
                $baseSha = str_repeat('0', 64);
                $content = json_encode(['kind' => $kind, 'trigger' => trim((string) ($arguments['trigger'] ?? '')), 'text' => $text], JSON_UNESCAPED_UNICODE);
                $rule = null;
            } elseif (in_array($op, ['revise', 'retire'], true)) {
                $rule = OrganizationAgentRule::where('organization_entity_id', $agent->id)
                    ->where('rule_id', (int) ($arguments['rule_id'] ?? 0))->first();
                if (! $rule || ! $rule->isLive()) {
                    return ToolResult::error('NOT_FOUND', 'Geltende Regel nicht gefunden. Nutze organization.agent_rules.GET.');
                }
                if ($op === 'revise' && $text === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'revise braucht text (die neue Fassung).');
                }
                $path = 'rules/' . $rule->rule_id;
                $baseSha = $rule->sha;
                $content = $op === 'revise' ? $text : null;
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'op muss revise, retire oder add sein.');
            }

            $c = OrganizationAgentCuration::create([
                'organization_entity_id' => $agent->id,
                'path' => $path,
                'op' => $op,
                'base_sha' => $baseSha,
                'content' => $content,
                'note' => $note,
                'status' => 'draft',
                'created_by_user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $c->id,
                'agent' => $agent->name,
                'op' => $op,
                'rule_id' => $rule?->rule_id,
                'status' => 'draft',
                'message' => 'Entwurf angelegt — wartet auf Freigabe im Reiter „Gedächtnis“ des Agenten.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Anlegen des Entwurfs: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'agents', 'rules', 'memory', 'curation', 'draft'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
