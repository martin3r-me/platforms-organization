<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Services\DimensionLinkAuditService;

/**
 * Read-only Integritäts-Audit der DimensionLinks.
 *
 * Prüft die plattformkritischen Invarianten, die heute nur in der App-Logik
 * (nicht in der DB) erzwungen werden — Phase-0-Bestandsaufnahme der Härtung und
 * Verifikations-Instrument vor/nach riskanten Migrationen. Verändert nichts.
 *
 * Dünner Wrapper um {@see DimensionLinkAuditService}; dieselbe Logik steht als
 * `organization:dimension-link-audit` auch auf der CLI/CI zur Verfügung.
 */
class AuditDimensionLinksTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'organization.dimension_links.audit.GET';
    }

    public function getDescription(): string
    {
        return 'GET /organization/dimension-links/audit - Read-only Integritaets-Audit der DimensionLinks. Prueft Invarianten: entity-Values ohne/mit verwaister source_entity_id, Prozent-Summe > 100 (multi_percent), mehrfaches is_primary pro Gruppe, Links auf soft-geloeschte Values, unbekannte linkable_type. Liefert pro Check {label, status(ok|warn), count, samples}. Veraendert nichts.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_samples' => [
                    'type' => 'boolean',
                    'description' => 'Beispielzeilen je Check mitliefern (max. 15 pro Check). Default true.',
                ],
            ],
            'required' => [],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'read',
            'tags'          => ['organization', 'dimensions', 'links', 'audit', 'integrity', 'diagnostics'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
            // Diagnose-/Dev-Werkzeug: nicht im Always-On-Surface, aber über die
            // Modul-Discovery (tools__GET module=organization) erreichbar.
            'tier'          => 'specialized',
            'intent'        => 'Read-only Integritäts-Audit der DimensionLinks (Invarianten-Check).',
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $includeSamples = (bool) ($arguments['include_samples'] ?? true);

            $checks = (new DimensionLinkAuditService())->run();

            $findings = 0;
            $data = [];
            foreach ($checks as $code => $check) {
                $isWarn = $check['status'] === 'warn';
                $findings += $isWarn ? 1 : 0;

                $entry = [
                    'label'  => $check['label'],
                    'status' => $check['status'],
                    'count'  => $check['count'],
                ];
                if (isset($check['note'])) {
                    $entry['note'] = $check['note'];
                }
                if ($includeSamples) {
                    $entry['samples'] = $check['samples'];
                }
                $data[$code] = $entry;
            }

            return ToolResult::success([
                'has_findings'   => $findings > 0,
                'findings_count' => $findings,
                'checks'         => $data,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Audit fehlgeschlagen: ' . $e->getMessage());
        }
    }
}
