<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Models\OrganizationProcess;
use Platform\Organization\Services\ProcessChainDetector;

class DetectProcessChainsCommand extends Command
{
    protected $signature = 'organization:detect-process-chains
        {--dry-run : Vorschau ohne Persistenz}
        {--team-id= : Nur ein bestimmtes Team prüfen}';

    protected $description = 'Erkennt Prozessketten automatisch via Graph-Analyse (Trigger/Outputs) und persistiert ad_hoc-Chains.';

    public function handle(ProcessChainDetector $detector): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $teamIdOption = $this->option('team-id');

        if ($teamIdOption !== null && $teamIdOption !== '') {
            $teamIds = [(int) $teamIdOption];
        } else {
            $teamIds = OrganizationProcess::query()
                ->select('team_id')
                ->distinct()
                ->pluck('team_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        if (empty($teamIds)) {
            $this->info('Keine Teams mit Prozessen gefunden.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s Erkennung für %d Team(s)%s',
            $dryRun ? '[DRY-RUN]' : '[LIVE]',
            count($teamIds),
            $dryRun ? ' (keine Persistenz)' : ''
        ));

        $totalChains = 0;
        $totalNew = 0;

        foreach ($teamIds as $teamId) {
            try {
                $chains = $detector->detectChainsForTeam($teamId, $dryRun);
            } catch (\Throwable $e) {
                $this->error(sprintf('Team %d: %s', $teamId, $e->getMessage()));
                continue;
            }

            if ($chains->isEmpty()) {
                $this->line(sprintf('Team %d: keine Ketten erkannt.', $teamId));
                continue;
            }

            $newCount = $chains->where('is_new', true)->count();
            $totalChains += $chains->count();
            $totalNew += $newCount;

            $this->info(sprintf(
                'Team %d: %d Kette(n) erkannt (%d neu)',
                $teamId,
                $chains->count(),
                $newCount
            ));

            foreach ($chains as $chain) {
                $this->line(sprintf(
                    '  - [%s] %s (%d Prozesse%s)%s',
                    $chain['chain_type'] ?? 'ad_hoc',
                    $chain['name'] ?? 'unbenannt',
                    count($chain['process_ids'] ?? []),
                    ! empty($chain['has_cycle']) ? ', Zyklus' : '',
                    ! empty($chain['is_new']) ? ' [NEU]' : ''
                ));
            }
        }

        $this->info(sprintf(
            'Fertig: %d Ketten insgesamt, %d neu.%s',
            $totalChains,
            $totalNew,
            $dryRun ? ' (Dry-Run: nichts gespeichert)' : ''
        ));

        return self::SUCCESS;
    }
}
