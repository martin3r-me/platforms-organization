<?php

namespace Platform\Organization\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Neue Koordinations-Ereignisse aus Parlan sind gespeichert (Push-Senke oder parlan-pull). Allgemein gehalten:
 * Wer Vorgänge in Parlan angestoßen hat, kann sie über die Vorgangs-ID (thread) wiederfinden – die Organisation
 * weiß nicht, wer zuhört.
 *
 * @property-read list<array{event_id:string, kind:string, thread:?string, actor_user_id:int, actor_handle:string, context_ref:?string, occurred_at:mixed}> $events
 */
class CoordinationEventsRecorded
{
    use Dispatchable;

    /** @param  list<array<string,mixed>>  $events */
    public function __construct(public readonly array $events)
    {
    }
}
