<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Services\SignalEscalationService;

class EscalateSignalsCommand extends Command
{
    protected $signature = 'organization:escalate-signals';

    protected $description = 'Eskaliert ueberfaellige Signale entlang der VSM-Stufen und aggregiert unabsorbierte S5-Signale.';

    public function handle(SignalEscalationService $service): int
    {
        $result = $service->run();

        $this->info(sprintf(
            'Eskaliert: %d, Aggregiert: %d',
            $result['escalated'],
            $result['aggregated']
        ));

        return self::SUCCESS;
    }
}
