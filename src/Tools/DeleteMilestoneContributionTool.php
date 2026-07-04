<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMilestoneContribution;

class DeleteMilestoneContributionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.milestone_contributions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/milestone-contributions/{id} - Loest die Verknuepfung eines Contributors (OKR-Objective, Key Result) von einem Milestone. Hard-Delete (kein soft-delete auf der Poly-Tabelle).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id'            => ['type' => 'integer', 'description' => 'Optional: direkte Contribution-ID.'],
                'milestone_id'  => ['type' => 'integer', 'description' => 'Alternativ mit linkable_type+linkable_id.'],
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
                $q->where('id', (int) $arguments['id']);
            } elseif (!empty($arguments['milestone_id']) && !empty($arguments['linkable_type']) && !empty($arguments['linkable_id'])) {
                $q->where('milestone_id', (int) $arguments['milestone_id'])
                  ->where('linkable_type', (string) $arguments['linkable_type'])
                  ->where('linkable_id', (int) $arguments['linkable_id']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder id oder (milestone_id + linkable_type + linkable_id) angeben.');
            }
            $count = $q->delete();
            if ($count === 0) {
                return ToolResult::error('NOT_FOUND', 'Keine passende Contribution gefunden.');
            }
            return ToolResult::success(['deleted' => $count, 'message' => 'Contribution(s) entfernt.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'milestones', 'contributions', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'destructive',
            'idempotent'    => true,
        ];
    }
}
