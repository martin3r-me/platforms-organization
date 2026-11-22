<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Event;
use Platform\Organization\Events\CoordinationEventsRecorded;
use Platform\Organization\Services\CoordinationIngest;
use Tests\TestCase;

/** Neue Parlan-Ereignisse werden allgemein gemeldet – jedes nur einmal. */
class OrganizationCoordinationEventTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $files = array_merge(glob(base_path('vendor/martin3r/platform-core/database/migrations/*.php')), glob(base_path('vendor/martin3r/platform-organization/database/migrations/*.php')));
        usort($files, fn ($a, $b) => strcmp(basename($a), basename($b)));
        foreach ($files as $f) {
            try {
                (require $f)->up();
            } catch (\Throwable) {
            }
        }
    }

    public function test_new_events_are_announced_once(): void
    {
        \Platform\Core\Models\User::create(['name' => 'Martin', 'email' => 'm@x.test', 'password' => 'x']);
        $seen = [];
        Event::listen(CoordinationEventsRecorded::class, function ($e) use (&$seen) {
            $seen[] = $e->events;
        });
        $ev = [['event_id' => 'e1', 'actor_handle' => 'M@x.test', 'kind' => 'handoff_done', 'thread' => 'T1', 'context_ref' => 'office/organization_entity/5'], ['event_id' => 'e2', 'actor_handle' => 'fremd@x.test', 'kind' => 'message']];

        $r = CoordinationIngest::store($ev);
        $this->assertSame(1, $r['stored']);
        $this->assertCount(1, $seen);
        $this->assertSame('handoff_done', $seen[0][0]['kind']);
        $this->assertSame('T1', $seen[0][0]['thread']);

        CoordinationIngest::store($ev); // dasselbe Ereignis noch einmal (Pull nach Push)
        $this->assertCount(1, $seen, 'bekannte Ereignisse werden nicht erneut gemeldet');
    }
}
