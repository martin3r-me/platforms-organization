<?php

namespace Platform\Organization\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationReportType;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class CreateReportTypeTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.report_types.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/report_types - Erstellt einen Berichtstyp. Zwei Modi: (1) AI-Loop (hull+modules) — LLM holt Daten selbst. (2) Template-Engine (template+data_sources+ai_sections) — deterministische Datenabfrage, AI nur für markierte Abschnitte.';
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
                    'description' => 'ERFORDERLICH: Name des Berichtstyps (z.B. "Wöchentlicher Venture-Status").',
                ],
                'hull' => [
                    'type' => 'object',
                    'description' => 'ERFORDERLICH: Fixe Abschnittsstruktur als JSON. Definiert die Gliederung des Berichts.',
                ],
                'modules' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'ERFORDERLICH: Array von Modul-Keys die abgefragt werden (z.B. ["planner", "helpdesk"]).',
                ],
                'key' => [
                    'type' => 'string',
                    'description' => 'Optional: Eindeutiger Slug (auto-generiert aus Name wenn leer). Unique pro Team.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung des Berichtstyps.',
                ],
                'requirements' => [
                    'type' => 'object',
                    'description' => 'Optional: Regeln/Anforderungen für die LLM-Generierung als JSON.',
                ],
                'include_time_entries' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Zeitbuchungen einbeziehen. Default: false.',
                ],
                'frequency' => [
                    'type' => 'string',
                    'description' => 'Optional: Frequenz (daily/weekly/monthly/manual). Default: manual.',
                    'enum' => ['daily', 'weekly', 'monthly', 'manual'],
                ],
                'output_channel' => [
                    'type' => 'string',
                    'description' => 'Optional: Ausgabekanal (obsidian/html/audio/all). Default: obsidian.',
                    'enum' => ['obsidian', 'html', 'audio', 'all'],
                ],
                'obsidian_folder' => [
                    'type' => 'string',
                    'description' => 'Optional: Obsidian-Ordner für die Ausgabe.',
                ],
                'template' => [
                    'type' => 'string',
                    'description' => 'Optional: Blade-Markdown-Template für Template-Engine-Modus. Wenn gesetzt, werden hull/modules ignoriert und stattdessen data_sources/ai_sections verwendet.',
                ],
                'data_sources' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string', 'description' => 'Variablenname im Template'],
                            'tool' => ['type' => 'string', 'description' => 'Tool-Name (z.B. planner.projects.GET)'],
                            'params' => ['type' => 'object', 'description' => 'Tool-Parameter. Platzhalter: {{entity.id}}, {{period.from}}, {{period.to}}'],
                        ],
                        'required' => ['key', 'tool'],
                    ],
                    'description' => 'Optional: Tool-Call-Definitionen für deterministische Datenabfrage (nur Template-Engine-Modus).',
                ],
                'ai_sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string', 'description' => 'Variablenname im Template'],
                            'instruction' => ['type' => 'string', 'description' => 'Anweisung für das LLM'],
                            'based_on' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Keys aus data_sources als Kontext'],
                            'max_tokens' => ['type' => 'integer', 'description' => 'Max Tokens für AI-Antwort (Default: 500)'],
                        ],
                        'required' => ['key', 'instruction'],
                    ],
                    'description' => 'Optional: AI-Abschnitt-Definitionen (nur Template-Engine-Modus).',
                ],
            ],
            'required' => ['name'],
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

            $template = ($arguments['template'] ?? null) ?: null;
            $hasTemplate = !empty($template);

            $hull = $arguments['hull'] ?? null;
            $modules = $arguments['modules'] ?? null;

            // Wenn kein Template: hull + modules sind required (AI-Loop-Modus)
            if (!$hasTemplate) {
                if (!is_array($hull) || empty($hull)) {
                    return ToolResult::error('VALIDATION_ERROR', 'hull ist erforderlich (wenn kein template gesetzt) und muss ein JSON-Objekt sein.');
                }
                if (!is_array($modules) || empty($modules)) {
                    return ToolResult::error('VALIDATION_ERROR', 'modules ist erforderlich (wenn kein template gesetzt) und muss ein nicht-leeres Array sein.');
                }
            }

            // Key: auto-generate from name if not provided
            $key = trim((string) ($arguments['key'] ?? ''));
            if ($key === '') {
                $key = Str::slug($name);
            }

            // Uniqueness check: key per team
            $exists = OrganizationReportType::query()
                ->where('team_id', $rootTeamId)
                ->where('key', $key)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                return ToolResult::error('VALIDATION_ERROR', "Berichtstyp mit key '{$key}' existiert bereits im Team.");
            }

            $dataSources = (isset($arguments['data_sources']) && is_array($arguments['data_sources'])) ? $arguments['data_sources'] : null;
            $aiSections = (isset($arguments['ai_sections']) && is_array($arguments['ai_sections'])) ? $arguments['ai_sections'] : null;

            $reportType = OrganizationReportType::create([
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
                'name' => $name,
                'key' => $key,
                'description' => ($arguments['description'] ?? null) ?: null,
                'hull' => is_array($hull) ? $hull : null,
                'requirements' => (isset($arguments['requirements']) && is_array($arguments['requirements'])) ? $arguments['requirements'] : null,
                'modules' => is_array($modules) ? $modules : null,
                'include_time_entries' => (bool) ($arguments['include_time_entries'] ?? false),
                'frequency' => $arguments['frequency'] ?? 'manual',
                'output_channel' => $arguments['output_channel'] ?? 'obsidian',
                'obsidian_folder' => ($arguments['obsidian_folder'] ?? null) ?: null,
                'template' => $template,
                'data_sources' => $dataSources,
                'ai_sections' => $aiSections,
                'is_active' => true,
                'created_by' => $context->user?->id,
            ]);

            return ToolResult::success([
                'id' => $reportType->id,
                'uuid' => $reportType->uuid,
                'name' => $reportType->name,
                'key' => $reportType->key,
                'team_id' => $reportType->team_id,
                'frequency' => $reportType->frequency,
                'output_channel' => $reportType->output_channel,
                'engine' => $reportType->usesTemplateEngine() ? 'template' : 'ai_loop',
                'modules' => $reportType->modules,
                'message' => 'Berichtstyp erfolgreich erstellt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Erstellen des Berichtstyps: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'report_types', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
