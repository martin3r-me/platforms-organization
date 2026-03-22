<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationReportType;
use Platform\Organization\Services\ReportGenerator;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class GenerateReportTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.reports.POST';
    }

    public function getDescription(): string
    {
        return 'POST /organization/reports - Generiert einen Bericht. Zwei Modi: (1) Template-Engine (wenn template gesetzt) — deterministische Tool-Calls, AI nur für markierte Abschnitte, Blade-Rendering. (2) AI-Loop (Fallback) — LLM holt Daten selbst via Tools. Kann einige Minuten dauern.';
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
                'report_type_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID des Berichtstyps.',
                ],
                'entity_id' => [
                    'type' => 'integer',
                    'description' => 'ERFORDERLICH: ID der Entity, für die der Bericht generiert werden soll.',
                ],
                'output_channel' => [
                    'type' => 'string',
                    'description' => 'Optional: Überschreibt den Ausgabekanal des Berichtstyps (obsidian/html/audio/all).',
                    'enum' => ['obsidian', 'html', 'audio', 'all'],
                ],
            ],
            'required' => ['report_type_id', 'entity_id'],
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

            $userId = $context->user?->id;
            if (!$userId) {
                return ToolResult::error('AUTH_ERROR', 'Kein authentifizierter User.');
            }

            // Load ReportType
            $reportTypeId = $arguments['report_type_id'] ?? null;
            if (!$reportTypeId) {
                return ToolResult::error('VALIDATION_ERROR', 'report_type_id ist erforderlich.');
            }

            $reportType = OrganizationReportType::where('id', (int) $reportTypeId)
                ->where('team_id', $rootTeamId)
                ->whereNull('deleted_at')
                ->first();

            if (!$reportType) {
                return ToolResult::error('NOT_FOUND', 'Berichtstyp nicht gefunden.');
            }

            if (!$reportType->is_active) {
                return ToolResult::error('VALIDATION_ERROR', 'Berichtstyp ist deaktiviert.');
            }

            // Load Entity
            $entityId = $arguments['entity_id'] ?? null;
            if (!$entityId) {
                return ToolResult::error('VALIDATION_ERROR', 'entity_id ist erforderlich.');
            }

            $entity = OrganizationEntity::where('id', (int) $entityId)
                ->where('team_id', $rootTeamId)
                ->whereNull('deleted_at')
                ->first();

            if (!$entity) {
                return ToolResult::error('NOT_FOUND', 'Entity nicht gefunden.');
            }

            // Output channel override
            $outputChannel = $arguments['output_channel'] ?? $reportType->output_channel;

            $generator = new ReportGenerator();
            $report = $generator->generate($reportType, $entity, $context->user, $outputChannel);

            return ToolResult::success([
                'id' => $report->id,
                'uuid' => $report->uuid,
                'status' => $report->status,
                'report_type_id' => $report->report_type_id,
                'entity_id' => $report->entity_id,
                'output_channel' => $report->output_channel,
                'obsidian_path' => $report->obsidian_path,
                'snapshot_at' => $report->snapshot_at?->toIso8601String(),
                'generated_content' => $report->generated_content,
                'error_message' => $report->error_message,
                'message' => $report->status === 'final'
                    ? 'Bericht erfolgreich generiert.'
                    : 'Bericht-Generierung fehlgeschlagen: ' . ($report->error_message ?? 'Unbekannter Fehler'),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Generieren des Berichts: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'reports', 'generate', 'ai'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
