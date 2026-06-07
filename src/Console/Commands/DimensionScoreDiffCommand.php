<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\DimensionRadarService;
use Platform\Organization\Services\EntityLinkRegistry;

class DimensionScoreDiffCommand extends Command
{
    protected $signature = 'organization:dimension-score-diff
        {--team= : Team-ID (Pflicht — Vergleich laeuft pro Team-Maximum)}
        {--limit=20 : Max. Anzahl Entities (sortiert nach |delta|)}
        {--dimension= : Auf eine Dimension einschraenken (z.B. energy)}
        {--with-zero : Auch Entities/Dimensionen ohne Daten anzeigen}';

    protected $description = 'Vergleicht Dimension-Scores unter sum vs primary fuer alle Entities eines Teams — Audit vor Umstellung des Defaults.';

    public function handle(DimensionRadarService $radar): int
    {
        $teamId = (int) $this->option('team');
        if (!$teamId) {
            $this->error('--team= ist Pflicht (Scores werden gegen Team-Maxima normalisiert).');
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $dimensionFilter = $this->option('dimension');
        $includeZero = (bool) $this->option('with-zero');

        $entities = OrganizationEntity::where('team_id', $teamId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($entities->isEmpty()) {
            $this->warn("Keine aktiven Entities in Team {$teamId}.");
            return self::SUCCESS;
        }

        $this->info("Vergleich fuer Team {$teamId} — {$entities->count()} Entities, Methode 'sum' vs 'primary'.");
        $this->newLine();

        $originalMethod = config('organization.dimension_score_method', 'sum');

        // Erst 'sum' fuer alle, dann 'primary' fuer alle — Service cached team-maxima
        // intern per (team_id, method), beide Runs koexistieren.
        config(['organization.dimension_score_method' => 'sum']);
        $sumResults = [];
        foreach ($entities as $entity) {
            $sumResults[$entity->id] = $radar->computeRadar($entity->id, $teamId);
        }

        config(['organization.dimension_score_method' => 'primary']);
        $primaryResults = [];
        foreach ($entities as $entity) {
            $primaryResults[$entity->id] = $radar->computeRadar($entity->id, $teamId);
        }

        config(['organization.dimension_score_method' => $originalMethod]);

        $dimensions = EntityLinkRegistry::allDimensions();
        if ($dimensionFilter) {
            if (!isset($dimensions[$dimensionFilter])) {
                $this->error("Unbekannte Dimension: {$dimensionFilter}");
                return self::FAILURE;
            }
            $dimensions = [$dimensionFilter => $dimensions[$dimensionFilter]];
        }

        $rows = [];
        $fallbackCount = 0;
        $bigDiffCount = 0;

        foreach ($entities as $entity) {
            foreach ($dimensions as $dimKey => $dimConfig) {
                $sum = $sumResults[$entity->id][$dimKey] ?? null;
                $primary = $primaryResults[$entity->id][$dimKey] ?? null;
                if (!$sum || !$primary) {
                    continue;
                }

                if (!$includeZero && !$sum['has_data'] && !$primary['has_data']) {
                    continue;
                }

                $scoreSum = (float) $sum['score'];
                $scorePrimary = (float) $primary['score'];
                $diff = round($scorePrimary - $scoreSum, 1);

                $methodEffective = $primary['score_method'];
                if ($methodEffective === 'sum_fallback') {
                    $fallbackCount++;
                }
                if (abs($diff) >= 20) {
                    $bigDiffCount++;
                }

                $rows[] = [
                    '_sort' => abs($diff),
                    'entity' => "{$entity->name} (#{$entity->id})",
                    'dim' => $dimKey,
                    'sum_score' => number_format($scoreSum, 1),
                    'sum_raw' => number_format((float) $sum['raw'], 1),
                    'primary_score' => number_format($scorePrimary, 1),
                    'primary_raw' => number_format((float) $primary['raw'], 1),
                    'primary_metric' => $primary['primary_metric'] ?? '—',
                    'diff' => $this->formatDiff($diff),
                    'method' => $methodEffective,
                ];
            }
        }

        usort($rows, fn ($a, $b) => $b['_sort'] <=> $a['_sort']);
        $rows = array_slice($rows, 0, $limit);

        foreach ($rows as &$r) {
            unset($r['_sort']);
        }

        $this->table(
            ['entity', 'dim', 'sum_score', 'sum_raw', 'primary_score', 'primary_raw', 'primary_metric', 'Δ', 'method'],
            $rows
        );

        $this->newLine();
        $this->info('Zusammenfassung:');
        $this->line("  Verglichene Entity-Dimension-Paare: " . count($entities) * count($dimensions));
        $this->line("  Anzeigt (sortiert nach |Δ|, limit {$limit}): " . count($rows));
        if ($fallbackCount > 0) {
            $this->warn("  ⚠ {$fallbackCount} Paar(e) nutzen sum_fallback (keine Primary fuer Dimension deklariert)");
        }
        if ($bigDiffCount > 0) {
            $this->warn("  ⚠ {$bigDiffCount} Paar(e) mit |Δ| ≥ 20 Punkten — wahrscheinlich starke Unitmix-Verzerrung in 'sum'");
        }

        return self::SUCCESS;
    }

    protected function formatDiff(float $diff): string
    {
        if ($diff == 0) {
            return '<fg=gray>±0</>';
        }
        $sign = $diff > 0 ? '+' : '';
        $color = abs($diff) >= 20 ? 'yellow' : ($diff > 0 ? 'green' : 'red');

        return "<fg={$color}>{$sign}{$diff}</>";
    }
}
