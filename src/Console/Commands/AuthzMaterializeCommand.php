<?php

namespace Platform\Organization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Materialisiert die Content-Achse des Autorisierungs-Graphen aus den
 * organization-Daten in die core-authz_*-Tabellen. Team-scoped, idempotent.
 *
 * Phase 1 — Closure:   organization_entities (parent_entity_id) → authz_scope_closure
 *                      (ancestor→descendant+depth, self@0). Reichweite im Baum.
 * Phase 2 — Links:     entity-sourced DimensionLinks → authz_resource_link
 *                      (welches Modul-Objekt hängt an welcher Entity). linkable_type
 *                      wird via Morph-Map auf die volle Klasse normalisiert.
 * Phase 3 — Grants:    organization_role_assignments → authz_grant(scope=entity)
 *                      (Person hält Rolle an Kontext-Entity). Capability aus
 *                      --default-capability (Role trägt sie noch nicht; TODO: Feld
 *                      auf organization_roles).
 *
 * Ändert NICHTS am Enforcement — füllt nur die Tabellen; der Shadow misst weiter.
 */
class AuthzMaterializeCommand extends Command
{
    protected $signature = 'authz:materialize {--team= : Team-ID} {--all : Alle Teams mit Org-Graph} {--default-capability=write : read|write|manage für Rollen-Grants}';

    protected $description = 'Materialisiert Content-Achse (Closure + resource_links + Rollen-Grants) EINES Teams in die authz_*-Tabellen.';

    public function handle(): int
    {
        foreach (['authz_scope_closure', 'authz_resource_link', 'authz_grant', 'organization_entities'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->error("Tabelle {$t} fehlt — Migrationen nicht vollständig.");
                return self::FAILURE;
            }
        }

        $cap = (string) $this->option('default-capability');
        if (! in_array($cap, ['read', 'write', 'manage'], true)) {
            $cap = 'write';
        }

        if ($this->option('all')) {
            $teamIds = DB::table('organization_entities')
                ->whereNull('deleted_at')
                ->whereNotNull('team_id')
                ->distinct()
                ->pluck('team_id')
                ->all();
            foreach ($teamIds as $tid) {
                $this->materializeTeam((int) $tid, $cap);
            }
            $this->info('Fertig: '.count($teamIds).' Team(s) materialisiert.');

            return self::SUCCESS;
        }

        $teamId = (int) $this->option('team');
        if ($teamId <= 0) {
            $this->error('Bitte Team angeben: php artisan authz:materialize --team=<id> (oder --all)');
            return self::FAILURE;
        }

        $this->materializeTeam($teamId, $cap);

        return self::SUCCESS;
    }

    private function materializeTeam(int $teamId, string $cap): void
    {
        $closure   = $this->buildClosure($teamId);
        $links     = $this->buildResourceLinks($teamId);
        $grants    = $this->buildRoleGrants($teamId, $cap);
        $relGrants = $this->buildRelationGrants($teamId);

        $this->info(sprintf(
            'Team %d: Closure %d, resource_links %d, Rollen-Grants %d, Relation-Grants %d (default-cap=%s).',
            $teamId, $closure, $links, $grants, $relGrants, $cap
        ));
    }

    /** Phase 1: Entity-Hierarchie → transitive Closure. */
    private function buildClosure(int $teamId): int
    {
        // id => parent_entity_id (alle nicht-gelöschten Entities des Teams).
        $parents = DB::table('organization_entities')
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->pluck('parent_entity_id', 'id');

        DB::table('authz_scope_closure')->where('team_id', $teamId)->delete();

        $rows = [];
        $count = 0;

        foreach ($parents as $id => $parent) {
            $cur = (int) $id;
            $depth = 0;
            $seen = [];
            while ($cur && ! isset($seen[$cur]) && $depth <= 100) {
                $seen[$cur] = true;
                $rows[] = [
                    'ancestor_id'   => $cur,
                    'descendant_id' => (int) $id,
                    'depth'         => $depth,
                    'team_id'       => $teamId,
                ];
                $count++;
                $cur = isset($parents[$cur]) ? (int) $parents[$cur] : 0;
                $depth++;
            }
            if (count($rows) >= 1000) {
                DB::table('authz_scope_closure')->insert($rows);
                $rows = [];
            }
        }
        if ($rows) {
            DB::table('authz_scope_closure')->insert($rows);
        }

        return $count;
    }

