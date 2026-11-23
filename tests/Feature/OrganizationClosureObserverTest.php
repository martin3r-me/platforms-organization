<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Platform\Core\Authz\AuthzResolver;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\AuthzClosureSync;
use Tests\TestCase;

/**
 * Hot-Path der authz_scope_closure: neue, umgehängte, gelöschte und wiederhergestellte Entities sind sofort
 * im Closure-Index (ohne authz:materialize). Aufruf: tests/run-in-taiste.sh tests/Feature/OrganizationClosureObserverTest.php
 */
class OrganizationClosureObserverTest extends TestCase
{
    private int $teamId;
    private int $typeId;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $files = array_merge(
            glob(base_path('vendor/martin3r/platform-core/database/migrations/*.php')),
            glob(base_path('vendor/martin3r/platform-organization/database/migrations/*.php')),
            glob(base_path('vendor/martin3r/platform-activity-log/database/migrations/*.php')) ?: [], // LogsActivity der Entity
        );
        usort($files, fn ($a, $b) => strcmp(basename($a), basename($b)));
        foreach ($files as $f) {
            try {
                (require $f)->up();
            } catch (\Throwable $e) {
                // auf sqlite nicht lauffähige Fremd-Migrationen ignorieren
            }
        }
        $this->assertTrue(Schema::hasTable('authz_scope_closure'));
        AuthzClosureSync::flushTableCache();

        $U = \Platform\Core\Models\User::class;
        $this->user = $U::create(['name' => 'Martin', 'email' => 'm@x.test', 'password' => 'x']);
        $this->teamId = DB::table('teams')->insertGetId(['name' => 'T', 'user_id' => $this->user->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->user->forceFill(['current_team_id' => $this->teamId])->save();
        $g = $this->row('organization_entity_type_groups', ['code' => 'g', 'name' => 'G', 'team_id' => $this->teamId, 'is_active' => true, 'sort_order' => 1]);
        $this->typeId = DB::table('organization_entity_types')->where('code', 'business_unit')->value('id')
            ?? $this->row('organization_entity_types', ['code' => 'business_unit', 'name' => 'BU', 'team_id' => $this->teamId, 'is_active' => true, 'sort_order' => 1, 'entity_type_group_id' => $g, 'vsm_class' => 'carrier']);
    }

    private function row(string $table, array $data): int
    {
        $data += ['uuid' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()];

        return DB::table($table)->insertGetId(array_intersect_key($data, array_flip(Schema::getColumnListing($table))));
    }

    private function entity(string $name, ?int $parent = null): OrganizationEntity
    {
        return OrganizationEntity::create(['name' => $name, 'team_id' => $this->teamId, 'user_id' => $this->user->id, 'entity_type_id' => $this->typeId, 'parent_entity_id' => $parent, 'is_active' => true]);
    }

    private function ancestors(int $id): array
    {
        return DB::table('authz_scope_closure')->where('descendant_id', $id)->orderBy('depth')
            ->get(['ancestor_id', 'depth', 'team_id'])
            ->map(fn ($r) => [(int) $r->ancestor_id, (int) $r->depth, (int) $r->team_id])->all();
    }

    private function grantWrite(int $entityId): void
    {
        DB::table('authz_grant')->insert(['subject_type' => 'user', 'subject_id' => $this->user->id, 'capability' => 'write', 'scope_type' => 'entity', 'scope_id' => $entityId, 'team_id' => $this->teamId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function writable(): array
    {
        return app(AuthzResolver::class)->reachableEntityIds($this->user->fresh(), 'write');
    }

    public function test_new_child_inherits_write_immediately_and_move_and_delete_follow(): void
    {
        $root = $this->entity('Root');
        $p = $this->entity('Governance', $root->id);
        $q = $this->entity('Operations', $root->id);
        $this->grantWrite($p->id);

        // Anlegen in einer äußeren Transaktion (wie management-book) → sofort schreibbar
        $c = DB::transaction(fn () => $this->entity('Gremium', $p->id));
        $this->assertContains($c->id, $this->writable());
        $this->assertSame([[$c->id, 0, $this->teamId], [$p->id, 1, $this->teamId], [$root->id, 2, $this->teamId]], $this->ancestors($c->id));

        // Enkel unter C, dann C samt Teilbaum unter Q umhängen
        $gc = $this->entity('Arbeitskreis', $c->id);
        $this->assertSame([[$gc->id, 0, $this->teamId], [$c->id, 1, $this->teamId], [$p->id, 2, $this->teamId], [$root->id, 3, $this->teamId]], $this->ancestors($gc->id));
        $c->update(['parent_entity_id' => $q->id]);
        $this->assertSame([[$c->id, 0, $this->teamId], [$q->id, 1, $this->teamId], [$root->id, 2, $this->teamId]], $this->ancestors($c->id));
        $this->assertSame([[$gc->id, 0, $this->teamId], [$c->id, 1, $this->teamId], [$q->id, 2, $this->teamId], [$root->id, 3, $this->teamId]], $this->ancestors($gc->id));
        $this->assertNotContains($c->id, $this->writable());
        $this->assertNotContains($gc->id, $this->writable());
        $this->assertSame(0, DB::table('authz_scope_closure')->where('ancestor_id', $p->id)->whereIn('descendant_id', [$c->id, $gc->id])->count());

        // Umbenennen ändert nichts
        $before = DB::table('authz_scope_closure')->count();
        $c->update(['name' => 'Gremium neu']);
        $this->assertSame($before, DB::table('authz_scope_closure')->count());

        // Soft-Delete: Knoten raus; Kinder wie buildClosure (Kette endet am gelöschten Knoten)
        $c->delete();
        $this->assertSame([], $this->ancestors($c->id));
        $this->assertSame(0, DB::table('authz_scope_closure')->where('ancestor_id', $c->id)->where('descendant_id', $c->id)->count());
        $this->assertSame([[$gc->id, 0, $this->teamId], [$c->id, 1, $this->teamId]], $this->ancestors($gc->id));

        // Restore: Teilbaum wieder vollständig
        $c->restore();
        $this->assertSame([[$c->id, 0, $this->teamId], [$q->id, 1, $this->teamId], [$root->id, 2, $this->teamId]], $this->ancestors($c->id));
        $this->assertSame([[$gc->id, 0, $this->teamId], [$c->id, 1, $this->teamId], [$q->id, 2, $this->teamId], [$root->id, 3, $this->teamId]], $this->ancestors($gc->id));
    }

    public function test_observer_matches_full_materialize(): void
    {
        $root = $this->entity('Root');
        $a = $this->entity('A', $root->id);
        $b = $this->entity('B', $a->id);
        $this->entity('C', $b->id);
        $d = $this->entity('D', $root->id);
        $b->update(['parent_entity_id' => $d->id]);
        $a->delete();

        $snap = fn () => DB::table('authz_scope_closure')->orderBy('ancestor_id')->orderBy('descendant_id')
            ->get(['ancestor_id', 'descendant_id', 'depth', 'team_id'])->map(fn ($r) => (array) $r)->all();
        $hot = $snap();
        $this->artisan('authz:materialize', ['--team' => $this->teamId]);
        $this->assertEquals($snap(), $hot);
    }

    public function test_missing_table_never_breaks_entity_creation(): void
    {
        Schema::drop('authz_scope_closure');
        AuthzClosureSync::flushTableCache();
        $e = $this->entity('Ohne Closure');
        $this->assertTrue($e->exists);
    }
}
