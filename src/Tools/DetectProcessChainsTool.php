<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Organization\Services\ProcessChainDetector;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DetectProcessChainsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.process_chains.detect';
    }

    public function getDescription(): string
    {
        return 'POST /organization/process-chains/detect - Erkennt Prozessketten automatisch via Graph-Analyse (Triggers/Outputs). Erzeugt/aktualisiert ad_hoc Chains. Mit dry_run=true nur Vorschau.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer'],
                'dry_run' => ['type' => 'boolean', 'description' => 'Optional: Nur Vorschau, keine Änderungen. Default false.'],
            ],
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
            $dryRun = (bool) ($arguments['dry_run'] ?? false);

            /** @var ProcessChainDetector $detector */
            $detector = app(ProcessChainDetector::class);
            $chains = $detector->detectChainsForTeam($rootTeamId, $dryRun);

            return ToolResult::success([
                'dry_run' => $dryRun,
                'count'   => $chains->count(),
                'chains'  => $chains->map(fn ($c) => [
                    'id'               => $c['id'] ?? null,
                    'uuid'             => $c['uuid'] ?? null,
                    'name'             => $c['name'] ?? null,
                    'signature'        => $c['signature'] ?? null,
                    'process_ids'      => $c['process_ids'] ?? [],
                    'has_cycle'        => $c['has_cycle'] ?? false,
                    'is_new'           => $c['is_new'] ?? false,
                    'chain_type'       => $c['chain_type'] ?? 'ad_hoc',
                ])->values()->toArray(),
                'team_id' => $rootTeamId,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Ketten-Erkennung: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['organization', 'process_chains', 'detect'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'write',
            'idempotent'    => true,
        ];
    }
}
