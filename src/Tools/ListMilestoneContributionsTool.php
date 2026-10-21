<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMilestoneContribution;
use Platform\Organization\Services\ObjectTypeRegistry;

class ListMilestoneContributionsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.milestone_contributions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/milestone-contributions - Listet Beitraege von Objectives/Key Results auf Meilensteine. '
            . 'Filter: milestone_id (was zahlt auf M ein?), linkable_type + linkable_id (worauf zahlt X ein?), entity_id (alle Beitraege auf die Map einer Entity).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'milestone_id'  => ['type' => 'integer', 'description' => 'Optional: nur Beitraege auf diesen Meilenstein.'],
                'linkable_type' => ['type' => 'string', 'description' => 'Optional (mit linkable_id): Objekttyp (Kurzname, Morph-Alias oder Klasse).'],
                'linkable_id'   => ['type' => 'integer', 'description' => 'Optional (mit linkable_type).'],
                'entity_id'     => ['type' => 'integer', 'description' => 'Optional: Meilensteine dieser Entity (z. B. Venture).'],
                'limit'         => ['type' => 'integer', 'description' => 'Optional, default 100, max 500.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $q = OrganizationMilestoneContribution::query()->with(['milestone.focusArea', 'milestone.entity', 'linkable']);
            if (!empty($arguments['milestone_id'])) {
                $q->where('milestone_id', (int) $arguments['milestone_id']);
            }
            if (!empty($arguments['linkable_type']) && !empty($arguments['linkable_id'])) {
                $alias = ObjectTypeRegistry::resolve((string) $arguments['linkable_type']);
                if (!$alias) {
                    return ToolResult::error('VALIDATION_ERROR', "Objekttyp '{$arguments['linkable_type']}' ist nicht registriert.");
                }
                $q->where('linkable_type', $alias)->where('linkable_id', (int) $arguments['linkable_id']);
            }
            if (!empty($arguments['entity_id'])) {
                $q->whereHas('milestone', fn ($m) => $m->where('entity_id', (int) $arguments['entity_id']));
            }
            if ($teamId = $context->team?->id) {
                $q->where('team_id', $teamId);
            }
            $limit = max(1, min(500, (int) ($arguments['limit'] ?? 100)));

            $rows = $q->orderBy('milestone_id')->limit($limit)->get()->map(fn (OrganizationMilestoneContribution $c) => array_merge(
                $c->toAlignmentArray(),
                [
                    'linkable_type'  => $c->linkable_type,
                    'linkable_id'    => $c->linkable_id,
                    'linkable_title' => $c->linkable?->title ?? $c->linkable?->name,
                ]
            ))->values()->all();

            return ToolResult::success(['data' => $rows, 'count' => count($rows)]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'strategy', 'milestones', 'contributions', 'okr', 'list'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
