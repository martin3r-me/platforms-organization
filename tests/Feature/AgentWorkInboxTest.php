<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Platform\Organization\Contracts\AgentWorkSource;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Support\AgentWorkSources;
use Tests\TestCase;

/**
 * Arbeitseingang der Agenten: Module legen über AgentWorkSource Arbeit hin, GET /api/org/agent/work
 * liefert sie dem Agenten (dringendstes zuerst); eine ausgefallene Quelle steht mit ok=false da.
 * Aufruf: tests/run-in-taiste.sh tests/Feature/AgentWorkInboxTest.php
 */
class AgentWorkInboxTest extends TestCase
{
    private array $ids = [];
    private int $teamId;
    private $bot;
    private $human;

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
        $this->human = $U::create(['name' => 'Martin', 'email' => 'm@x.test', 'password' => 'x']);
        $this->teamId = DB::table('teams')->insertGetId(['name' => 'T', 'user_id' => $this->human->id, 'personal_team' => false, 'created_at' => now(), 'updated_at' => now()]);
        $this->bot = $U::create(['name' => 'Ironhide', 'email' => 'ironhide@x.test', 'password' => 'x']);
        $g = $this->row('organization_entity_type_groups', ['code' => 'g', 'name' => 'G', 'team_id' => $this->teamId, 'is_active' => true, 'sort_order' => 1]);
        $type = fn ($code) => DB::table('organization_entity_types')->where('code', $code)->value('id')
            ?? $this->row('organization_entity_types', ['code' => $code, 'name' => $code, 'team_id' => $this->teamId, 'is_active' => true, 'sort_order' => 1, 'entity_type_group_id' => $g]);
        $this->ids['root'] = $this->row('organization_entities', ['name' => 'BHG', 'team_id' => $this->teamId, 'user_id' => $this->human->id, 'entity_type_id' => $type('venture_builder'), 'is_active' => true]);
        $this->ids['agent'] = $this->row('organization_entities', ['name' => 'Ironhide', 'team_id' => $this->teamId, 'user_id' => $this->human->id, 'entity_type_id' => $type('agent'), 'parent_entity_id' => $this->ids['root'], 'is_active' => true, 'linked_user_id' => $this->bot->id]);

        foreach (AgentWorkSources::keys() as $key) {
            AgentWorkSources::forget($key);
        }
    }

    protected function tearDown(): void
    {
        foreach (AgentWorkSources::keys() as $key) {
            AgentWorkSources::forget($key);
        }
        parent::tearDown();
    }

    private function row(string $table, array $data): int
    {
        $data += ['uuid' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()];

        return DB::table($table)->insertGetId(array_intersect_key($data, array_flip(Schema::getColumnListing($table))));
    }

    private function source(string $key, callable $fn): AgentWorkSource
    {
        return new class($key, $fn) implements AgentWorkSource {
            public function __construct(private string $k, private $fn) {}

            public function key(): string { return $this->k; }

            public function workFor(OrganizationEntity $agent): array { return ($this->fn)($agent); }
        };
    }

    public function test_agent_gets_the_work_modules_left_for_him_most_urgent_first(): void
    {
        $agentId = $this->ids['agent'];
        AgentWorkSources::register($this->source('signals', fn (OrganizationEntity $a) => $a->id === $agentId ? [
            ['key' => 'signals.deliveries', 'text' => '4 Zustell-Aufträge bereit', 'count' => 4, 'weight' => 0.6, 'tool' => 'signals.deliveries.GET', 'how' => 'abholen, ausführen, melden'],
        ] : []));
        AgentWorkSources::register($this->source('mb', fn () => [
            ['key' => 'mb.drafts', 'text' => '1 LA-Entwurf fällig', 'count' => 1, 'weight' => 0.9, 'tool' => 'management-book.drafts.GET'],
            ['key' => 'mb.nothing', 'text' => 'leer', 'count' => 0, 'tool' => 'x'],
        ]));

        $res = $this->actingAs($this->bot, 'api')->getJson('/api/org/agent/work')->assertOk();

        $this->assertSame(['mb.drafts', 'signals.deliveries'], collect($res->json('data.items'))->pluck('key')->all(), 'dringendstes zuerst, count 0 fällt weg');
        $this->assertSame('signals.deliveries.GET', $res->json('data.items.1.tool'));
        $this->assertSame(4, $res->json('data.items.1.count'));
        $this->assertSame([true, true], collect($res->json('data.sources'))->pluck('ok')->all());
    }

    public function test_a_failing_source_is_reported_not_silently_empty(): void
    {
        AgentWorkSources::register($this->source('broken', fn () => throw new \RuntimeException('DB weg')));

        $res = $this->actingAs($this->bot, 'api')->getJson('/api/org/agent/work')->assertOk();

        $this->assertSame([], $res->json('data.items'));
        $this->assertFalse($res->json('data.sources.0.ok'));
        $this->assertSame('broken', $res->json('data.sources.0.source'));
    }

    public function test_non_agents_get_404(): void
    {
        $this->actingAs($this->human, 'api')->getJson('/api/org/agent/work')->assertNotFound();
    }
}
