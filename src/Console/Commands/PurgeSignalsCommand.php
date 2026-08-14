<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationSignal;

/**
 * Löscht alle Signale eines Teams inkl. der abhängigen Zeilen
 * (Actions, Comments, Focuses). Dry-Run per Default — löscht erst mit --force.
 */
class PurgeSignalsCommand extends Command
{
    protected $signature = 'organization:signals:purge
        {team : Team-ID, deren Signale entfernt werden}
        {--force : Wirklich löschen (ohne Flag nur Dry-Run mit Zählung)}
        {--soft : Soft-Delete (setzt deleted_at) statt Hard-Delete}';

    protected $description = 'Entfernt alle Signale eines Teams inkl. Actions/Comments/Focuses (Dry-Run per Default).';

    public function handle(): int
    {
        $teamId = (int) $this->argument('team');

        if ($teamId <= 0) {
            $this->error('Ungültige Team-ID.');

            return self::FAILURE;
        }

        // withTrashed: auch bereits soft-deletete Signale werden erfasst.
        $signalIds = OrganizationSignal::withTrashed()
            ->where('team_id', $teamId)
            ->pluck('id');

        $count = $signalIds->count();

        if ($count === 0) {
            $this->info("Team {$teamId}: keine Signale gefunden.");

            return self::SUCCESS;
        }

        $actions = DB::table('organization_signal_actions')->whereIn('signal_id', $signalIds)->count();
        $comments = DB::table('organization_signal_comments')->whereIn('signal_id', $signalIds)->count();
        $focuses = DB::table('organization_signal_focuses')->whereIn('signal_id', $signalIds)->count();

        $this->info("Team {$teamId}:");
        $this->line("  Signale:  {$count}");
        $this->line("  Actions:  {$actions}");
        $this->line("  Comments: {$comments}");
        $this->line("  Focuses:  {$focuses}");

        if (! $this->option('force')) {
            $this->warn('DRY-RUN — nichts gelöscht. Mit --force wirklich ausführen.');

            return self::SUCCESS;
        }

        $soft = (bool) $this->option('soft');

        DB::transaction(function () use ($signalIds, $soft) {
            // Selbst-Referenz (Aggregation) auflösen, um FK-Konflikte zu vermeiden.
            OrganizationSignal::withTrashed()
                ->whereIn('id', $signalIds)
                ->update(['aggregated_to_signal_id' => null]);

            if ($soft) {
                OrganizationSignal::whereIn('id', $signalIds)->delete();

                return;
            }

            // Hard-Delete: erst die Kind-Zeilen, dann die Signale.
            DB::table('organization_signal_actions')->whereIn('signal_id', $signalIds)->delete();
            DB::table('organization_signal_comments')->whereIn('signal_id', $signalIds)->delete();
            DB::table('organization_signal_focuses')->whereIn('signal_id', $signalIds)->delete();

            OrganizationSignal::withTrashed()->whereIn('id', $signalIds)->forceDelete();
        });

        $mode = $soft ? 'soft-deleted' : 'hart gelöscht';
        $this->info("Fertig — {$count} Signal(e) {$mode} (Team {$teamId}).");

        return self::SUCCESS;
    }
}
