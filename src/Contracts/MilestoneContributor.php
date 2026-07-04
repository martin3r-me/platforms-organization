<?php

namespace Platform\Organization\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * MilestoneContributor
 *
 * Module implement this to signal: "I have entities that can *contribute*
 * to a Milestone on the Transformations Map." Concrete example: the OKR
 * module registers Objectives and KeyResults as contributors.
 *
 * Semantically distinct from EntityLinkProvider — the anchor here is a
 * Milestone (a waypoint on a Carrier's Transformations Map), not an
 * OrganizationEntity.
 */
interface MilestoneContributor
{
    /**
     * Morph aliases this contributor handles (as stored in
     * organization_milestone_contributions.linkable_type).
     *
     * @return string[]  z.B. ['okr_objective', 'okr_key_result']
     */
    public function morphAliases(): array;

    /**
     * Display config per alias.
     *
     * @return array<string, array{label: string, singular: string, icon: string, route: string|null}>
     */
    public function linkTypeConfig(): array;

    /**
     * Optional eager loading hook — apply withCount / eager relations
     * to the query loading contributions of this alias.
     */
    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void;

    /**
     * Serialize a single contributor for map rendering.
     * Return whatever the map needs (status, performance_score, title override, ...).
     *
     * @return array<string, mixed>
     */
    public function extractMetadata(string $morphAlias, mixed $model): array;

    /**
     * Batch-compute per-milestone aggregate metrics (used by the map summary).
     *
     * @param string $morphAlias
     * @param array<int, int[]> $linksByMilestone [milestoneId => [linkable_id, ...]]
     * @return array<int, array<string, int|float>> [milestoneId => ['contributors_total' => X, 'contributors_done' => Y, ...]]
     */
    public function metrics(string $morphAlias, array $linksByMilestone): array;
}
