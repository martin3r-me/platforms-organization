<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Models\OrganizationSignalDefinition;
use Platform\Organization\Services\SignalEvaluationService;

class EvaluateSignalsCommand extends Command
{
    protected $signature = 'organization:evaluate-signals {--team= : Nur für ein bestimmtes Team}';

    protected $description = 'Evaluate active signal definitions against current snapshots and create/resolve signals';

    public function handle(SignalEvaluationService $service): int
    {
        $teamOption = $this->option('team');

        // Determine which teams to evaluate
        if ($teamOption) {
            $teamIds = [(int) $teamOption];
        } else {
            $teamIds = OrganizationSignalDefinition::active()
                ->distinct()
                ->pluck('team_id')
                ->all();
        }

        if (empty($teamIds)) {
            $this->info('Keine Teams mit aktiven Signal-Definitionen gefunden.');
            return self::SUCCESS;
        }

        $this->info('Evaluiere Signale für ' . count($teamIds) . ' Team(s)...');

        $totalCreated = 0;

        foreach ($teamIds as $teamId) {
            $created = $service->evaluateForTeam($teamId);
            $count = $created->count();
            $totalCreated += $count;

            if ($count > 0) {
                $this->line("  Team {$teamId}: {$count} neue Signal(e) erstellt");
            } else {
                $this->line("  Team {$teamId}: keine neuen Signale");
            }
        }

        $this->info("Fertig. {$totalCreated} Signal(e) insgesamt erstellt.");

        return self::SUCCESS;
    }
}
