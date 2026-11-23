<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Hot-Path für authz_scope_closure: hält die Closure eines Knotens samt Teilbaum sofort aktuell,
 * wenn eine Entity angelegt, umgehängt, gelöscht oder wiederhergestellt wird. Gleiche Semantik wie
 * AuthzMaterializeCommand::buildClosure (nur nicht-gelöschte Entities des Teams, Kette über
 * parent_entity_id, team_id = Team der Entity). Das 10-Minuten-Materialize bleibt das Reconcile-Netz.
 */
class AuthzClosureSync
{
    private static ?bool $hasTable = null;

    /**
     * Baut die Closure-Zeilen für $entityId und alle (nicht-gelöschten) Nachfahren neu auf.
     * Wirft nie: ein Closure-Fehler darf das Speichern der Entity nicht verhindern.
     */
    public static function syncSubtree(int $entityId, ?int $teamId): void
    {
        if (! $teamId || ! self::hasTable()) {
            return;
        }

        try {
            // Savepoint: scheitert die Closure innerhalb einer äußeren Transaktion, wird nur
            // dieser Teil zurückgerollt (wichtig für Postgres, wo sonst die ganze TX abbricht).
            DB::transaction(fn () => self::rebuild($entityId, $teamId));
        } catch (\Throwable $e) {
            Log::warning('[Organization] authz_scope_closure konnte nicht aktualisiert werden (Reconcile via authz:materialize).', [
                'entity_id' => $entityId,
                'team_id'   => $teamId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private static function rebuild(int $entityId, int $teamId): void
    {
        // id => parent_entity_id (alle nicht-gelöschten Entities des Teams), wie buildClosure.
        $parents = DB::table('organization_entities')
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->pluck('parent_entity_id', 'id')
            ->all();

        $children = [];
        foreach ($parents as $id => $parent) {
            if ($parent) {
                $children[(int) $parent][] = (int) $id;
            }
        }

        // Teilbaum ab dem Knoten (BFS über die aktuellen Eltern-Zeiger).
        $subtree = [$entityId => true];
        $queue = [$entityId];
        while ($queue) {
            $cur = array_shift($queue);
            foreach ($children[$cur] ?? [] as $child) {
                if (! isset($subtree[$child])) {
                    $subtree[$child] = true;
                    $queue[] = $child;
                }
            }
        }
        $subtreeIds = array_keys($subtree);

        foreach (array_chunk($subtreeIds, 1000) as $chunk) {
            DB::table('authz_scope_closure')->whereIn('descendant_id', $chunk)->delete();
        }

        $rows = [];
        foreach ($subtreeIds as $id) {
            // Gelöschte Knoten (z. B. der soeben soft-gelöschte) bekommen keine Zeilen.
            if (! array_key_exists($id, $parents)) {
                continue;
            }
            $cur = $id;
            $depth = 0;
            $seen = [];
            while ($cur && ! isset($seen[$cur]) && $depth <= 100) {
                $seen[$cur] = true;
                $rows[] = [
                    'ancestor_id'   => $cur,
                    'descendant_id' => $id,
                    'depth'         => $depth,
                    'team_id'       => $teamId,
                ];
                $cur = isset($parents[$cur]) ? (int) $parents[$cur] : 0;
                $depth++;
            }
            if (count($rows) >= 1000) {
                DB::table('authz_scope_closure')->insertOrIgnore($rows);
                $rows = [];
            }
        }
        if ($rows) {
            DB::table('authz_scope_closure')->insertOrIgnore($rows);
        }
    }

    private static function hasTable(): bool
    {
        return self::$hasTable ??= Schema::hasTable('authz_scope_closure');
    }

    /** Nur für Tests: Tabellen-Check zurücksetzen. */
    public static function flushTableCache(): void
    {
        self::$hasTable = null;
    }
}
