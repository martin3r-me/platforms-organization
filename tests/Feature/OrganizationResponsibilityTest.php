<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Platform\Organization\Contracts\AvailabilityProvider;
use Platform\Organization\Models\OrganizationDeputy;
use Platform\Organization\Services\ResponsibilityResolver;
use Tests\TestCase;

/**
 * Zuständigkeit: der nächste Verantwortliche (Fähigkeit manage) ab einem Knoten aufwärts, mit Rang,
 * Befangenheit, Abwesenheit, Vertretung, Rollen und Agenten. Aufruf: tests/run-in-taiste.sh tests/Feature/OrganizationResponsibilityTest.php
 */
class OrganizationResponsibilityTest extends TestCase
{
    private array $ids = [];
    private int $teamId;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $files = array_merge(
            glob(base_path('vendor/martin3r/platform-core/database/migrations/*.php')),
            glob(base_path('vendor/martin3r/platform-organization/database/migrations/*.php')),
        );
        usort($files, fn ($a, $b) => strcmp(basename($a), basename($b)));
        foreach ($files as $f) {
            try {
                (require $f)->up();
            } catch (\Throwable $e) {
                // auf sqlite nicht lauffähige Fremd-Migrationen ignorieren
            }
        }
        $this->assertTrue(Schema::hasTable('organization_deputies'));
        $this->assertTrue(Schema::hasColumn('organization_entity_relation_types', 'responsibility_rank'));
        $this->seedWorld();
    }

    private function row(string $table, array $data): int
    {
        $data += ['uuid' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()];

        return DB::table($table)->insertGetId(array_intersect_key($data, array_flip(Schema::getColumnListing($table))));
    }

    private function seedWorld(): void
    {
        $U = \Platform\Core\Models\User::class;
        $this->user = $U::create(['name' => 'Martin', 'email' => 'm@x.test', 'password' => 'x']);
        $this->teamId = DB::table('teams')->insertGetId(['name' => 'T', 'user_id' => $this->user->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        $g = $this->row('organization_entity_type_groups', ['code' => 'g', 'name' => 'G', 'team_id' => $this->teamId, 'is_active' => true, 'sort_order' => 1]);
        $type = fn ($code, $class) => DB::table('organization_entity_types')->where('code', $code)->value('id')
            ?? $this->row('organization_entity_types', ['code' => $code, 'name' => $code, 'team_id' => $this->teamId, 'is_active' => true, 'sort_order' => 1, 'entity_type_group_id' => $g, 'vsm_class' => $class]);
        $unit = $type('business_unit', 'carrier');
        $person = $type('person', 'actor');
        $agent = $type('agent', 'actor');
        $e = function ($name, $t, $parent = null, $linked = null) {
            return $this->row('organization_entities', ['name' => $name, 'team_id' => $this->teamId, 'user_id' => $this->user->id, 'entity_type_id' => $t, 'parent_entity_id' => $parent, 'is_active' => true, 'linked_user_id' => $linked]);
        };
        $this->ids['root'] = $e('BHG.DIGITAL', $unit);
        $this->ids['venture'] = $e('FOOD.WORKS', $unit, $this->ids['root']);
        $this->ids['project'] = $e('Projekt P', $unit, $this->ids['venture']);
        $this->ids['owner'] = $e('Owner', $person, null, $this->user->id);
        $this->ids['coowner'] = $e('Co-Owner', $person);
        $this->ids['ceo'] = $e('Geschäftsführung', $person);
        $this->ids['deputy'] = $e('Vertretung', $person);
        $this->ids['pl'] = $e('Projektleitung', $person);
        $this->ids['bot'] = $e('Ironhide', $agent);

        $rt = function ($code, $caps, $rank) {
            $id = DB::table('organization_entity_relation_types')->where('code', $code)->value('id')
                ?? $this->row('organization_entity_relation_types', ['code' => $code, 'name' => $code, 'is_active' => true, 'sort_order' => 1]);
            DB::table('organization_entity_relation_types')->where('id', $id)->update(['capabilities' => json_encode($caps), 'responsibility_rank' => $rank]);

            return $id;
        };
        $this->ids['rt_owner'] = $rt('owns_venture', ['manage'], 10);
        $this->ids['rt_co'] = $rt('co_owns_venture', ['manage'], 20);
        $this->ids['rt_manages'] = $rt('manages', ['manage'], 30);
        $this->ids['rt_works'] = $rt('works_for', ['read'], null);
        $this->ids['rt_reports'] = $rt('reports_to', [], null);
    }

    private function rel(string $from, string $to, string $type): void
    {
        $this->row('organization_entity_relationships', ['from_entity_id' => $this->ids[$from], 'to_entity_id' => $this->ids[$to], 'relation_type_id' => $this->ids[$type], 'team_id' => $this->teamId, 'user_id' => $this->user->id]);
    }

    private function resolver(): ResponsibilityResolver
    {
        return new ResponsibilityResolver();
    }

    private function names(array $holders): array
    {
        return array_column($holders, 'name');
    }

    public function test_nearest_responsible_from_below_ordered_by_rank(): void
    {
        $this->rel('coowner', 'venture', 'rt_co');
        $this->rel('owner', 'venture', 'rt_owner');
        $this->rel('ceo', 'root', 'rt_manages');
        $this->rel('pl', 'project', 'rt_works'); // nur read – zählt nicht

        $r = $this->resolver()->responsibleFor($this->ids['project']);
        $this->assertTrue($r['found']);
        $this->assertSame('FOOD.WORKS', $r['node']['name']);
        $this->assertSame(1, $r['distance']);
        $this->assertSame(['Owner', 'Co-Owner'], $this->names($r['holders']));
        $this->assertSame('owns_venture', $r['holders'][0]['via_code']);
        $this->assertSame($this->user->id, $r['holders'][0]['user_id']);
        $this->assertSame([['id' => $this->ids['project'], 'name' => 'Projekt P', 'reason' => 'niemand verantwortlich']], $r['path']);

        // befangen: der Owner ist Gegenstand des Signals
        $r = $this->resolver()->responsibleFor($this->ids['project'], 'manage', [$this->ids['owner']]);
        $this->assertSame(['Co-Owner'], $this->names($r['holders']));
    }

    public function test_walks_up_to_the_root_and_reports_nothing_found(): void
    {
        $r = $this->resolver()->responsibleFor($this->ids['project']);
        $this->assertFalse($r['found']);
        $this->assertCount(3, $r['path']);

        $this->rel('ceo', 'root', 'rt_manages');
        $r = $this->resolver()->responsibleFor($this->ids['project']);
        $this->assertSame('BHG.DIGITAL', $r['node']['name']);
        $this->assertSame(2, $r['distance']);
    }

    public function test_absence_and_deputies(): void
    {
        $this->rel('owner', 'venture', 'rt_owner');
        $this->rel('coowner', 'venture', 'rt_co');
        $absent = [$this->ids['owner'] => ['until' => now()->addDays(5)->toDateString(), 'kind' => 'vacation']];
        $this->app->instance(AvailabilityProvider::class, new class($absent) implements AvailabilityProvider {
            public function __construct(private array $absent) {}
            public function absence(int $personEntityId, \DateTimeInterface $at): ?array { return $this->absent[$personEntityId] ?? null; }
        });

        // ohne Vertretung: der Nächste im Rang am Knoten
        $this->assertSame(['Co-Owner'], $this->names($this->resolver()->holdersAt($this->ids['venture'])));

        // Vertretung bei Abwesenheit, auf den Venture-Knoten beschränkt: übernimmt den Rang des Owners
        OrganizationDeputy::create(['team_id' => $this->teamId, 'principal_entity_id' => $this->ids['owner'], 'deputy_entity_id' => $this->ids['deputy'], 'scope_entity_id' => $this->ids['venture']]);
        $h = $this->resolver()->holdersAt($this->ids['venture']);
        $this->assertSame(['Vertretung', 'Co-Owner'], $this->names($h));
        $this->assertStringContainsString('vertritt Owner (abwesend bis', $h[0]['reason']);
        $this->assertSame($this->ids['owner'], $h[0]['deputy_for']['entity_id']);

        // abgelaufene Vertretung zählt nicht
        OrganizationDeputy::query()->update(['valid_to' => now()->subDay()->toDateString()]);
        $this->assertSame(['Co-Owner'], $this->names($this->resolver()->holdersAt($this->ids['venture'])));
    }

    public function test_general_deputy_is_always_included(): void
    {
        $this->rel('owner', 'venture', 'rt_owner');
        OrganizationDeputy::create(['team_id' => $this->teamId, 'principal_entity_id' => $this->ids['owner'], 'deputy_entity_id' => $this->ids['deputy'], 'only_when_absent' => false]);
        $h = $this->resolver()->holdersAt($this->ids['venture']);
        $this->assertSame(['Owner', 'Vertretung'], $this->names($h));
        $this->assertSame('vertritt Owner generell', $h[1]['reason']);
    }

    public function test_roles_with_manage_count_and_agents_need_a_heartbeat(): void
    {
        $role = $this->row('organization_roles', ['team_id' => $this->teamId, 'user_id' => $this->user->id, 'name' => 'Projektleitung', 'slug' => 'projektleitung', 'status' => 'active', 'capabilities' => json_encode(['manage']), 'responsibility_rank' => 40]);
        $this->row('organization_role_assignments', ['team_id' => $this->teamId, 'user_id' => $this->user->id, 'role_id' => $role, 'person_entity_id' => $this->ids['pl'], 'context_entity_id' => $this->ids['project']]);
        $r = $this->resolver()->responsibleFor($this->ids['project']);
        $this->assertSame(0, $r['distance']);
        $this->assertSame(['Projektleitung'], $this->names($r['holders']));
        $this->assertSame('role', $r['holders'][0]['via']);

        // Agent führt den Venture-Knoten; offline = abwesend, online = verantwortlich, mit seinem Menschen
        $this->rel('bot', 'venture', 'rt_manages');
        $this->rel('bot', 'ceo', 'rt_reports');
        $this->row('organization_agent_profiles', ['organization_entity_id' => $this->ids['bot'], 'active' => true, 'last_heartbeat_at' => now()->subDay()]);
        $this->assertSame([], $this->resolver()->holdersAt($this->ids['venture']));
        DB::table('organization_agent_profiles')->update(['last_heartbeat_at' => now()]);
        $h = $this->resolver()->holdersAt($this->ids['venture']);
        $this->assertSame(['Ironhide'], $this->names($h));
        $this->assertSame('agent', $h[0]['kind']);
        $this->assertSame('Geschäftsführung', $h[0]['supervisor']['name']);
    }

    public function test_tools_answer_and_guard_deputies(): void
    {
        $this->rel('owner', 'venture', 'rt_owner');
        DB::table('team_user')->insert(['team_id' => $this->teamId, 'user_id' => $this->user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        $this->user->forceFill(['current_team_id' => $this->teamId])->save();
        $all = DB::table('organization_entities')->pluck('id')->map(fn ($i) => (int) $i)->all();
        $this->app->instance(\Platform\Core\Authz\AuthzResolver::class, new class($all) extends \Platform\Core\Authz\AuthzResolver {
            public function __construct(private array $all) {}
            public function reachableEntityIds(\Platform\Core\Models\User $user, string $cap = 'read'): array { return $this->all; }
        });
        $ctx = new \Platform\Core\Contracts\ToolContext($this->user->fresh(), \Platform\Core\Models\Team::find($this->teamId));

        $res = (new \Platform\Organization\Tools\GetResponsibleTool())->execute(['entity_id' => $this->ids['project']], $ctx);
        $this->assertTrue($res->success, json_encode($res->toArray()));
        $this->assertSame('FOOD.WORKS', $res->data['node']['name']);

        $post = (new \Platform\Organization\Tools\CreateDeputyTool())->execute(['principal_entity_id' => $this->ids['owner'], 'deputy_entity_id' => $this->ids['deputy'], 'valid_to' => now()->addMonth()->toDateString()], $ctx);
        $this->assertTrue($post->success, json_encode($post->toArray()));
        $this->assertTrue($post->data['deputy']['only_when_absent']);
        $bad = (new \Platform\Organization\Tools\CreateDeputyTool())->execute(['principal_entity_id' => $this->ids['owner'], 'deputy_entity_id' => $this->ids['venture']], $ctx);
        $this->assertFalse($bad->success, 'nur Personen und Agenten können vertreten');
        $list = (new \Platform\Organization\Tools\ListDeputyTool())->execute([], $ctx);
        $this->assertCount(1, $list->data['deputies']);
        $del = (new \Platform\Organization\Tools\DeleteDeputyTool())->execute(['id' => $post->data['deputy']['id']], $ctx);
        $this->assertTrue($del->success);
    }
}
