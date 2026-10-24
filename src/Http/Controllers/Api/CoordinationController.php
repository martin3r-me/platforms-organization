<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Comms/Koordinations-Sink: Parlan (hält den gefalteten Fabric-Stand) pusht ROHE Koordinations-
 * Events (ein Event je verbindlicher Handlung). Wir lösen actor_handle→User (users.email) auf und
 * speichern das Event idempotent (upsert über event_id). Die Org rechnet NICHTS live — der Snapshot
 * aggregiert daraus die 7½-Dimensionen-Kennzahlen.
 *
 * Kontext (TRAAN rooms/0.2): Events aus einem Kontext-Raum tragen `context_ref`
 * (`<herkunft>/<morph-alias>/<id>`); der Snapshot verortet sie darüber an der Einheit des
 * Objekts. Dort kommen auch Nachrichten (kind `message`, nur Metadaten) an.
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
            'events.*.context_ref' => ['nullable', 'string', 'max:300'],
            'events.*.latency_seconds' => ['nullable', 'integer', 'min:0'],
            'events.*.occurred_at' => ['nullable', 'date'],
        ]);

        $r = \Platform\Organization\Services\CoordinationIngest::store($data['events']);

        return response()->json([
            'ok' => true,
            'stored' => $r['stored'],
            'unresolved' => $r['unresolved'],
        ]);
    }
}
