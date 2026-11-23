<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Platform\Organization\Contracts\StrategySource;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Strategy\StrategySources;
use Tests\TestCase;

/**
 * Meilensteine für die Lage eines Agenten: scope=mine grenzt auf die Einheiten ein, die er verantwortet
 * (und darunter); past=N bringt noch offene aus vergangenen Quartalen als überfällig.
 * Aufruf: tests/run-in-taiste.sh tests/Feature/AgentMilestoneScopeTest.php
 */
class AgentMilestoneScopeTest extends TestCase
{
    private array $ids = [];
    private int $teamId;
    private $bot;

    protected function setUp(): void
    {
        parent::setUp();
        $files = array_merge(
            glob(base_path('vendor/martin3r/platform-core/database/migrations/*.php')),
            glob(base_path('vendor/martin3r/platform-organization/database/migrations/*.php')),
        );
        usort($files, fn ($a, $b) => strcmp(basename($a), basename($b)));
        foreach ($files as $f) {
            try {
                (require $f)->up();
            } catch (\Throwable) {
            }
        }
        $U = \Platform\Core\Models\User::class;
        $owner = $U::create(['name' => 'Martin', 'email' => 'm@x.test', 'password' => 'x']);
        $this->teamId = DB::table('teams')->insertGetId(['name' => 'T', 'user_id' => $owner->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->bot = $U::create(['name' => 'Blurr', 'email' => 'blurr@x.test', 'password' => 'x']);
        $g = $this->row('organization_entity_type_groups', ['code' => 'g', 'name' => 'G', 'team_id' => $this->teamId, 'is_active' => true, 'sort_order' => 1]);
        $type = fn ($code) => DB::table('organization_entity_types')->where('code', $code)->value('id')
            ?? $this->row('organization_entity_types', ['code' => $code, 'name' => $code, 'team_id' => $this->teamId, 'is_active' => true, 'sort_order' => 1, 'entity_type_group_id' => $g, 'vsm_class' => 'carrier']);
        $e = fn ($name, $t, $parent = null, $linked = null) => $this->row('organization_entities', ['name' => $name, 'team_id' => $this->teamId, 'user_id' => $owner->id, 'entity_type_id' => $type($t), 'parent_entity_id' => $parent, 'is_active' => true, 'linked_user_id' => $linked]);
        $this->ids['root'] = $e('BHG', 'venture_builder');
        $this->ids['sylt'] = $e('SYLTJUNKIE', 'venture', $this->ids['root']);
        $this->ids['web'] = $e('Website', 'initiative', $this->ids['sylt']);
        $this->ids['other'] = $e('FOOD.WORKS', 'venture', $this->ids['root']);
        $this->ids['blurr'] = $e('BLURR', 'agent', $this->ids['root'], $this->bot->id);
        $rt = $this->row('organization_entity_relation_types', ['code' => 'owns_venture', 'name' => 'Venture Owner von', 'is_active' => true, 'sort_order' => 1, 'capabilities' => json_encode(['manage'])]);
        $this->row('organization_entity_relationships', ['from_entity_id' => $this->ids['blurr'], 'to_entity_id' => $this->ids['sylt'], 'relation_type_id' => $rt, 'team_id' => $this->teamId, 'user_id' => $owner->id]);

        $ids = $this->ids;
        StrategySources::register(new class($ids) implements StrategySource {
            public function __construct(private array $ids) {}

            public function forEntity(OrganizationEntity $entity): ?array { return null; }

            public function milestonesForTeam(int $teamId, array $slots): array
            {
                $now = now()->startOfQuarter();
                $last = $now->copy()->subQuarter();
                $all = [
                    ['id' => 1, 'title' => 'Graph v1', 'entity_id' => $this->ids['sylt'], 'target_year' => $now->year, 'target_quarter' => $now->quarter, 'status' => 'planned'],
                    ['id' => 2, 'title' => 'Website live', 'entity_id' => $this->ids['web'], 'target_year' => $last->year, 'target_quarter' => $last->quarter, 'status' => 'in_progress'],
                    ['id' => 3, 'title' => 'Alt erreicht', 'entity_id' => $this->ids['sylt'], 'target_year' => $last->year, 'target_quarter' => $last->quarter, 'status' => 'reached'],
                    ['id' => 4, 'title' => 'Fremd', 'entity_id' => $this->ids['other'], 'target_year' => $now->year, 'target_quarter' => $now->quarter, 'status' => 'planned'],
                ];
                $want = array_map(fn ($s) => $s[0] . '-' . $s[1], $slots);

                return array_values(array_filter($all, fn ($m) => in_array($m['target_year'] . '-' . $m['target_quarter'], $want, true)));
            }

            public function contribute(int $teamId, int $milestoneId, string $linkableType, int $linkableId, ?int $weight, ?int $userId): ?array { return null; }

            public function alignmentsFor(array $idsByAlias): array { return []; }
        });
    }

    private function row(string $table, array $data): int
    {
        $data += ['uuid' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()];

        return DB::table($table)->insertGetId(array_intersect_key($data, array_flip(Schema::getColumnListing($table))));
    }

    public function test_mine_keeps_my_units_and_past_brings_only_open_ones_as_overdue(): void
    {
        $res = $this->actingAs($this->bot, 'api')->getJson('/api/org/agent/milestones?scope=mine&past=1&quarters=1')->assertOk();
        $ms = collect($res->json('data.milestones'))->keyBy('id');

        $this->assertSame([1, 2], $ms->keys()->sort()->values()->all(), 'eigene Einheit + darunter, ohne Erreichtes und ohne Fremdes');
        $this->assertFalse($ms[1]['overdue']);
        $this->assertTrue($ms[2]['overdue']);
        $this->assertContains($this->ids['web'], $res->json('data.domain'));
    }

    public function test_without_scope_the_whole_team_is_listed(): void
    {
        $res = $this->actingAs($this->bot, 'api')->getJson('/api/org/agent/milestones?quarters=1')->assertOk();
        $this->assertSame([1, 4], collect($res->json('data.milestones'))->pluck('id')->sort()->values()->all());
    }
}
