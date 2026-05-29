<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Jobs\InferenceWorkerJob;
use Platform\Organization\Models\OrganizationInferenceTrigger;

class ProcessInferenceTriggersCommand extends Command
{
    protected $signature = 'organization:process-inference-triggers {--batch=10 : Max triggers per tick}';

    protected $description = 'Verarbeitet offene Inference-Triggers aus der Outbox und dispatched Queue-Jobs.';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');

        $triggers = OrganizationInferenceTrigger::where('status', 'pending')
            ->orderByDesc('priority')
            ->orderBy('created_at')
            ->limit($batchSize)
            ->get();

        if ($triggers->isEmpty()) {
            $this->info('Keine offenen Inference-Triggers.');
            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($triggers as $trigger) {
            // Mark as processing
            $trigger->update(['status' => 'processing']);

            // Determine queue based on priority
            $queue = match (true) {
                $trigger->priority >= 80 => 'inference-urgent',
                $trigger->trigger_type === 'synthesis' => 'synthesis',
                default => 'inference',
            };

            InferenceWorkerJob::dispatch($trigger)->onQueue($queue);
            $dispatched++;
        }

        $this->info("{$dispatched} Inference-Job(s) dispatched.");

        return self::SUCCESS;
    }
}
