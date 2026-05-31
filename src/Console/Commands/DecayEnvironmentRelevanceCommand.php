<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Models\OrganizationMemoryEntry;

class DecayEnvironmentRelevanceCommand extends Command
{
    protected $signature = 'organization:decay-environment-relevance';

    protected $description = 'Decay confidence of stale source_relevance memories that have not received feedback';

    public function handle(): int
    {
        $memories = OrganizationMemoryEntry::ofType('source_relevance')
            ->active()
            ->get();

        if ($memories->isEmpty()) {
            $this->info('No active source_relevance memories found.');

            return self::SUCCESS;
        }

        $decayed = 0;
        $deactivated = 0;

        foreach ($memories as $memory) {
            $lastFeedback = $memory->structured_data['last_feedback_at'] ?? null;
            $lastFeedbackAt = $lastFeedback ? \Carbon\Carbon::parse($lastFeedback) : $memory->created_at;
            $daysSinceFeedback = $lastFeedbackAt->diffInDays(now());

            if ($daysSinceFeedback < 30) {
                continue;
            }

            if ($daysSinceFeedback >= 60) {
                $memory->confidence = max(0.0, $memory->confidence - 0.15);
            } else {
                // 30-60 days
                $memory->confidence = max(0.0, $memory->confidence - 0.05);
            }

            if ($memory->confidence < 0.15) {
                $memory->is_active = false;
                $deactivated++;
            }

            $memory->save();
            $decayed++;
        }

        $this->info("Decayed {$decayed} source_relevance memories, deactivated {$deactivated}.");

        return self::SUCCESS;
    }
}
