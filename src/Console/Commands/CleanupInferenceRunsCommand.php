<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Models\OrganizationInferenceRun;
use Platform\Organization\Models\OrganizationInferenceTrigger;

class CleanupInferenceRunsCommand extends Command
{
    protected $signature = 'organization:cleanup-inference-runs';

    protected $description = 'Mark stale "running" inference runs as failed after 10 minutes';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(10);

        $staleRuns = OrganizationInferenceRun::where('status', 'running')
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($staleRuns->isEmpty()) {
            $this->info('No stale inference runs found.');
            return self::SUCCESS;
        }

        $triggerIds = [];

        foreach ($staleRuns as $run) {
            $run->markFailed('Timeout: Run exceeded maximum duration');

            if ($run->trigger_id) {
                $triggerIds[] = $run->trigger_id;
            }
        }

        // Mark associated processing triggers as failed
        if (!empty($triggerIds)) {
            OrganizationInferenceTrigger::whereIn('id', $triggerIds)
                ->where('status', 'processing')
                ->update(['status' => 'failed']);
        }

        $this->info("Cleaned up {$staleRuns->count()} stale inference run(s).");

        return self::SUCCESS;
    }
}
