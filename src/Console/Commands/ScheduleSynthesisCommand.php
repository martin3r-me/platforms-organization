<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Models\OrganizationInferenceTrigger;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;

class ScheduleSynthesisCommand extends Command
{
    protected $signature = 'organization:schedule-synthesis {--team= : Optional Team-ID} {--type=weekly : Report-Typ (weekly/monthly)}';

    protected $description = 'Erzeugt Synthesis-Trigger für wöchentliche/monatliche Reports.';

    public function handle(): int
    {
        $teamIds = $this->resolveTeamIds();
        $reportType = $this->option('type');
        $created = 0;

        foreach ($teamIds as $teamId) {
            $trigger = OrganizationInferenceTrigger::createDebounced([
                'team_id' => $teamId,
                'trigger_type' => 'scheduled',
                'trigger_reference' => null,
                'prompt_filter' => ['synthesis' => true, 'report_type' => $reportType],
                'priority' => 30,
                'status' => 'pending',
                'debounce_key' => "synthesis:{$reportType}:team:{$teamId}",
            ]);

            if ($trigger) {
                $created++;
            }
        }

        $this->info("{$created} Synthesis-Trigger(s) erstellt.");

        return self::SUCCESS;
    }

    protected function resolveTeamIds(): array
    {
        if ($this->option('team')) {
            return [(int) $this->option('team')];
        }

        return OrganizationSignalInferencePrompt::active()
            ->distinct()
            ->pluck('team_id')
            ->all();
    }
}