    /** Phase 2: entity-sourced DimensionLinks → resource_links (Objekt → Entity). */
    private function buildResourceLinks(int $teamId): int
    {
        DB::table('authz_resource_link')->where('team_id', $teamId)->delete();

        if (! Schema::hasTable('organization_dimension_definitions')
            || ! Schema::hasTable('organization_dimension_values')
            || ! Schema::hasTable('organization_dimension_links')) {
            return 0;
        }

        $defIds = DB::table('organization_dimension_definitions')
            ->where('value_source', 'entity')
            ->where('is_active', true)
            ->pluck('id')
            ->all();
        if (! $defIds) {
            return 0;
        }

        // Entities dieses Teams (für Scoping der Value→Entity-Map).
        $teamEntities = DB::table('organization_entities')
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->flip();

        // dimension_value_id => entity_id (nur Team-Entities), aus metadata.source_entity_id.
        $dvToEntity = [];
        DB::table('organization_dimension_values')
            ->whereIn('dimension_definition_id', $defIds)
            ->whereNull('deleted_at')
            ->select('id', 'metadata')
            ->orderBy('id')
            ->chunk(1000, function ($vals) use (&$dvToEntity, $teamEntities) {
                foreach ($vals as $v) {
                    $meta = json_decode($v->metadata ?? '', true);
                    $eid = is_array($meta) ? ($meta['source_entity_id'] ?? null) : null;
                    if ($eid && isset($teamEntities[$eid])) {
                        $dvToEntity[$v->id] = (int) $eid;
                    }
                }
            });

        if (! $dvToEntity) {
            return 0;
        }

        $today = now()->toDateString();
        $rows = [];
        $seen = [];
        $count = 0;

        DB::table('organization_dimension_links')
            ->whereIn('dimension_value_id', array_keys($dvToEntity))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
            ->select('linkable_type', 'linkable_id', 'dimension_value_id')
            ->orderBy('id')
            ->chunk(1000, function ($links) use (&$rows, &$seen, &$count, $dvToEntity, $teamId) {
                foreach ($links as $l) {
                    $eid = $dvToEntity[$l->dimension_value_id] ?? null;
                    if (! $eid || ! $l->linkable_type || ! $l->linkable_id) {
                        continue;
                    }
                    // Morph-Alias → volle Klasse (muss mit dem Gate-Resource-Typ matchen).
                    $fqcn = Relation::getMorphedModel($l->linkable_type) ?: $l->linkable_type;

                    $key = $fqcn.'|'.$l->linkable_id.'|'.$eid;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $rows[] = [
                        'resource_type' => $fqcn,
                        'resource_id'   => (int) $l->linkable_id,
                        'scope_id'      => $eid,
                        'team_id'       => $teamId,
                    ];
                    $count++;
                    if (count($rows) >= 1000) {
                        DB::table('authz_resource_link')->insert($rows);
                        $rows = [];
                    }
                }
            });
        if ($rows) {
            DB::table('authz_resource_link')->insert($rows);
        }

        return $count;
    }

