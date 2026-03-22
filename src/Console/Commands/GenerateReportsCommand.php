<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationReport;
use Platform\Organization\Models\OrganizationReportType;
use Platform\Organization\Services\ReportGenerator;

class GenerateReportsCommand extends Command
{
    protected $signature = 'reports:generate
        {--report-type-id= : ID eines bestimmten Berichtstyps}
        {--entity-id= : ID einer bestimmten Entity}
        {--user-id= : Als bestimmter User ausführen}';

    protected $description = 'Generiert Berichte basierend auf fälligen Berichtstypen oder expliziten Parametern';

    public function handle(): int
    {
        $reportTypeId = $this->option('report-type-id');
        $entityId = $this->option('entity-id');
        $userId = $this->option('user-id');

        $generator = new ReportGenerator();

        // Expliziter Einzel-Modus
        if ($reportTypeId) {
            return $this->generateSingle($generator, (int) $reportTypeId, $entityId ? (int) $entityId : null, $userId ? (int) $userId : null);
        }

        // Scheduler-Modus: alle fälligen Berichtstypen prüfen
        return $this->generateScheduled($generator);
    }

    protected function generateSingle(ReportGenerator $generator, int $reportTypeId, ?int $entityId, ?int $userId): int
    {
        $reportType = OrganizationReportType::find($reportTypeId);
        if (!$reportType) {
            $this->error("Berichtstyp mit ID {$reportTypeId} nicht gefunden.");
            return 1;
        }

        $user = $userId ? User::find($userId) : ($reportType->user_id ? User::find($reportType->user_id) : null);
        if (!$user) {
            $this->error('Kein User angegeben oder am Berichtstyp hinterlegt. Nutze --user-id.');
            return 1;
        }

        // Entities bestimmen
        $entities = collect();
        if ($entityId) {
            $entity = OrganizationEntity::where('id', $entityId)
                ->where('team_id', $reportType->team_id)
                ->first();
            if (!$entity) {
                $this->error("Entity mit ID {$entityId} nicht gefunden im Team des Berichtstyps.");
                return 1;
            }
            $entities->push($entity);
        } else {
            // Alle aktiven Entities im Team
            $entities = OrganizationEntity::where('team_id', $reportType->team_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get();
        }

        if ($entities->isEmpty()) {
            $this->warn('Keine Entities gefunden.');
            return 0;
        }

        $this->info("Generiere {$entities->count()} Bericht(e) für Typ \"{$reportType->name}\"...");

        $success = 0;
        $failed = 0;

        foreach ($entities as $entity) {
            $this->line("  → {$entity->name}...");

            try {
                Auth::login($user);
                $report = $generator->generate($reportType, $entity, $user);

                if ($report->status === 'final') {
                    $this->info("    ✓ Bericht generiert (ID: {$report->id})");
                    $success++;
                } else {
                    $this->warn("    ✗ Fehlgeschlagen: {$report->error_message}");
                    $failed++;
                }
            } catch (\Throwable $e) {
                $this->error("    ✗ Fehler: {$e->getMessage()}");
                Log::error('[GenerateReportsCommand] Einzelbericht fehlgeschlagen', [
                    'report_type_id' => $reportType->id,
                    'entity_id' => $entity->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->info("Ergebnis: {$success} erfolgreich, {$failed} fehlgeschlagen.");
        return $failed > 0 ? 1 : 0;
    }

    protected function generateScheduled(ReportGenerator $generator): int
    {
        $this->info('Prüfe fällige Berichtstypen...');

        $reportTypes = OrganizationReportType::where('is_active', true)
            ->where('frequency', '!=', 'manual')
            ->whereNull('deleted_at')
            ->get();

        if ($reportTypes->isEmpty()) {
            $this->info('Keine automatischen Berichtstypen gefunden.');
            return 0;
        }

        $checked = 0;
        $generated = 0;
        $failed = 0;

        foreach ($reportTypes as $reportType) {
            $checked++;

            if (!$this->isDue($reportType)) {
                continue;
            }

            $user = $reportType->user_id ? User::find($reportType->user_id) : null;
            if (!$user) {
                $this->warn("Berichtstyp \"{$reportType->name}\" hat keinen User hinterlegt, überspringe.");
                continue;
            }

            $entities = OrganizationEntity::where('team_id', $reportType->team_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get();

            foreach ($entities as $entity) {
                $this->line("Generiere \"{$reportType->name}\" für \"{$entity->name}\"...");

                try {
                    Auth::login($user);
                    $report = $generator->generate($reportType, $entity, $user);

                    if ($report->status === 'final') {
                        $this->info("  ✓ Generiert (ID: {$report->id})");
                        $generated++;
                    } else {
                        $this->warn("  ✗ Fehlgeschlagen: {$report->error_message}");
                        $failed++;
                    }
                } catch (\Throwable $e) {
                    $this->error("  ✗ Fehler: {$e->getMessage()}");
                    Log::error('[GenerateReportsCommand] Scheduler-Bericht fehlgeschlagen', [
                        'report_type_id' => $reportType->id,
                        'entity_id' => $entity->id,
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;
                }
            }
        }

        $this->info("Ergebnis: {$checked} geprüft, {$generated} generiert, {$failed} fehlgeschlagen.");
        return $failed > 0 ? 1 : 0;
    }

    protected function isDue(OrganizationReportType $reportType): bool
    {
        $lastReport = OrganizationReport::where('report_type_id', $reportType->id)
            ->where('status', 'final')
            ->orderBy('snapshot_at', 'desc')
            ->first();

        if (!$lastReport) {
            return true; // Noch nie generiert
        }

        $lastSnapshot = $lastReport->snapshot_at;
        $now = now();

        return match ($reportType->frequency) {
            'daily' => $lastSnapshot->diffInHours($now) >= 20,
            'weekly' => $lastSnapshot->diffInDays($now) >= 6,
            'monthly' => $lastSnapshot->diffInDays($now) >= 27,
            default => false,
        };
    }
}
