<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateEnvironmentSourceTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.environment_sources.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/environment_sources - Erstellt eine Umwelt-Datenquelle (RSS-Feed, Wetterdaten, Gesundheitsdaten) für VSM S4/S5 Diagnostik. Wird automatisch gepollt und analysiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Name der Datenquelle.',
                ],
                'source_type' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Typ der Quelle.',
                    'enum' => ['rss', 'weather', 'health_incidence'],
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'ERFORDERLICH: Thematische Kategorie.',
                    'enum' => ['industry', 'technology', 'regulation', 'market', 'competition', 'talent', 'sustainability', 'macro', 'geopolitics', 'gastronomy', 'health', 'weather', 'other'],
                ],
                'cluster' => [
                    'type' => 'string',
                    'description' => 'Optional: Geographisch/strategischer Cluster.',
                    'enum' => ['dach', 'europa', 'global', 'tech_ai', 'strategic', 'society', 'regional'],
                ],
                'config' => [
                    'type' => 'object',
                    'description' => 'ERFORDERLICH: Konfiguration. RSS: {url: "..."}. Weather: {latitude: 50.94, longitude: 6.96, location_name?: "Köln"}. Health: {datasets: ["grippeweb", "covid_are"], region?: "Bundesweit"}.',
                ],
                'pull_interval_hours' => [
                    'type' => 'integer',
                    'description' => 'Optional: Pull-Intervall in Stunden. Default: 24.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv. Default: true.',
                    'default' => true,
                ],
            ],
            'required' => ['name', 'source_type', 'category', 'config'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $name = trim((string) ($arguments['name'] ?? ''));
            if ($name === '') {
                return ToolResult::error('VALIDATION_ERROR', 'name ist erforderlich.');
            }

            $sourceType = $arguments['source_type'] ?? '';
            $validSourceTypes = ['rss', 'weather', 'health_incidence'];
            if (! in_array($sourceType, $validSourceTypes)) {
                return ToolResult::error('VALIDATION_ERROR', 'source_type muss einer der folgenden Werte sein: ' . implode(', ', $validSourceTypes));
            }

            $validCategories = ['industry', 'technology', 'regulation', 'market', 'competition', 'talent', 'sustainability', 'macro', 'geopolitics', 'gastronomy', 'health', 'weather', 'other'];
            $category = $arguments['category'] ?? '';
            if (! in_array($category, $validCategories)) {
                return ToolResult::error('VALIDATION_ERROR', 'category muss einer der folgenden Werte sein: ' . implode(', ', $validCategories));
            }

            $config = $arguments['config'] ?? [];
            if (! is_array($config)) {
                return ToolResult::error('VALIDATION_ERROR', 'config muss ein Objekt sein.');
            }

            if ($sourceType === 'rss' && empty($config['url'])) {
                return ToolResult::error('VALIDATION_ERROR', 'config.url ist bei source_type=rss erforderlich.');
            }

            if ($sourceType === 'weather') {
                if (empty($config['latitude']) || empty($config['longitude'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'config.latitude und config.longitude sind bei source_type=weather erforderlich.');
                }
            }

            if ($sourceType === 'health_incidence') {
                $datasets = $config['datasets'] ?? [];
                $validDatasets = ['grippeweb', 'covid_are'];
                if (empty($datasets) || ! is_array($datasets) || empty(array_intersect($datasets, $validDatasets))) {
                    return ToolResult::error('VALIDATION_ERROR', 'config.datasets muss ein Array mit mindestens einem Wert aus [grippeweb, covid_are] sein.');
                }
            }

            $validClusters = ['dach', 'europa', 'global', 'tech_ai', 'strategic', 'society', 'regional'];
            $cluster = $arguments['cluster'] ?? null;
            if ($cluster && ! in_array($cluster, $validClusters)) {
                return ToolResult::error('VALIDATION_ERROR', 'cluster muss einer der folgenden Werte sein: ' . implode(', ', $validClusters));
            }

            $source = OrganizationEnvironmentSource::create([
                'name' => $name,
                'source_type' => $sourceType,
                'category' => $category,
                'cluster' => $cluster,
                'config' => $config,
                'pull_interval_hours' => (int) ($arguments['pull_interval_hours'] ?? match ($sourceType) {
                    'weather' => 12,
                    'health_incidence' => 24,
                    default => 24,
                }),
                'is_active' => (bool) ($arguments['is_active'] ?? true),
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $source->id,
                'uuid' => $source->uuid,
                'name' => $source->name,
                'source_type' => $source->source_type,
                'category' => $source->category,
                'cluster' => $source->cluster,
                'config' => $source->config,
                'pull_interval_hours' => $source->pull_interval_hours,
                'is_active' => (bool) $source->is_active,
                'message' => 'Environment-Source erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen der Environment-Source: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'environment', 'vsm', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
