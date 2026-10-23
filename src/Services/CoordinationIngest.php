<?php

namespace Platform\Organization\Services;

use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationCoordinationEvent;

/**
 * Speichert rohe Koordinations-Ereignisse (TRAAN/Parlan) idempotent über event_id. actor_handle
 * wird über users.email einem Org-User zugeordnet; ohne User wird das Ereignis verworfen und
 * ehrlich zurückgemeldet. Genutzt von der Push-Senke (CoordinationController) und vom Abholen
 * der Kontext-Kommunikation (organization:parlan-pull).
 */
class CoordinationIngest
{
    /**
     * @param  list<array<string,mixed>>  $events
     * @return array{stored:int, unresolved:list<string>}
     */
    public static function store(array $events): array
    {
        $handles = array_values(array_unique(array_map(
            fn ($e) => strtolower(trim((string) ($e['actor_handle'] ?? ''))),
            $events,
        )));
        $usersByEmail = DB::table('users')
            ->whereIn(DB::raw('LOWER(email)'), $handles)
            ->pluck('id', DB::raw('LOWER(email)'));

        $stored = 0;
        $unresolved = [];
        foreach ($events as $e) {
            $handle = strtolower(trim((string) ($e['actor_handle'] ?? '')));
            $userId = $usersByEmail[$handle] ?? null;
            if ($userId === null || empty($e['event_id'])) {
                $unresolved[] = $handle;
                continue;
            }
            OrganizationCoordinationEvent::updateOrCreate(
                ['event_id' => (string) $e['event_id']],
                [
                    'actor_user_id' => $userId,
                    'actor_handle' => $handle,
                    'counterpart_handle' => isset($e['counterpart_handle']) ? strtolower(trim((string) $e['counterpart_handle'])) : null,
                    'kind' => mb_substr((string) ($e['kind'] ?? ''), 0, 32),
                    'thread' => isset($e['thread']) ? mb_substr((string) $e['thread'], 0, 64) : null,
                    'context_ref' => isset($e['context_ref']) ? mb_substr((string) $e['context_ref'], 0, 300) : null,
                    'latency_seconds' => isset($e['latency_seconds']) ? (int) $e['latency_seconds'] : null,
                    'occurred_at' => $e['occurred_at'] ?? now(),
                    'created_at' => now(),
                ],
            );
            $stored++;
        }

        return ['stored' => $stored, 'unresolved' => array_values(array_unique(array_filter($unresolved)))];
    }
}
