<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Services\EntityLinkRegistry;

class DimensionCoverageCommand extends Command
{
    protected $signature = 'organization:dimension-coverage';

    protected $description = 'Zeigt Primary-Coverage und Metrik-Tagging der 7½ Dimensionen — Health-Check vor Umstellung auf score_method=primary.';

    public function handle(EntityLinkRegistry $registry): int
    {
        $allDefs = $registry->allMetricDefinitions();
        $dimensions = EntityLinkRegistry::allDimensions();

        $this->info('Aktive Scoring-Methode: ' . config('organization.dimension_score_method', 'sum'));
        $this->newLine();

        $rows = [];
        $warnings = 0;
        foreach ($dimensions as $dimKey => $dimConfig) {
            $dimMetrics = array_filter($allDefs, fn ($def) => ($def['dimension'] ?? null) === $dimKey);
            $primary = null;
            foreach ($dimMetrics as $key => $def) {
                if (!empty($def['is_dimension_primary'])) {
                    $primary = $key;
                    break;
                }
            }

            $status = $primary
                ? '<fg=green>OK</>'
                : '<fg=yellow>FALLBACK→sum</>';
            if (!$primary && count($dimMetrics) > 0) {
                $warnings++;
            }

            $rows[] = [
                $dimKey,
                $dimConfig['label'],
                $dimConfig['type'],
                count($dimMetrics),
                $primary ?? '—',
                $status,
            ];
        }

        $this->table(['dimension', 'label', 'type', '#metrics', 'primary', 'status'], $rows);

        if ($warnings > 0) {
            $this->warn("{$warnings} Dimension(en) ohne Primary — score_method=primary fällt dort auf 'sum_fallback' zurück.");
        }

        $this->newLine();
        $this->info('Metrik-Details:');

        $metricRows = [];
        foreach ($dimensions as $dimKey => $dimConfig) {
            $dimMetrics = array_filter($allDefs, fn ($def) => ($def['dimension'] ?? null) === $dimKey);
            foreach ($dimMetrics as $key => $def) {
                $flags = [];
                if (!empty($def['is_dimension_primary'])) {
                    $flags[] = '<fg=cyan>primary</>';
                }
                if (!empty($def['subset_of'])) {
                    $flags[] = '<fg=magenta>subset_of=' . $def['subset_of'] . '</>';
                }
                $metricRows[] = [
                    $dimKey,
                    $key,
                    $def['type'] ?? '?',
                    $def['basis'] ?? '?',
                    $def['unit'] ?? '?',
                    $def['aggregation_mode'] ?? 'own',
                    implode(' ', $flags),
                ];
            }
        }

        $this->table(
            ['dimension', 'metric', 'type', 'basis', 'unit', 'agg', 'flags'],
            $metricRows
        );

        $orphans = array_filter($allDefs, fn ($def) => ($def['dimension'] ?? null) === null);
        if (!empty($orphans)) {
            $this->newLine();
            $this->warn(count($orphans) . ' Metrik(en) ohne Dimension-Zuordnung:');
            foreach ($orphans as $key => $def) {
                $this->line("  - {$key} (group={$def['group']}, unit={$def['unit']})");
            }
        }

        return $warnings > 0 ? self::FAILURE : self::SUCCESS;
    }
}
