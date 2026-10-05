<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationCoordinationEvent;

/**
 * Comms/Koordinations-Sink: Parlan (hält den gefalteten Fabric-Stand) pusht ROHE Koordinations-
 * Events (ein Event je verbindlicher Handlung). Wir lösen actor_handle→User (users.email) auf und
 * speichern das Event idempotent (upsert über event_id). Die Org rechnet NICHTS live — der Snapshot
 * aggregiert daraus die 7½-Dimensionen-Kennzahlen, wie terminal_messages → terminal-Metriken.
 *
 * Auth: auth:api (Admin-/Service-Token) — wie dev/helpdesk/agent.
 */
class CoordinationController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'max:1000'],
            'events.*.event_id' => ['required', 'string', 'max:64'],
            'events.*.actor_handle' => ['required', 'string', 'max:190'],
            'events.*.counterpart_handle' => ['nullable', 'string', 'max:190'],
            'events.*.kind' => ['required', 'string', 'max:32'],
            'events.*.thread' => ['nullable', 'string', 'max:64'],
            'events.*.latency_seconds' => ['nullable', 'integer', 'min:0'],
            'events.*.occurred_at' => ['nullable', 'date'],
        ]);

        // actor_handles → user_id in EINER Abfrage (Handle = E-Mail des Org-Users).
        $handles = array_values(array_unique(array_map(
            fn ($e) => strtolower(trim((string) $e['actor_handle'])),
            $data['events'],
        )));
        $usersByEmail = DB::table('users')
            ->whereIn(DB::raw('LOWER(email)'), $handles)
            ->pluck('id', DB::raw('LOWER(email)'));

        $stored = 0;
        $unresolved = [];
        foreach ($data['events'] as $e) {
            $handle = strtolower(trim((string) $e['actor_handle']));
            $userId = $usersByEmail[$handle] ?? null;
            if ($userId === null) {
                $unresolved[] = $handle; // kein Org-User → ehrlich zurückmelden, Event verwerfen
                continue;
            }
            OrganizationCoordinationEvent::updateOrCreate(
                ['event_id' => (string) $e['event_id']],
                [
                    'actor_user_id' => $userId,
                    'actor_handle' => $handle,
                    'counterpart_handle' => isset($e['counterpart_handle']) ? strtolower(trim((string) $e['counterpart_handle'])) : null,
                    'kind' => (string) $e['kind'],
                    'thread' => $e['thread'] ?? null,
                    'latency_seconds' => $e['latency_seconds'] ?? null,
                    'occurred_at' => $e['occurred_at'] ?? now(),
                    'created_at' => now(),
                ],
            );
            $stored++;
        }

        return response()->json([
            'ok' => true,
            'stored' => $stored,
            'unresolved' => array_values(array_unique($unresolved)),
        ]);
    }
}
