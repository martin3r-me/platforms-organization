<?php

namespace Platform\Organization\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Core\Contracts\ToolContext;
use Platform\Organization\Models\OrganizationInferenceRun;
use Platform\Organization\Models\OrganizationInferenceTrigger;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;
use Platform\Organization\Services\InferencePromptService;

class InferenceWorkerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900; // 15 minutes max per job
    public int $tries = 1;    // No retry for LLM calls

    public function __construct(
        public OrganizationInferenceTrigger $trigger
    ) {}

    public function handle(): void
    {
        $startTime = hrtime(true);
        $trigger = $this->trigger;

        // Create inference run record
        $run = OrganizationInferenceRun::create([
            'team_id' => $trigger->team_id,
            'trigger_id' => $trigger->id,
            'trigger_type' => $trigger->trigger_type,
            'status' => 'running',
        ]);

        try {
            // Check if this is a synthesis trigger
            $isSynthesis = ($trigger->prompt_filter['synthesis'] ?? false) === true;

            if ($isSynthesis) {
                $this->runSynthesis($trigger, $run, $startTime);
            } else {
                $this->runInference($trigger, $run, $startTime);
            }

            // Mark trigger completed
            $trigger->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            $run->markFailed($e->getMessage());
            $run->update(['duration_ms' => $durationMs]);

            $trigger->update([
                'status' => 'failed',
                'processed_at' => now(),
            ]);

            Log::error('[InferenceWorkerJob] Failed', [
                'trigger_id' => $trigger->id,
                'team_id' => $trigger->team_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function runInference(OrganizationInferenceTrigger $trigger, OrganizationInferenceRun $run, int $startTime): void
    {
        $service = resolve(InferencePromptService::class);

        // Resolve which prompts to run
        $prompts = $this->resolvePrompts($trigger);

        if ($prompts->isEmpty()) {
            $run->markCompleted(['duration_ms' => $this->elapsedMs($startTime)]);
            return;
        }

        $totalSignals = 0;
        $totalInquiries = 0;
        $totalMemory = 0;
        $totalDoNothing = 0;
        $totalEntities = 0;

        foreach ($prompts as $prompt) {
            $run->touch(); // Heartbeat: signal that run is still active

            $result = $service->executePrompt($prompt, $trigger->team_id, $run);

            $totalSignals += $result['signals_created'] ?? 0;
            $totalInquiries += $result['inquiries_created'] ?? 0;
            $totalMemory += $result['memory_updates'] ?? 0;
            $totalDoNothing += $result['do_nothing_count'] ?? 0;
            $totalEntities += $result['entities_analyzed'] ?? 0;
        }

        $durationMs = $this->elapsedMs($startTime);

        $run->markCompleted([
            'prompts_evaluated' => $prompts->count(),
            'entities_analyzed' => $totalEntities,
            'signals_created' => $totalSignals,
            'inquiries_created' => $totalInquiries,
            'memory_updates' => $totalMemory,
            'do_nothing_count' => $totalDoNothing,
            'duration_ms' => $durationMs,
        ]);
    }

    protected function runSynthesis(OrganizationInferenceTrigger $trigger, OrganizationInferenceRun $run, int $startTime): void
    {
        $service = resolve(InferencePromptService::class);
        $reportType = $trigger->prompt_filter['report_type'] ?? 'weekly';

        $result = $service->generateSynthesisReport($trigger->team_id, $reportType, $run);

        $durationMs = $this->elapsedMs($startTime);

        $run->markCompleted([
            'prompts_evaluated' => 1,
            'signals_created' => 0,
            'duration_ms' => $durationMs,
            'summary' => $result['title'] ?? 'Synthesis report generated',
        ]);
    }

    protected function resolvePrompts(OrganizationInferenceTrigger $trigger)
    {
        $query = OrganizationSignalInferencePrompt::forTeam($trigger->team_id)->active();

        $promptFilter = $trigger->prompt_filter ?? [];

        if (! empty($promptFilter['prompt_ids'])) {
            $query->whereIn('id', $promptFilter['prompt_ids']);
        } elseif (! empty($promptFilter['vsm_system'])) {
            $query->forVsmSystem($promptFilter['vsm_system']);
        } else {
            $query->due();
        }

        return $query->orderBy('created_at')->limit(10)->get();
    }

    protected function elapsedMs(int $startHrtime): int
    {
        return (int) ((hrtime(true) - $startHrtime) / 1_000_000);
    }
}
