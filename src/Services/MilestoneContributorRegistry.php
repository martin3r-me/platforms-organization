<?php

namespace Platform\Organization\Services;

use Platform\Organization\Contracts\MilestoneContributor;

/**
 * Registry for MilestoneContributor providers.
 *
 * Modules register their MilestoneContributor implementation once (in a
 * ServiceProvider::boot). The Transformations Map uses this registry to
 * resolve contributions of any registered morph alias without needing
 * a hard dependency on any specific consumer module.
 */
class MilestoneContributorRegistry
{
    /** @var MilestoneContributor[] */
    protected array $providers = [];

    /** @var array<string, MilestoneContributor> morph alias => provider */
    protected array $aliasMap = [];

    protected ?array $cachedLinkTypeConfig = null;

    public function register(MilestoneContributor $provider): void
    {
        $this->providers[] = $provider;

        foreach ($provider->morphAliases() as $alias) {
            $this->aliasMap[$alias] = $provider;
        }

        $this->cachedLinkTypeConfig = null;
    }

    public function getProvider(string $morphAlias): ?MilestoneContributor
    {
        return $this->aliasMap[$morphAlias] ?? null;
    }

    /** @return string[] */
    public function knownAliases(): array
    {
        return array_keys($this->aliasMap);
    }

    /**
     * Merged linkTypeConfig from all providers.
     * @return array<string, array{label: string, singular: string, icon: string, route: string|null}>
     */
    public function allLinkTypeConfig(): array
    {
        if ($this->cachedLinkTypeConfig === null) {
            $this->cachedLinkTypeConfig = [];
            foreach ($this->providers as $provider) {
                foreach ($provider->linkTypeConfig() as $alias => $config) {
                    $this->cachedLinkTypeConfig[$alias] = $config;
                }
            }
        }

        return $this->cachedLinkTypeConfig;
    }

    /**
     * Batch-compute metrics across all providers and merge per milestone.
     *
     * @param array<int, array<string, int[]>> $linksByMilestoneAndType [milestoneId => [morphAlias => [linkable_ids]]]
     * @return array<int, array<string, int|float>> [milestoneId => merged metrics]
     */
    public function computeMetricsBatch(array $linksByMilestoneAndType): array
    {
        // Regroup: [morphAlias => [milestoneId => [linkable_ids]]]
        $byAlias = [];
        foreach ($linksByMilestoneAndType as $milestoneId => $typeMap) {
            foreach ($typeMap as $morphAlias => $ids) {
                $byAlias[$morphAlias][$milestoneId] = $ids;
            }
        }

        $result = [];
        foreach ($byAlias as $morphAlias => $linksByMilestone) {
            $provider = $this->getProvider($morphAlias);
            if (!$provider) {
                continue;
            }

            $metrics = $provider->metrics($morphAlias, $linksByMilestone);

            foreach ($metrics as $milestoneId => $milestoneMetrics) {
                foreach ($milestoneMetrics as $key => $value) {
                    $result[$milestoneId][$key] = ($result[$milestoneId][$key] ?? 0) + $value;
                }
            }
        }

        return $result;
    }
}
