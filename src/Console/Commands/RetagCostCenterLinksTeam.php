<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetagCostCenterLinksTeam extends Command
{
    /**
     * Example:
     *  php artisan organization:cost-center-links:reteam --from=22 --to=37 --dry-run
     *  php artisan organization:cost-center-links:reteam --from=22 --to=37 --yes
     */
    protected $signature = 'organization:cost-center-links:reteam
        {--from=22 : Alte team_id}
        {--to=37 : Neue team_id}
        {--dry-run : Nur zählen, nichts ändern}
        {--yes : Ohne Rückfrage ausführen}';

    protected $description = 'Ändert organization_cost_center_links.team_id von einer Team-ID auf eine andere (Bulk Update).';

    public function handle(): int
    {
        $from = (int)$this->option('from');
        $to = (int)$this->option('to');
        $dryRun = (bool)$this->option('dry-run');
        $yes = (bool)$this->option('yes');

        if ($from <= 0 || $to <= 0) {
            $this->error('from/to müssen positive Integers sein.');
            return self::FAILURE;
        }
        if ($from === $to) {
            $this->warn('from und to sind gleich – nichts zu tun.');
            return self::SUCCESS;
        }

        $count = DB::table('organization_cost_center_links')
            ->where('team_id', $from)
            ->count();

        $this->info("Gefunden: {$count} organization_cost_center_links mit team_id={$from}");

        if ($dryRun) {
            $this->comment('dry-run aktiv: keine Änderungen vorgenommen.');
            return self::SUCCESS;
        }

        if (!$yes) {
            if (!$this->confirm("Wirklich alle {$count} Links von team_id={$from} auf team_id={$to} ändern?", false)) {
                $this->warn('Abgebrochen.');
                return self::SUCCESS;
            }
        }

        $updated = DB::table('organization_cost_center_links')
            ->where('team_id', $from)
            ->update(['team_id' => $to]);

        $this->info("Aktualisiert: {$updated} Zeilen.");

        return self::SUCCESS;
    }
}

