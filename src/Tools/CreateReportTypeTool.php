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
        return 'POST /organization/report_types - Erstellt einen Berichtstyp. hull definiert die Abschnittsstruktur, modules die abzufragenden Module, requirements die Regeln für die LLM-Generierung.';
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
            ],
            'required' => ['name', 'hull', 'modules'],
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

            $hull = $arguments['hull'] ?? null;
            if (!is_array($hull) || empty($hull)) {
                return ToolResult::error('VALIDATION_ERROR', 'hull ist erforderlich und muss ein JSON-Objekt sein.');
            }

            $modules = $arguments['modules'] ?? null;
            if (!is_array($modules) || empty($modules)) {
                return ToolResult::error('VALIDATION_ERROR', 'modules ist erforderlich und muss ein nicht-leeres Array sein.');
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

            $reportType = OrganizationReportType::create([
                'team_id' => $rootTeamId,
                'user_id' => $context->user?->id,
                'name' => $name,
                'key' => $key,
                'description' => ($arguments['description'] ?? null) ?: null,
                'hull' => $hull,
                'requirements' => (isset($arguments['requirements']) && is_array($arguments['requirements'])) ? $arguments['requirements'] : null,
                'modules' => $modules,
                'include_time_entries' => (bool) ($arguments['include_time_entries'] ?? false),
                'frequency' => $arguments['frequency'] ?? 'manual',
                'output_channel' => $arguments['output_channel'] ?? 'obsidian',
                'obsidian_folder' => ($arguments['obsidian_folder'] ?? null) ?: null,
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
