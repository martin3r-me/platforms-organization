<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Authz\DimensionLinkAuthorizer;
use Platform\Organization\Services\ResponsibilityResolver;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class GetResponsibleTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.responsible.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/responsible - Wer ist JETZT verantwortlich für einen Knoten? Sucht ab entity_id aufwärts den ersten Knoten, '
            . 'an dem jemand eine Beziehung oder Rolle mit der Fähigkeit capability (Standard manage: Führt an, Venture Owner, Co-Owner …) hält. '
            . 'Geordnet nach Rang; Abwesende werden durch ihre Vertretung ersetzt, sonst rückt der Nächste im Rang nach. '
            . 'exclude_entity_ids = befangene Personen überspringen. direct_only=true: nur dieser Knoten, ohne Suche nach oben. '
            . 'Antwort: node, distance, holders (entity_id, user_id, name, kind person|agent, via, rank, deputy_for, reason, supervisor bei Agenten), path (übersprungene Knoten mit Grund).';
    }

    public function getSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'entity_id' => ['type' => 'integer', 'description' => 'ERFORDERLICH: Knoten, ab dem gesucht wird.'],
            'capability' => ['type' => 'string', 'enum' => ['read', 'write', 'manage'], 'description' => 'Standard: manage'],
            'exclude_entity_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'direct_only' => ['type' => 'boolean'],
            'at' => ['type' => 'string', 'description' => 'Zeitpunkt (ISO-Datum), Standard: jetzt'],
            'team_id' => ['type' => 'integer'],
        ], 'required' => ['entity_id']];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $entityId = (int) ($arguments['entity_id'] ?? 0);
            $readable = array_flip((new DimensionLinkAuthorizer())->readableEntityIds($context->user));
            if (! isset($readable[$entityId])) {
                return ToolResult::error('NOT_FOUND', 'Knoten nicht gefunden oder kein Lesezugriff.');
            }
            $cap = in_array($arguments['capability'] ?? 'manage', ['read', 'write', 'manage'], true) ? ($arguments['capability'] ?? 'manage') : 'manage';
            $exclude = array_map('intval', (array) ($arguments['exclude_entity_ids'] ?? []));
            $at = ! empty($arguments['at']) ? \Carbon\CarbonImmutable::parse($arguments['at']) : null;
            $resolver = app(ResponsibilityResolver::class);

            if (! empty($arguments['direct_only'])) {
                return ToolResult::success(['entity_id' => $entityId, 'capability' => $cap, 'holders' => $resolver->holdersAt($entityId, $cap, $exclude, $at)]);
            }

            return ToolResult::success(['capability' => $cap] + $resolver->responsibleFor($entityId, $cap, $exclude, $at));
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category' => 'read', 'tags' => ['organization', 'responsibility', 'manage', 'deputy', 'agent'], 'read_only' => true,
            'requires_auth' => true, 'requires_team' => true, 'risk_level' => 'safe', 'idempotent' => true];
    }
}
