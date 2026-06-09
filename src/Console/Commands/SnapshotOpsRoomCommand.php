<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationEntityVsmAssignment;
use Platform\Organization\Models\OrganizationOpsRoomSnapshot;
use Platform\Organization\Models\OrganizationSignal;

/**
 * Schreibt fuer jede Carrier-Entity (=Perspektive) einen Tages-Snapshot
 * der Ops-Room-Counter: open/escalated/algedonic/vacant_cells +
 * Per-Level-Breakdown.
 *
 * Wird einmal taeglich um 23:55 lokaler Zeit ausgefuehrt — damit der Tag
 * vollstaendig erfasst ist (Algedonics von 23:00 zaehlen noch zum Tag).
 */
class SnapshotOpsRoomCommand extends Command
{
    protected $signature = 'organization:snapshot-ops-room';

    protected $description = 'Schreibt Tages-Snapshots der Ops-Room-Counter pro Carrier-Perspektive (fuer historische Bilanz).';

    public function handle(): int
    {
        $today = Carbon::today();

        $perspectives = OrganizationEntity::query()
            ->whereHas('type', fn ($q) => $q->where('vsm_class', OrganizationEntityType::VSM_CLASS_CARRIER))
            ->where('is_active', true)
            ->get(['id', 'team_id', 'name']);

        $count = 0;
        foreach ($perspectives as $p) {
            $perLevel = [];
            $open = 0;
            $escalated = 0;
            $algedonic = 0;

            foreach (OrganizationEntityVsmAssignment::VSM_SYSTEMS as $vsm) {
                $row = OrganizationSignal::query()
                    ->where('perspective_entity_id', $p->id)
                    ->where('vsm_level', $vsm)
                    ->where('status', 'open')
                    ->selectRaw('COUNT(*) as o, SUM(CASE WHEN escalated_at IS NOT NULL THEN 1 ELSE 0 END) as e, SUM(CASE WHEN source_type = ? THEN 1 ELSE 0 END) as a', [OrganizationSignal::SOURCE_TYPE_HUMAN_ALGEDONIC])
                    ->first();

                $o = (int) ($row->o ?? 0);
                $e = (int) ($row->e ?? 0);
                $a = (int) ($row->a ?? 0);

                $perLevel[$vsm] = ['open' => $o, 'escalated' => $e, 'algedonic' => $a];
                $open += $o;
                $escalated += $e;
                $algedonic += $a;
            }

            // Vakanz: Ebenen ohne aktive Assignment.
            $occupied = OrganizationEntityVsmAssignment::query()
                ->where('perspective_entity_id', $p->id)
                ->activeAt()
                ->distinct()
                ->pluck('vsm_system')
                ->toArray();
            $vacantCells = count(array_diff(OrganizationEntityVsmAssignment::VSM_SYSTEMS, $occupied));

            OrganizationOpsRoomSnapshot::updateOrCreate(
                [
                    'perspective_entity_id' => $p->id,
                    'snapshot_date' => $today->toDateString(),
                ],
                [
                    'team_id' => $p->team_id,
                    'open_count' => $open,
                    'escalated_count' => $escalated,
                    'algedonic_count' => $algedonic,
                    'vacant_cells_count' => $vacantCells,
                    'per_level' => $perLevel,
                ]
            );
            $count++;
        }

        $this->info("Snapshots geschrieben: $count Perspektive(n) fuer {$today->toDateString()}.");
        return self::SUCCESS;
    }
}
