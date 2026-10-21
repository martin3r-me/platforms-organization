<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMilestoneContribution;
use Platform\Organization\Services\ObjectTypeRegistry;

class DeleteMilestoneContributionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.milestone_contributions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/milestone-contributions - Loest einen Beitrag eines Objectives/Key Results von einem Meilenstein. '
            . 'Entweder id ODER milestone_id + linkable_type + linkable_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id'            => ['type' => 'integer', 'description' => 'Contribution-ID.'],
                'milestone_id'  => ['type' => 'integer'],
                'linkable_type' => ['type' => 'string'],
                'linkable_id'   => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $q = OrganizationMilestoneContribution::query();
            if (!empty($arguments['id'])) {
                $q->whereKey((int) $arguments['id']);
            } elseif (!empty($arguments['milestone_id']) && !empty($arguments['linkable_type']) && !empty($arguments['linkable_id'])) {
                $alias = ObjectTypeRegistry::resolve((string) $arguments['linkable_type']);
                if (!$alias) {
                    return ToolResult::error('VALIDATION_ERROR', "Objekttyp '{$arguments['linkable_type']}' ist nicht registriert.");
                }
                $q->where('milestone_id', (int) $arguments['milestone_id'])
                    ->where('linkable_type', $alias)
                    ->where('linkable_id', (int) $arguments['linkable_id']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'id oder milestone_id + linkable_type + linkable_id angeben.');
            }
            $c = $q->first();
            if (!$c) {
                return ToolResult::error('NOT_FOUND', 'Beitrag nicht gefunden.');
            }
            $id = $c->id;
            $c->delete();

            return ToolResult::success(['id' => $id, 'message' => 'Beitrag geloest.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'milestones', 'contributions', 'okr', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
