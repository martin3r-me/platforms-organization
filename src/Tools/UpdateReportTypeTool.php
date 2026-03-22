<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationReportType;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateReportTypeTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.report_types.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/report_types/{id} - Aktualisiert einen Berichtstyp. Nutze organization.report_types.GET um IDs zu ermitteln.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'report_type_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Berichtstyps.',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Name.',
                ],
                'key' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Key/Slug (unique pro Team).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Beschreibung ("" zum Leeren).',
                ],
                'hull' => [
                    'type' => 'object',
                    'description' => 'Optional: Neue Abschnittsstruktur.',
                ],
                'requirements' => [
                    'type' => 'object',
                    'description' => 'Optional: Neue Anforderungen (null zum Leeren).',
                ],
                'modules' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Optional: Neue Modul-Liste.',
                ],
                'include_time_entries' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Zeitbuchungen einbeziehen.',
                ],
                'frequency' => [
                    'type' => 'string',
                    'description' => 'Optional: Neue Frequenz (daily/weekly/monthly/manual).',
                    'enum' => ['daily', 'weekly', 'monthly', 'manual'],
                ],
                'output_channel' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Ausgabekanal (obsidian/html/audio/all).',
                    'enum' => ['obsidian', 'html', 'audio', 'all'],
                ],
                'obsidian_folder' => [
                    'type' => 'string',
                    'description' => 'Optional: Neuer Obsidian-Ordner ("" zum Leeren).',
                ],
                'template' => [
                    'type' => 'string',
                    'description' => 'Optional: Blade-Markdown-Template ("" zum Leeren → zurück zu AI-Loop).',
                ],
                'data_sources' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'tool' => ['type' => 'string'],
                            'params' => ['type' => 'object'],
                        ],
                        'required' => ['key', 'tool'],
                    ],
                    'description' => 'Optional: Tool-Call-Definitionen (null zum Leeren).',
                ],
                'ai_sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'instruction' => ['type' => 'string'],
                            'based_on' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'max_tokens' => ['type' => 'integer'],
                        ],
                        'required' => ['key', 'instruction'],
                    ],
                    'description' => 'Optional: AI-Abschnitt-Definitionen (null zum Leeren).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
            ],
            'required' => ['report_type_id'],
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
                'report_type_id',
                OrganizationReportType::class,
                'NOT_FOUND',
                'Berichtstyp nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            $reportType = $found['model'];
            if ((int) $reportType->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Berichtstyp gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];

            if (array_key_exists('name', $arguments)) {
                $name = trim((string) ($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }

            if (array_key_exists('key', $arguments)) {
                $key = trim((string) ($arguments['key'] ?? ''));
                if ($key === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'key darf nicht leer sein.');
                }
                $exists = OrganizationReportType::query()
                    ->where('team_id', $rootTeamId)
                    ->where('key', $key)
                    ->where('id', '!=', $reportType->id)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($exists) {
                    return ToolResult::error('VALIDATION_ERROR', "Berichtstyp mit key '{$key}' existiert bereits im Team.");
                }
                $update['key'] = $key;
            }

            if (array_key_exists('description', $arguments)) {
                $d = (string) ($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }

            if (array_key_exists('hull', $arguments)) {
                if (!is_array($arguments['hull']) || empty($arguments['hull'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'hull muss ein nicht-leeres JSON-Objekt sein.');
                }
                $update['hull'] = $arguments['hull'];
            }

            if (array_key_exists('requirements', $arguments)) {
                $update['requirements'] = (isset($arguments['requirements']) && is_array($arguments['requirements'])) ? $arguments['requirements'] : null;
            }

            if (array_key_exists('modules', $arguments)) {
                if (!is_array($arguments['modules']) || empty($arguments['modules'])) {
                    return ToolResult::error('VALIDATION_ERROR', 'modules muss ein nicht-leeres Array sein.');
                }
                $update['modules'] = $arguments['modules'];
            }

            if (array_key_exists('include_time_entries', $arguments)) {
                $update['include_time_entries'] = (bool) $arguments['include_time_entries'];
            }

            foreach (['frequency', 'output_channel'] as $field) {
                if (array_key_exists($field, $arguments) && $arguments[$field] !== null) {
                    $update[$field] = (string) $arguments[$field];
                }
            }

            if (array_key_exists('obsidian_folder', $arguments)) {
                $of = (string) ($arguments['obsidian_folder'] ?? '');
                $update['obsidian_folder'] = $of === '' ? null : $of;
            }

            if (array_key_exists('template', $arguments)) {
                $t = (string) ($arguments['template'] ?? '');
                $update['template'] = $t === '' ? null : $t;
            }

            if (array_key_exists('data_sources', $arguments)) {
                $update['data_sources'] = (isset($arguments['data_sources']) && is_array($arguments['data_sources'])) ? $arguments['data_sources'] : null;
            }

            if (array_key_exists('ai_sections', $arguments)) {
                $update['ai_sections'] = (isset($arguments['ai_sections']) && is_array($arguments['ai_sections'])) ? $arguments['ai_sections'] : null;
            }

            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool) $arguments['is_active'];
            }

            if (!empty($update)) {
                $reportType->update($update);
            }
            $reportType->refresh();

            return ToolResult::success([
                'id' => $reportType->id,
                'uuid' => $reportType->uuid,
                'name' => $reportType->name,
                'key' => $reportType->key,
                'frequency' => $reportType->frequency,
                'output_channel' => $reportType->output_channel,
                'engine' => $reportType->usesTemplateEngine() ? 'template' : 'ai_loop',
                'is_active' => (bool) $reportType->is_active,
                'message' => 'Berichtstyp erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Berichtstyps: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'report_types', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
