<?php

namespace Platform\Organization\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Models\OrganizationCoordinationMetric;

/**
 * Comms/Koordinations-Sink: Parlan (hält den gefalteten Fabric-Stand) pusht pro HANDLE fertige
 * Koordinations-Kennzahlen; wir lösen Handle→User (users.email) auf und speichern den neuesten
 * Stand (eine Zeile je Handle, upsert). Die Org rechnet NICHTS selbst — sie nimmt an, was die
 * eine Quelle (die Fabric via Parlan) meldet, und snapshottet es wie jede andere Metrik.
 *
 * Auth: auth:api mit einem Parlan-Service-Token (eigener Bot-User) — wie dev/helpdesk/agent.
 */
class CoordinationController extends Controller
{
    public function ingest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'events' => ['required', 'array', 'max:500'],
            'events.*.handle' => ['required', 'string', 'max:190'],
            'events.*.metrics' => ['required', 'array'],
            'events.*.reported_at' => ['nullable', 'date'],
        ]);

        // Handles → user_id in EINER Abfrage (Handle = E-Mail des Org-Users).
        $handles = array_values(array_unique(array_map(
            fn ($e) => strtolower(trim((string) $e['handle'])),
            $data['events'],
        )));
        $usersByEmail = DB::table('users')
            ->whereIn(DB::raw('LOWER(email)'), $handles)
            ->pluck('id', DB::raw('LOWER(email)'));

        $stored = 0;
        $unresolved = [];
        foreach ($data['events'] as $e) {
            $handle = strtolower(trim((string) $e['handle']));
            $userId = $usersByEmail[$handle] ?? null;
            if ($userId === null) {
                $unresolved[] = $handle; // kein Org-User zu diesem Handle → ehrlich zurückmelden
                continue;
            }
            OrganizationCoordinationMetric::updateOrCreate(
                ['handle' => $handle],
                [
                    'user_id' => $userId,
                    'metrics' => $e['metrics'],
                    'reported_at' => $e['reported_at'] ?? now(),
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
