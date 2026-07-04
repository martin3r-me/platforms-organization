<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationMilestone;
use Platform\Organization\Models\OrganizationMilestoneContribution;
use Platform\Organization\Services\MilestoneContributorRegistry;

/**
 * Verbindet einen beliebigen Contributor (z.B. okr_objective, okr_key_result)
 * mit einem Milestone auf der Transformations-Map. linkable_type muss ueber
 * die MilestoneContributorRegistry registriert sein.
 */
class CreateMilestoneContributionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.milestone_contributions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/milestone-contributions - Verknuepft ein beitragendes Objekt (z.B. okr_objective, okr_key_result) '
            . 'mit einem Milestone. linkable_type muss ein registrierter Morph-Alias sein.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'milestone_id'  => ['type' => 'integer', 'description' => 'Milestone-ID (PFLICHT).'],
                'linkable_type' => ['type' => 'string',  'description' => 'Morph-Alias, z.B. okr_objective, okr_key_result (PFLICHT).'],
                'linkable_id'   => ['type' => 'integer', 'description' => 'ID des beitragenden Objekts (PFLICHT).'],
                'weight'        => ['type' => 'integer', 'description' => 'Optional: Gewichtung 1-255. Null = 1 (default).'],
                'team_id'       => ['type' => 'integer', 'description' => 'Optional: Team-Scope. Default: aus Milestone.'],
                'created_by_user_id' => ['type' => 'integer', 'description' => 'Optional.'],
                'created_at'    => ['type' => 'string', 'description' => 'Optional.'],
                'updated_at'    => ['type' => 'string', 'description' => 'Optional.'],
            ],
            'required' => ['milestone_id', 'linkable_type', 'linkable_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $milestoneId  = (int) ($arguments['milestone_id'] ?? 0);
            $linkableType = (string) ($arguments['linkable_type'] ?? '');
            $linkableId   = (int) ($arguments['linkable_id'] ?? 0);

            if ($milestoneId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'milestone_id ist erforderlich.');
            }
            if ($linkableType === '') {
                return ToolResult::error('VALIDATION_ERROR', 'linkable_type ist erforderlich.');
            }
            if ($linkableId <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'linkable_id ist erforderlich.');
            }

            $registry = resolve(MilestoneContributorRegistry::class);
            if (!$registry->getProvider($linkableType)) {
                $known = $registry->knownAliases();
                return ToolResult::error(
                    'VALIDATION_ERROR',
                    "linkable_type '{$linkableType}' ist kein registrierter Contributor. Bekannt: " . implode(', ', $known) . '.'
                );
            }

            $milestone = OrganizationMilestone::find($milestoneId);
            if (!$milestone) {
                return ToolResult::error('NOT_FOUND', "Milestone #{$milestoneId} nicht gefunden.");
            }

            $existing = OrganizationMilestoneContribution::query()
                ->where('milestone_id', $milestoneId)
                ->where('linkable_type', $linkableType)
                ->where('linkable_id', $linkableId)
                ->first();
            if ($existing) {
                return ToolResult::success([
                    'id'            => $existing->id,
                    'uuid'          => $existing->uuid,
                    'milestone_id'  => $existing->milestone_id,
                    'linkable_type' => $existing->linkable_type,
                    'linkable_id'   => $existing->linkable_id,
                    'weight'        => $existing->weight,
                    'message'       => 'Contribution existierte bereits (idempotent).',
                ]);
            }

            $teamId = (int) ($arguments['team_id'] ?? $milestone->team_id);

            $data = [
                'milestone_id'       => $milestoneId,
                'linkable_type'      => $linkableType,
                'linkable_id'        => $linkableId,
                'weight'             => isset($arguments['weight']) ? (int) $arguments['weight'] : null,
                'team_id'            => $teamId,
                'created_by_user_id' => isset($arguments['created_by_user_id'])
                    ? (int) $arguments['created_by_user_id']
                    : ($context->user?->id ?? null),
            ];
            foreach (['created_at', 'updated_at'] as $ts) {
                if (!empty($arguments[$ts])) {
                    $data[$ts] = $arguments[$ts];
                }
            }

            $contrib = OrganizationMilestoneContribution::create($data);

            return ToolResult::success([
                'id'            => $contrib->id,
                'uuid'          => $contrib->uuid,
                'milestone_id'  => $contrib->milestone_id,
                'linkable_type' => $contrib->linkable_type,
                'linkable_id'   => $contrib->linkable_id,
                'weight'        => $contrib->weight,
                'team_id'       => $contrib->team_id,
                'message'       => 'Contribution erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'strategy', 'milestones', 'contributions', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
