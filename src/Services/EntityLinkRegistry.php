<?php

namespace Platform\Organization\Services;

use Platform\Organization\Contracts\EntityLinkProvider;

class EntityLinkRegistry
{
    /** @var EntityLinkProvider[] */
    protected array $providers = [];

    /** @var array<string, EntityLinkProvider> morph alias => provider */
    protected array $aliasMap = [];

    protected ?array $cachedLinkTypeConfig = null;
    protected ?array $cachedDisplayRules = null;
    protected ?array $cachedTimeTrackableCascades = null;

    public function register(EntityLinkProvider $provider): void
    {
        $this->providers[] = $provider;

        foreach ($provider->morphAliases() as $alias) {
            $this->aliasMap[$alias] = $provider;
        }

        // Invalidate caches
        $this->cachedLinkTypeConfig = null;
        $this->cachedDisplayRules = null;
        $this->cachedTimeTrackableCascades = null;
    }

    public function getProvider(string $morphAlias): ?EntityLinkProvider
    {
        return $this->aliasMap[$morphAlias] ?? null;
    }

    /**
     * Merged linkTypeConfig from all providers.
     * @return array<string, array{label: string, icon: string, route: string|null}>
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
     * Merged metadataDisplayRules from all providers.
     * @return array<string, array>
     */
    public function allMetadataDisplayRules(): array
    {
        if ($this->cachedDisplayRules === null) {
            $this->cachedDisplayRules = [];
            foreach ($this->providers as $provider) {
                foreach ($provider->metadataDisplayRules() as $alias => $rules) {
                    $this->cachedDisplayRules[$alias] = $rules;
                }
            }
        }

        return $this->cachedDisplayRules;
    }

    /**
     * Merged timeTrackableCascades from all providers.
     * @return array<string, array{0: class-string, 1: string[]}>
     */
    public function allTimeTrackableCascades(): array
    {
        if ($this->cachedTimeTrackableCascades === null) {
            $this->cachedTimeTrackableCascades = [];
            foreach ($this->providers as $provider) {
                foreach ($provider->timeTrackableCascades() as $alias => $config) {
                    $this->cachedTimeTrackableCascades[$alias] = $config;
                }
            }
        }

        return $this->cachedTimeTrackableCascades;
    }

    /**
     * Ruft metrics() aller Provider auf und merged Ergebnisse per Entity.
     *
     * @param array<int, array<string, int[]>> $linksByEntityAndType [entityId => [morphAlias => [linkable_ids]]]
     * @return array<int, array> [entityId => merged metrics]
     */
    public function computeMetricsBatch(array $linksByEntityAndType): array
    {
        // Regroup: [morphAlias => [entityId => [linkable_ids]]]
        $byAlias = [];
        foreach ($linksByEntityAndType as $entityId => $typeMap) {
            foreach ($typeMap as $morphAlias => $ids) {
                $byAlias[$morphAlias][$entityId] = $ids;
            }
        }

        $result = [];
        foreach ($byAlias as $morphAlias => $linksByEntity) {
            $provider = $this->getProvider($morphAlias);
            if (!$provider) {
                continue;
            }

            $metrics = $provider->metrics($morphAlias, $linksByEntity);

            foreach ($metrics as $entityId => $entityMetrics) {
                foreach ($entityMetrics as $key => $value) {
                    $result[$entityId][$key] = ($result[$entityId][$key] ?? 0) + $value;
                }
            }
        }

        return $result;
    }
}
