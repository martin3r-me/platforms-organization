<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\Team;
use Platform\Organization\Models\OrganizationInferenceTrigger;
use Platform\Organization\Models\OrganizationSignalInferencePrompt;

class ScheduleInferenceCommand extends Command
{
    protected $signature = 'organization:schedule-inference {--team= : Optional Team-ID}';

    protected $description = 'Erzeugt scheduled Inference-Triggers für fällige Prompts.';

    public function handle(): int
    {
        $teamIds = $this->resolveTeamIds();
        $created = 0;

        foreach ($teamIds as $teamId) {
            // Find prompts that are due (not evaluated in 24h)
            $duePrompts = OrganizationSignalInferencePrompt::forTeam($teamId)
                ->active()
                ->due()
                ->get();

            foreach ($duePrompts as $prompt) {
                $trigger = OrganizationInferenceTrigger::createDebounced([
                    'team_id' => $teamId,
                    'trigger_type' => 'scheduled',
                    'trigger_reference' => $prompt->id,
                    'prompt_filter' => ['prompt_ids' => [$prompt->id]],
                    'priority' => 50,
                    'status' => 'pending',
                    'debounce_key' => "scheduled:prompt:{$prompt->id}",
                ]);

                if ($trigger) {
                    $created++;
                }
            }
        }

        $this->info("{$created} Scheduled Trigger(s) erstellt.");

        return self::SUCCESS;
    }

    protected function resolveTeamIds(): array
    {
        if ($this->option('team')) {
            return [(int) $this->option('team')];
        }

        // All teams that have active inference prompts
        return OrganizationSignalInferencePrompt::active()
            ->distinct()
            ->pluck('team_id')
            ->all();
    }
}
