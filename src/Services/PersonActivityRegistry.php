<?php

namespace Platform\Organization\Services;

use Platform\Organization\Contracts\PersonActivityProvider;

class PersonActivityRegistry
{
    /** @var PersonActivityProvider[] */
    protected array $providers = [];

    public function register(PersonActivityProvider $provider): void
    {
        $this->providers[$provider->sectionKey()] = $provider;
    }

    /**
     * Alle Section-Configs von allen Providern.
     * @return array<string, array{label: string, icon: string, description: string|null}>
     */
    public function allSectionConfigs(): array
    {
        $configs = [];
        foreach ($this->providers as $key => $provider) {
            try {
                $configs[$key] = $provider->sectionConfig();
            } catch (\Throwable $e) {
                \Log::warning("PersonActivityRegistry: sectionConfig failed for '{$key}'", ['error' => $e->getMessage()]);
            }
        }
        return $configs;
    }

    /**
     * Vital Signs von allen Providern aggregiert.
     * @return array<string, array> [sectionKey => [metrics...]]
     */
    public function allVitalSigns(int $userId, int $teamId): array
    {
        $result = [];
        foreach ($this->providers as $key => $provider) {
            try {
                $signs = $provider->vitalSigns($userId, $teamId);
                if (!empty($signs)) {
                    $result[$key] = $signs;
                }
            } catch (\Throwable $e) {
                \Log::warning("PersonActivityRegistry: vitalSigns failed for '{$key}'", ['error' => $e->getMessage()]);
            }
        }
        return $result;
    }

    /**
     * Responsibilities von allen Providern aggregiert.
     * @return array<string, array> [sectionKey => [groups...]]
     */
    public function allResponsibilities(int $userId, int $teamId, int $limit = 5): array
    {
        $result = [];
        foreach ($this->providers as $key => $provider) {
            try {
                $groups = $provider->responsibilities($userId, $teamId, $limit);
                if (!empty($groups)) {
                    $result[$key] = $groups;
                }
            } catch (\Throwable $e) {
                \Log::warning("PersonActivityRegistry: responsibilities failed for '{$key}'", ['error' => $e->getMessage()]);
            }
        }
        return $result;
    }

    /**
     * Alle Metrik-Definitionen aller Provider, mit Snapshot-Key als Schluessel.
     * Returns: ['person_planner_open_tasks' => ['label' => ..., 'type' => ..., 'sort_weight' => ..., 'section' => ...], ...]
     */
    public function allMetricConfigs(): array
    {
        $result = [];
        foreach ($this->providers as $sectionKey => $provider) {
            try {
                $sectionConfig = $provider->sectionConfig();
                foreach ($provider->metricConfig() as $metricKey => $config) {
                    $snapshotKey = "person_{$sectionKey}_{$metricKey}";
                    $result[$snapshotKey] = array_merge($config, [
                        'section' => $sectionKey,
                        'section_label' => $sectionConfig['label'] ?? $sectionKey,
                    ]);
                }
            } catch (\Throwable $e) {
                \Log::warning("PersonActivityRegistry: metricConfig failed for '{$sectionKey}'", ['error' => $e->getMessage()]);
            }
        }
        return $result;
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }
}
