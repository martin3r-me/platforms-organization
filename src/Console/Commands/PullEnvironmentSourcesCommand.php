<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Models\OrganizationEnvironmentSource;
use Platform\Organization\Services\EnvironmentPullService;

class PullEnvironmentSourcesCommand extends Command
{
    protected $signature = 'organization:pull-environment-sources {--team= : Optional Team-ID} {--source= : Optional einzelne Source-ID}';

    protected $description = 'Pullt Environment-Datenquellen (RSS-Feeds) und erstellt Snapshots mit LLM-Extraktion.';

    public function handle(): int
    {
        $service = new EnvironmentPullService();

        if ($sourceId = $this->option('source')) {
            $source = OrganizationEnvironmentSource::find((int) $sourceId);
            if (! $source) {
                $this->error("Source {$sourceId} nicht gefunden.");

                return self::FAILURE;
            }

            return $this->pullSingle($service, $source);
        }

        $query = OrganizationEnvironmentSource::active()->due();

        if ($teamId = $this->option('team')) {
            $query->forTeam((int) $teamId);
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->info('Keine fälligen Sources gefunden.');

            return self::SUCCESS;
        }

        $pulled = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($sources as $source) {
            try {
                $snapshot = $service->pullSource($source);
                if ($snapshot) {
                    $pulled++;
                    $this->info("✓ {$source->name}: Snapshot erstellt (Items: {$snapshot->metrics['new_items_count']})");
                } else {
                    $skipped++;
                    $this->line("– {$source->name}: Keine neuen Items");
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->error("✗ {$source->name}: {$e->getMessage()}");
            }
        }

        $this->info("Fertig: {$pulled} Snapshots, {$skipped} übersprungen, {$errors} Fehler.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function pullSingle(EnvironmentPullService $service, OrganizationEnvironmentSource $source): int
    {
        try {
            $snapshot = $service->pullSource($source);
            if ($snapshot) {
                $this->info("Snapshot erstellt für '{$source->name}' (Items: {$snapshot->metrics['new_items_count']})");
            } else {
                $this->info("Keine neuen Items für '{$source->name}'.");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Fehler: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
