<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Models\OrganizationEntityType;
use Platform\Organization\Models\OrganizationPerspectiveTeam;
use Platform\Organization\Support\EntityTypeCatalog;

/**
 * Schreibt die code-owned Klassifikation aus EntityTypeCatalog in die DB.
 *
 * - vsm_class + can_be_perspective werden nach Katalog gesetzt.
 * - Perspective-Team-Zuordnungen zu jetzt-nicht-mehr-Carrier-Entities werden
 *   entfernt (Cleanup, damit keine Zombie-Defaults im PerspectiveService haengen).
 * - --dry-run zeigt nur an, was passieren wuerde.
 */
class SyncEntityTypeCatalogCommand extends Command
{
    protected $signature = 'organization:sync-catalog {--dry-run : Nichts schreiben, nur Diff anzeigen}';

    protected $description = 'Synchronisiert die VSM-Klassifikation der Entity-Types aus EntityTypeCatalog in die DB.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'DRY-RUN — es wird nichts geschrieben.' : 'Sync laeuft…');

        $types = OrganizationEntityType::query()->orderBy('code')->get();
        $classChanges = [];
        $unchanged = 0;

        foreach ($types as $type) {
            $targetClass = EntityTypeCatalog::vsmClass($type->code);
            $targetPerspective = EntityTypeCatalog::canBePerspective($type->code);

            $currentClass = $type->vsm_class;
            $currentPerspective = (bool) $type->can_be_perspective;

            if ($currentClass === $targetClass && $currentPerspective === $targetPerspective) {
                $unchanged++;
                continue;
            }

            $classChanges[] = [
                'code'            => $type->code,
                'name'            => $type->name,
                'from_class'      => $currentClass ?? '—',
                'to_class'        => $targetClass,
                'from_persp'      => $currentPerspective ? 'yes' : 'no',
                'to_persp'        => $targetPerspective ? 'yes' : 'no',
            ];

            if (! $dryRun) {
                $type->vsm_class = $targetClass;
                $type->can_be_perspective = $targetPerspective;
                $type->save();
            }
        }

        if (empty($classChanges)) {
            $this->line("Alle {$unchanged} Entity-Types passen bereits zum Katalog. Nichts zu tun.");
        } else {
            $this->newLine();
            $this->line("Aenderungen ({$this->countLabel($classChanges)}):");
            $this->table(
                ['Code', 'Name', 'vsm_class alt→neu', 'perspective alt→neu'],
                array_map(fn ($c) => [
                    $c['code'],
                    $c['name'],
                    $c['from_class'].' → '.$c['to_class'],
                    $c['from_persp'].' → '.$c['to_persp'],
                ], $classChanges)
            );
            $this->line("Unveraendert: {$unchanged}");
        }

        // Cleanup: verwaiste perspective_teams-Zuordnungen (Entities, deren Type
        // jetzt nicht mehr Carrier ist).
        $this->newLine();
        $this->info('Cleanup perspective_teams…');

        if (! Schema::hasTable('organization_perspective_teams')) {
            $this->line('Tabelle organization_perspective_teams existiert nicht in diesem Environment — Cleanup uebersprungen.');
            $this->newLine();
            $this->info($dryRun ? 'DRY-RUN beendet.' : 'Sync fertig.');
            return self::SUCCESS;
        }

        $nonCarrierTypeIds = OrganizationEntityType::query()
            ->where('vsm_class', '!=', OrganizationEntityType::VSM_CLASS_CARRIER)
            ->pluck('id')
            ->all();

        $orphanRows = OrganizationPerspectiveTeam::query()
            ->whereIn('perspective_entity_id', function ($q) use ($nonCarrierTypeIds) {
                $q->from('organization_entities')
                    ->select('id')
                    ->whereIn('entity_type_id', $nonCarrierTypeIds);
            })
            ->get();

        if ($orphanRows->isEmpty()) {
            $this->line('Keine verwaisten perspective_teams-Zuordnungen.');
        } else {
            $this->line("Verwaiste Zuordnungen: {$orphanRows->count()}");
            foreach ($orphanRows as $row) {
                $entity = OrganizationEntity::query()->with('type:id,code,name')->find($row->perspective_entity_id);
                $entityLabel = $entity
                    ? sprintf('#%d %s (%s)', $entity->id, $entity->name, $entity->type?->code ?? '?')
                    : '#'.$row->perspective_entity_id.' (nicht gefunden)';
                $this->line(sprintf('  team=%d perspective=%s default=%s', $row->team_id, $entityLabel, $row->is_default ? 'yes' : 'no'));
                if (! $dryRun) {
                    $row->delete();
                }
            }
            $this->line($dryRun ? 'DRY-RUN — nicht geloescht.' : 'Geloescht.');
        }

        $this->newLine();
        $this->info($dryRun ? 'DRY-RUN beendet.' : 'Sync fertig.');

        return self::SUCCESS;
    }

    private function countLabel(array $changes): string
    {
        $n = count($changes);
        return $n === 1 ? '1 Aenderung' : "{$n} Aenderungen";
    }
}
