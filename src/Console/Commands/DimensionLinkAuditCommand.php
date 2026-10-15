<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Platform\Organization\Services\DimensionLinkAuditService;

class DimensionLinkAuditCommand extends Command
{
    protected $signature = 'organization:dimension-link-audit {--details : Beispielzeilen je Check anzeigen}';

    protected $description = 'Read-only Integritäts-Audit der DimensionLinks — Invarianten-Check vor/nach der Härtung. Exit-Code FAILURE, wenn Funde vorliegen.';

    public function handle(DimensionLinkAuditService $audit): int
    {
        $checks = $audit->run();

        $this->info('DimensionLink-Audit (read-only)');
        $this->newLine();

        $summary = [];
        $findings = 0;
        foreach ($checks as $code => $check) {
            $isWarn = $check['status'] === 'warn';
            $findings += $isWarn ? 1 : 0;

            $summary[] = [
                $code,
                $check['label'],
                $check['count'],
                $isWarn ? '<fg=yellow>FUND</>' : '<fg=green>OK</>',
            ];
        }

        $this->table(['check', 'beschreibung', 'count', 'status'], $summary);

        if ($this->option('details')) {
            foreach ($checks as $code => $check) {
                if ($check['status'] !== 'warn' || empty($check['samples'])) {
                    continue;
                }
                $this->newLine();
                $this->warn("{$code} — {$check['label']} ({$check['count']})");
                $headers = array_keys($check['samples'][0]);
                $rows = array_map(fn ($s) => array_values($s), $check['samples']);
                $this->table($headers, $rows);
                if ($check['count'] > count($check['samples'])) {
                    $this->line('  … ' . ($check['count'] - count($check['samples'])) . ' weitere');
                }
            }
        } elseif ($findings > 0) {
            $this->newLine();
            $this->line('<fg=gray>Mit --details Beispielzeilen je Fund anzeigen.</>');
        }

        $this->newLine();
        if ($findings > 0) {
            $this->warn("{$findings} Check(s) mit Funden.");
            return self::FAILURE;
        }

        $this->info('Alle Invarianten erfüllt.');
        return self::SUCCESS;
    }
}
