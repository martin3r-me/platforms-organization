<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateEnvironmentSourceTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.environment_sources.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/environment_sources/{id} - Aktualisiert eine Environment-Source. Nutze organization.environment_sources.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'source_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Environment-Source.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name.',
                ],
                'source_type' => [
                    'type' => 'string',
                    'description' => 'Optional: Typ (rss).',
                    'enum' => ['rss'],
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Optional: Thematische Kategorie.',
                    'enum' => ['industry', 'technology', 'regulation', 'market', 'competition', 'talent', 'sustainability', 'macro', 'geopolitics', 'gastronomy', 'other'],
                ],
                'cluster' => [
                    'type' => 'string',
                    'description' => 'Optional: Geographisch/strategischer Cluster.',
                    'enum' => ['dach', 'europa', 'global', 'tech_ai', 'strategic', 'society'],
                ],
                'config' => [
                    'type' => 'object',
                    'description' => 'Optional: Neue Konfiguration.',
                ],
                'pull_interval_hours' => [
                    'type' => 'integer',
                    'description' => 'Optional: Pull-Intervall in Stunden.',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
            ],
            'required' => ['source_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int) $resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'source_id',
                OrganizationEnvironmentSource::class,
                'NOT_FOUND',
                'Environment-Source nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationEnvironmentSource $source */
            $source = $found['model'];

            if ((int) $source->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Environment-Source gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];

            if (array_key_exists('name', $arguments)) {
                $name = trim((string) ($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }

            if (array_key_exists('source_type', $arguments)) {
                $st = $arguments['source_type'];
                if (! in_array($st, ['rss'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'source_type muss rss sein.');
                }
                $update['source_type'] = $st;
            }

            if (array_key_exists('category', $arguments)) {
                $validCategories = ['industry', 'technology', 'regulation', 'market', 'competition', 'talent', 'sustainability', 'macro', 'geopolitics', 'gastronomy', 'other'];
                $cat = $arguments['category'];
                if (! in_array($cat, $validCategories)) {
                    return ToolResult::error('VALIDATION_ERROR', 'category muss einer der folgenden Werte sein: ' . implode(', ', $validCategories));
                }
                $update['category'] = $cat;
            }

            if (array_key_exists('cluster', $arguments)) {
                $validClusters = ['dach', 'europa', 'global', 'tech_ai', 'strategic', 'society'];
                $cl = $arguments['cluster'];
                if ($cl !== null && ! in_array($cl, $validClusters)) {
                    return ToolResult::error('VALIDATION_ERROR', 'cluster muss einer der folgenden Werte sein: ' . implode(', ', $validClusters));
                }
                $update['cluster'] = $cl;
            }

            if (array_key_exists('config', $arguments)) {
                $config = $arguments['config'];
                if (! is_array($config)) {
                    return ToolResult::error('VALIDATION_ERROR', 'config muss ein Objekt sein.');
                }
                $update['config'] = $config;
            }

            if (array_key_exists('pull_interval_hours', $arguments)) {
                $update['pull_interval_hours'] = (int) $arguments['pull_interval_hours'];
            }

            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (! empty($update)) {
                $source->update($update);
            }
            $source->refresh();

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
                'message' => 'Environment-Source erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Environment-Source: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'environment', 'vsm', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