    /** Phase 3: gültige RoleAssignments → Entity-Grants (Subjekt = Person-Entity). */
    private function buildRoleGrants(int $teamId, string $cap): int
    {
        DB::table('authz_grant')
            ->where('team_id', $teamId)
            ->where('source', 'org:role_assignment')
            ->delete();

        if (! Schema::hasTable('organization_role_assignments')) {
            return 0;
        }

        $today = now()->toDateString();
        $now = now();
        $rows = [];
        $count = 0;

        // Capability pro Rolle (falls Feld vorhanden) — Assignment erbt sie,
        // Fallback = --default-capability.
        $roleCaps = Schema::hasColumn('organization_roles', 'capability')
            ? DB::table('organization_roles')->pluck('capability', 'id')->all()
            : [];

        DB::table('organization_role_assignments')
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->select('person_entity_id', 'context_entity_id', 'role_id', 'valid_from', 'valid_to')
            ->orderBy('id')
            ->chunk(1000, function ($assignments) use (&$rows, &$count, $cap, $teamId, $now, $roleCaps) {
                foreach ($assignments as $a) {
                    if (! $a->person_entity_id || ! $a->context_entity_id) {
                        continue;
                    }
                    $capability = $roleCaps[$a->role_id] ?? $cap;
                    if (! in_array($capability, ['read', 'write', 'manage'], true)) {
                        $capability = $cap;
                    }
                    $rows[] = [
                        'subject_type' => 'entity',
                        'subject_id'   => (int) $a->person_entity_id,
                        'capability'   => $capability,
                        'scope_type'   => 'entity',
                        'scope_id'     => (int) $a->context_entity_id,
                        'scope_key'    => null,
                        'source'       => 'org:role_assignment',
                        'valid_from'   => $a->valid_from,
                        'valid_to'     => $a->valid_to,
                        'team_id'      => $teamId,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                    $count++;
                    if (count($rows) >= 500) {
                        DB::table('authz_grant')->insert($rows);
                        $rows = [];
                    }
                }
            });
        if ($rows) {
            DB::table('authz_grant')->insert($rows);
        }

        return $count;
    }

    /**
     * Phase 4: zugriffsgebende Relations (Person → Entity) → Entity-Grants.
     * Capability kommt vom Relation-TYP (default null = kein Zugriff). Nur
     * Kanten, deren from-Seite eine Person ist (Subjekt = Person, Scope = Ziel).
     */
    private function buildRelationGrants(int $teamId): int
    {
        DB::table('authz_grant')
            ->where('team_id', $teamId)
            ->where('source', 'org:relation')
            ->delete();

        if (! Schema::hasTable('organization_entity_relationships')
            || ! Schema::hasTable('organization_entity_relation_types')
            || ! Schema::hasColumn('organization_entity_relation_types', 'capability')) {
            return 0;
        }

        $typeCaps = DB::table('organization_entity_relation_types')
            ->whereNotNull('capability')
            ->pluck('capability', 'id')
            ->all();
        if ($typeCaps === []) {
            return 0;
        }

        // Nur Person-Entities als Subjekt (Kante Person → Entity).
        $personIds = DB::table('organization_entities as e')
            ->join('organization_entity_types as t', 't.id', '=', 'e.entity_type_id')
            ->where('e.team_id', $teamId)
            ->whereNull('e.deleted_at')
            ->where('t.code', 'person')
            ->pluck('e.id')
            ->flip();

        $today = now()->toDateString();
        $now = now();
        $rows = [];
        $count = 0;

        DB::table('organization_entity_relationships')
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->whereIn('relation_type_id', array_keys($typeCaps))
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->select('from_entity_id', 'to_entity_id', 'relation_type_id', 'valid_from', 'valid_to')
            ->orderBy('id')
            ->chunk(1000, function ($rels) use (&$rows, &$count, $typeCaps, $personIds, $teamId, $now) {
                foreach ($rels as $r) {
                    if (! isset($personIds[$r->from_entity_id]) || ! $r->to_entity_id) {
                        continue; // nur Person → Entity
                    }
                    $cap = $typeCaps[$r->relation_type_id] ?? null;
                    if (! in_array($cap, ['read', 'write', 'manage'], true)) {
                        continue;
                    }
                    $rows[] = [
                        'subject_type' => 'entity',
                        'subject_id'   => (int) $r->from_entity_id,
                        'capability'   => $cap,
                        'scope_type'   => 'entity',
                        'scope_id'     => (int) $r->to_entity_id,
                        'scope_key'    => null,
                        'source'       => 'org:relation',
                        'valid_from'   => $r->valid_from,
                        'valid_to'     => $r->valid_to,
                        'team_id'      => $teamId,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                    $count++;
                    if (count($rows) >= 500) {
                        DB::table('authz_grant')->insert($rows);
                        $rows = [];
                    }
                }
            });
        if ($rows) {
            DB::table('authz_grant')->insert($rows);
        }

        return $count;
    }
}
